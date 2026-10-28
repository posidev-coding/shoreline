<?php

use App\Models\QuickBooksConnection;
use App\Models\QuickBooksSyncLog;
use App\Models\User;
use App\Notifications\QuickBooksNeedsAttention;
use App\Services\QuickBooks\QuickBooksClient;
use App\Services\QuickBooks\QuickBooksException;
use App\Services\QuickBooks\TokenManager;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->superAdmin = User::factory()->superAdmin()->create();
});

function rotatedTokens(string $access = 'access-new', string $refresh = 'refresh-new'): array
{
    return ['access_token' => $access, 'refresh_token' => $refresh, 'expires_in' => 3600, 'x_refresh_token_expires_in' => 8_726_400];
}

function apiUrl(QuickBooksConnection $connection, string $path = '*'): string
{
    return "sandbox-quickbooks.api.intuit.com/v3/company/{$connection->realm_id}/{$path}";
}

describe('tokens', function () {
    it('uses a fresh access token as it is', function () {
        Http::fake();
        $connection = QuickBooksConnection::factory()->create(['access_token' => 'access-fresh']);

        expect(app(TokenManager::class)->accessToken($connection))->toBe('access-fresh');
        Http::assertNothingSent();
    });

    it('refreshes one about to run out, and saves the rotated refresh token', function () {
        $connection = QuickBooksConnection::factory()->expiring()->create(['refresh_token' => 'refresh-old']);
        Http::fake([TokenManager::TOKEN_URL => Http::response(rotatedTokens())]);

        expect(app(TokenManager::class)->accessToken($connection))->toBe('access-new');

        Http::assertSent(fn (Request $request): bool => $request['grant_type'] === 'refresh_token' && $request['refresh_token'] === 'refresh-old');
        expect($connection->fresh())
            ->refresh_token->toBe('refresh-new')
            ->access_expires_at->isAfter(now()->addMinutes(59))->toBeTrue();
    });

    it('doesn’t refresh again when another process already did', function () {
        $connection = QuickBooksConnection::factory()->expiring()->create();
        // Another worker refreshed after this one loaded the row.
        QuickBooksConnection::query()->whereKey($connection)->first()
            ->fill(['access_token' => 'access-theirs', 'access_expires_at' => now()->addHour()])->save();
        Http::fake();

        expect(app(TokenManager::class)->accessToken($connection))->toBe('access-theirs');
        Http::assertNothingSent();
    });

    it('stores the hard expiry when QuickBooks reports it', function () {
        $connection = QuickBooksConnection::factory()->create();
        Http::fake([TokenManager::TOKEN_URL => Http::response([...rotatedTokens(), 'x_refresh_token_hard_expires_in' => 86_400 * 1800])]);

        app(TokenManager::class)->refresh($connection, force: true);

        expect($connection->fresh()->refresh_hard_expires_at->isSameDay(now()->addDays(1800)))->toBeTrue();
    });

    it('disconnects and tells the super admins when QuickBooks refuses the refresh token', function () {
        $connection = QuickBooksConnection::factory()->expiring()->create();
        Http::fake([TokenManager::TOKEN_URL => Http::response(['error' => 'invalid_grant'], 400, ['intuit_tid' => 'tid-refused'])]);

        expect(fn () => app(TokenManager::class)->accessToken($connection))->toThrow(QuickBooksException::class, 'no longer accepts this connection');

        expect($connection->fresh())
            ->isConnected()->toBeFalse()
            ->refresh_token->toBeNull()
            ->disconnect_reason->toContain('invalid_grant');
        Notification::assertSentTo($this->superAdmin, QuickBooksNeedsAttention::class, fn ($notice): bool => $notice->title === 'QuickBooks was disconnected');
        // The failed refresh rolled back, but its log survives.
        expect(QuickBooksSyncLog::query()->where('intuit_tid', 'tid-refused')->value('status'))->toBe(400);
    });

    it('keeps the connection after any other refresh failure, and keeps its log', function () {
        $connection = QuickBooksConnection::factory()->expiring()->create();
        Http::fake([TokenManager::TOKEN_URL => Http::response(['error' => 'server_error'], 503, ['intuit_tid' => 'tid-503'])]);

        expect(fn () => app(TokenManager::class)->accessToken($connection))->toThrow(QuickBooksException::class);

        expect($connection->fresh()->isConnected())->toBeTrue()
            ->and(QuickBooksSyncLog::query()->where('intuit_tid', 'tid-503')->exists())->toBeTrue();
        Notification::assertNothingSent();
    });
});

