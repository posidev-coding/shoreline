<?php

namespace App\Services\QuickBooks;

use App\Enums\QuickBooksEnvironment;
use App\Enums\UserRole;
use App\Models\QuickBooksConnection;
use App\Models\QuickBooksSyncLog;
use App\Models\User;
use App\Notifications\QuickBooksNeedsAttention;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

/**
 * QuickBooks' OAuth: connecting a company, keeping its access token fresh, and disconnecting.
 *
 * Access tokens last an hour; refresh tokens roll over about every 24 hours, lapse after 100 days
 * unused, and stop for good after five years. Each refresh happens under a row lock, so two jobs
 * refreshing at once can't both spend the same refresh token, and the rotated one is saved in the
 * same transaction.
 */
final class TokenManager
{
    public const string AUTHORIZE_URL = 'https://appcenter.intuit.com/connect/oauth2';

    public const string TOKEN_URL = 'https://oauth.platform.intuit.com/oauth2/v1/tokens/bearer';

    public const string REVOKE_URL = 'https://developer.api.intuit.com/v2/oauth2/tokens/revoke';

    public const string SCOPE = 'com.intuit.quickbooks.accounting';

    /**
     * Refresh this long before the access token runs out, so a request doesn't start with an
     * hour's token and finish with none.
     */
    private const int EARLY_SECONDS = 300;

    /**
     * Log rows waiting for a refresh's transaction to end, so a failed refresh, which rolls back,
     * still leaves its log.
     *
     * @var list<array<string, mixed>>
     */
    private array $pendingLogs = [];

    private bool $deferLogs = false;

    public function isConfigured(): bool
    {
        return filled(config('services.quickbooks.client_id')) && filled(config('services.quickbooks.client_secret'));
    }

    public function authorizeUrl(string $state, string $redirectUri): string
    {
        $this->ensureConfigured();

        return self::AUTHORIZE_URL.'?'.http_build_query([
            'client_id' => config('services.quickbooks.client_id'),
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'redirect_uri' => $redirectUri,
            'state' => $state,
        ]);
    }

    /**
     * Trades the code from Intuit's redirect for tokens. Reconnecting the same company updates
     * its row; another company is refused before the code is spent.
     */
    public function connect(string $code, string $realmId, string $redirectUri, User $by): QuickBooksConnection
    {
        $this->ensureConfigured();
        $environment = QuickBooksEnvironment::configured();
        $existing = QuickBooksConnection::current();

        if ($existing !== null && ($existing->realm_id !== $realmId || $existing->environment !== $environment)) {
            throw QuickBooksException::differentCompany($existing);
        }

        $tokens = $this->requestTokens(['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => $redirectUri], $existing);

        $connection = $existing ?? new QuickBooksConnection(['environment' => $environment, 'realm_id' => $realmId]);
        $connection->fill([
            'connected_by' => $by->getKey(),
            'connected_at' => now(),
            'disconnected_at' => null,
            'disconnect_reason' => null,
        ]);
        $this->applyTokens($connection, $tokens);
        $connection->save();

        activity()->performedOn($connection)->causedBy($by)->event('quickbooks')
            ->log("Connected QuickBooks ({$environment->getLabel()}) company {$realmId}");

        return $connection;
    }

    /**
     * A current access token, refreshed first if it's about to run out.
     */
    public function accessToken(QuickBooksConnection $connection): string
    {
        if (! $connection->isConnected()) {
            throw QuickBooksException::notConnected();
        }

        if ($this->isFresh($connection)) {
            return (string) $connection->access_token;
        }

        return (string) $this->refresh($connection)->access_token;
    }

    /**
     * Refreshes the access token, unless another process already did. Pass the token QuickBooks
     * just rejected to refresh even if it looks unexpired; pass $force for the daily refresh
     * that keeps the refresh token from lapsing.
     */
    public function refresh(QuickBooksConnection $connection, bool $force = false, ?string $rejected = null): QuickBooksConnection
    {
        $this->ensureConfigured();
        $this->deferLogs = true;

        try {
            $refused = $this->refreshLocked($connection, $force, $rejected);
        } finally {
            $this->deferLogs = false;
            $this->flushLogs();
        }

        if ($refused) {
            Notification::send(self::superAdmins(), new QuickBooksNeedsAttention(
                'QuickBooks was disconnected',
                'QuickBooks refused the app’s refresh token, so nothing syncs until a super admin connects it again.',
            ));

            throw QuickBooksException::refused();
        }

        return $connection;
    }

    /**
     * The refresh itself, under the row lock. Returns whether QuickBooks refused the refresh
     * token, after recording the disconnect.
     */
    private function refreshLocked(QuickBooksConnection $connection, bool $force, ?string $rejected): bool
    {
        return DB::transaction(function () use ($connection, $force, $rejected): bool {
            $locked = QuickBooksConnection::query()->lockForUpdate()->whereKey($connection->getKey())->firstOrFail();

            if (! $locked->isConnected()) {
                throw QuickBooksException::notConnected();
            }

            $refreshedMeanwhile = $rejected !== null ? $locked->access_token !== $rejected : $this->isFresh($locked);

            if (! $force && $refreshedMeanwhile) {
                $connection->setRawAttributes($locked->getAttributes(), sync: true);

                return false;
            }

            try {
                $tokens = $this->requestTokens(['grant_type' => 'refresh_token', 'refresh_token' => $locked->refresh_token], $locked);
            } catch (QuickBooksException $exception) {
                if ($exception->intuitCode !== 'invalid_grant') {
                    throw $exception;
                }

                $this->markDisconnected($locked, 'QuickBooks refused the refresh token (invalid_grant).');
                $connection->setRawAttributes($locked->getAttributes(), sync: true);

                return true;
            }

            $this->applyTokens($locked, $tokens);
            $locked->save();
            $connection->setRawAttributes($locked->getAttributes(), sync: true);

            return false;
        });
    }

    /**
     * Revokes the tokens with Intuit and forgets them here. A failed revoke still disconnects:
     * the tokens are gone from the app either way.
     */
    public function disconnect(QuickBooksConnection $connection, User $by): void
    {
        if (filled($connection->refresh_token) && $this->isConfigured()) {
            try {
                $this->send($connection, 'v2/oauth2/tokens/revoke', fn (): Response => $this->oauthRequest()
                    ->asJson()
                    ->post(self::REVOKE_URL, ['token' => $connection->refresh_token]));
            } catch (QuickBooksException) {
                // Logged; the tokens are forgotten below regardless.
            }
        }

        $this->markDisconnected($connection, "Disconnected by {$by->email}");

        activity()->performedOn($connection)->causedBy($by)->event('quickbooks')
            ->log("Disconnected QuickBooks company {$connection->realm_id}");
    }

    /**
     * @return Collection<int, User>
     */
    public static function superAdmins(): Collection
    {
        return User::query()->where('role', UserRole::SuperAdmin)->get();
    }

    private function isFresh(QuickBooksConnection $connection): bool
    {
        return filled($connection->access_token)
            && $connection->access_expires_at?->isAfter(now()->addSeconds(self::EARLY_SECONDS)) === true;
    }

    /**
     * @param  array<string, string|null>  $form
     * @return array<string, mixed>
     */
    private function requestTokens(array $form, ?QuickBooksConnection $connection): array
    {
        $response = $this->send(
            $connection,
            'oauth2/v1/tokens/bearer',
            fn (): Response => $this->oauthRequest()
                ->asForm()
                ->withHeaders(['x-include-refresh-token-hard-expires-in' => 'true'])
                ->post(self::TOKEN_URL, $form),
            // Which fields Intuit sent, never their values: the hard-expiry field's name is unconfirmed.
            fn (Response $response): string => 'Fields: '.implode(', ', array_keys((array) $response->json())),
        );
        $tokens = (array) $response->json();

        if ($response->failed() || ! isset($tokens['access_token'], $tokens['refresh_token'])) {
            throw QuickBooksException::fromResponse($response);
        }

        return $tokens;
    }

    /**
     * @param  array<string, mixed>  $tokens
     */
    private function applyTokens(QuickBooksConnection $connection, array $tokens): void
    {
        $seconds = fn (string $key): ?int => is_numeric($tokens[$key] ?? null) ? (int) $tokens[$key] : null;

        $connection->fill([
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'access_expires_at' => now()->addSeconds($seconds('expires_in') ?? 3600),
            'refresh_expires_at' => ($rolling = $seconds('x_refresh_token_expires_in')) !== null ? now()->addSeconds($rolling) : null,
            'last_refreshed_at' => now(),
        ]);

        if (($hard = $seconds('x_refresh_token_hard_expires_in')) !== null) {
            $connection->refresh_hard_expires_at = now()->addSeconds($hard);
        }
    }

    private function markDisconnected(QuickBooksConnection $connection, string $reason): void
    {
        $connection->fill([
            'access_token' => null,
            'refresh_token' => null,
            'access_expires_at' => null,
            'disconnected_at' => now(),
            'disconnect_reason' => $reason,
        ])->save();
    }

    private function oauthRequest(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout(20)
            ->withBasicAuth((string) config('services.quickbooks.client_id'), (string) config('services.quickbooks.client_secret'));
    }

    /**
     * Sends a POST to Intuit's OAuth service and logs it, however it went.
     *
     * @param  callable(): Response  $request
     * @param  (callable(Response): string)|null  $detail
     */
    private function send(?QuickBooksConnection $connection, string $path, callable $request, ?callable $detail = null): Response
    {
        $started = hrtime(true);

        try {
            $response = $request();
        } catch (ConnectionException $exception) {
            $this->log($connection, $path, null, $started, $exception->getMessage());

            throw QuickBooksException::unreachable($exception->getMessage());
        }

        $this->log($connection, $path, $response, $started, $detail ? $detail($response) : null);

        return $response;
    }

    private function log(?QuickBooksConnection $connection, string $path, ?Response $response, int $started, ?string $detail): void
    {
        $this->pendingLogs[] = [
            'quickbooks_connection_id' => $connection?->getKey(),
            'method' => 'POST',
            'path' => $path,
            'status' => $response?->status(),
            'intuit_tid' => $response?->header('intuit_tid') ?: null,
            'duration_ms' => intdiv(hrtime(true) - $started, 1_000_000),
            'detail' => $detail,
            'created_at' => now(),
        ];

        if (! $this->deferLogs) {
            $this->flushLogs();
        }
    }

    private function flushLogs(): void
    {
        foreach ($this->pendingLogs as $log) {
            QuickBooksSyncLog::query()->create($log);
        }

        $this->pendingLogs = [];
    }

    private function ensureConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw QuickBooksException::notConfigured();
        }
    }
}