describe('client', function () {
    it('pins the minor version and logs each call with its intuit_tid', function () {
        $connection = QuickBooksConnection::factory()->create(['access_token' => 'access-fresh']);
        Http::fake([apiUrl($connection) => Http::response(['Customer' => ['Id' => '7', 'DisplayName' => 'Jane']], 200, ['intuit_tid' => 'tid-read'])]);

        expect(QuickBooksClient::for($connection)->read('Customer', '7'))->toBe(['Id' => '7', 'DisplayName' => 'Jane']);

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/customer/7?minorversion='.QuickBooksClient::MINOR_VERSION)
            && $request->hasHeader('Authorization', 'Bearer access-fresh'));
        expect(QuickBooksSyncLog::query()->sole())
            ->method->toBe('GET')
            ->path->toBe('customer/7')
            ->status->toBe(200)
            ->intuit_tid->toBe('tid-read');
    });

    it('refreshes a rejected token and tries once more', function () {
        $connection = QuickBooksConnection::factory()->create(['access_token' => 'access-revoked']);
        Http::fake([
            TokenManager::TOKEN_URL => Http::response(rotatedTokens()),
            apiUrl($connection) => Http::sequence()
                ->push(['Fault' => ['Error' => [['Message' => 'AuthenticationFailed', 'code' => '3200']]]], 401)
                ->push(['CompanyInfo' => ['CompanyName' => 'Sandbox Company_US_1']]),
        ]);

        expect(QuickBooksClient::for($connection)->read('CompanyInfo', $connection->realm_id))->toBe(['CompanyName' => 'Sandbox Company_US_1']);

        Http::assertSentCount(3);
        expect($connection->fresh()->access_token)->toBe('access-new');
    });

    it('gives up after one retry', function () {
        $connection = QuickBooksConnection::factory()->create();
        Http::fake([
            TokenManager::TOKEN_URL => Http::response(rotatedTokens()),
            apiUrl($connection) => Http::response(['Fault' => ['Error' => [['Message' => 'AuthenticationFailed', 'code' => '3200']]]], 401),
        ]);

        expect(fn () => QuickBooksClient::for($connection)->read('Customer', '1'))->toThrow(QuickBooksException::class);
        Http::assertSentCount(3);
    });

    it('surfaces a 429 as rate-limited, without retrying', function () {
        $connection = QuickBooksConnection::factory()->create();
        Http::fake([apiUrl($connection) => Http::response(['Fault' => ['Error' => [['Message' => 'ThrottleExceeded', 'code' => '003001']]]], 429, ['intuit_tid' => 'tid-429'])]);

        try {
            QuickBooksClient::for($connection)->read('Customer', '1');
            $this->fail('Expected a rate-limited exception.');
        } catch (QuickBooksException $exception) {
            expect($exception->isRateLimited())->toBeTrue()
                ->and($exception->intuitTid)->toBe('tid-429')
                ->and($exception->getMessage())->toContain('ThrottleExceeded');
        }

        Http::assertSentCount(1);
    });

    it('pages through a query until a short page', function () {
        $connection = QuickBooksConnection::factory()->create();
        Http::fake([apiUrl($connection) => Http::sequence()
            ->push(['QueryResponse' => ['Item' => [['Id' => '1'], ['Id' => '2']]]])
            ->push(['QueryResponse' => ['Item' => [['Id' => '3']]]])]);

        $ids = QuickBooksClient::for($connection)->queryAll('Item', 'where Active = true', pageSize: 2)->pluck('Id')->all();

        expect($ids)->toBe(['1', '2', '3']);
        Http::assertSent(fn (Request $request): bool => str_contains(urldecode($request->url()), 'select * from Item where Active = true startposition 3 maxresults 2'));
    });

    it('won’t call QuickBooks without a connection', function () {
        Http::fake();
        $connection = QuickBooksConnection::factory()->disconnected()->create();

        expect(fn () => QuickBooksClient::for($connection)->read('Customer', '1'))->toThrow(QuickBooksException::class, 'isn’t connected');
        Http::assertNothingSent();
    });
});
