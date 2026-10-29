<?php

namespace App\Services\QuickBooks;

use App\Models\QuickBooksConnection;
use App\Models\QuickBooksSyncLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\LazyCollection;

/**
 * A thin wrapper on Laravel's HTTP client for one connected QuickBooks company, pinned to a minor
 * version. A rejected access token is refreshed and the request tried once more; a 429 surfaces
 * as a rate-limited QuickBooksException for the job to back off. Every call is logged with its
 * intuit_tid.
 */
final class QuickBooksClient
{
    public const string MINOR_VERSION = '75';

    /**
     * QUERY returns at most 1,000 rows a page.
     */
    public const int MAX_PAGE_SIZE = 1000;

    public function __construct(private TokenManager $tokens, private QuickBooksConnection $connection) {}

    public static function for(QuickBooksConnection $connection): self
    {
        return new self(app(TokenManager::class), $connection);
    }

    /**
     * @param  array<string, scalar>  $query
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        return $this->send('GET', $path, $query);
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, scalar>  $query
     * @return array<string, mixed>
     */
    public function post(string $path, array $body, array $query = []): array
    {
        return $this->send('POST', $path, $query, $body);
    }

    /**
     * One record, as QuickBooks returns it under its entity name.
     *
     * @return array<string, mixed>
     */
    public function read(string $entity, string $id): array
    {
        return (array) ($this->get(strtolower($entity).'/'.rawurlencode($id))[$entity] ?? []);
    }

    /**
     * Every record a query matches, a page at a time, as the caller walks through them.
     *
     * @return LazyCollection<int, array<string, mixed>>
     */
    public function queryAll(string $entity, string $where = '', int $pageSize = self::MAX_PAGE_SIZE): LazyCollection
    {
        return LazyCollection::make(function () use ($entity, $where, $pageSize) {
            $start = 1;

            do {
                $rows = $this->queryPage($entity, $where, $start, $pageSize);

                foreach ($rows as $row) {
                    yield $row;
                }

                $start += $pageSize;
            } while (count($rows) === $pageSize);
        });
    }

    /**
     * One page of a query, for a job that takes a page at a time. Positions start at 1.
     *
     * @return list<array<string, mixed>>
     */
    public function queryPage(string $entity, string $where, int $start, int $pageSize): array
    {
        $statement = trim("select * from {$entity} {$where}")." startposition {$start} maxresults {$pageSize}";
        $rows = (array) ($this->get('query', ['query' => $statement])['QueryResponse'][$entity] ?? []);

        return array_values(array_map(fn (mixed $row): array => (array) $row, $rows));
    }

    /**
     * Refreshes the company's name and preferences on the connection, which also proves the
     * connection works.
     */
    public function refreshCompanyDetails(): QuickBooksConnection
    {
        $info = $this->read('CompanyInfo', $this->connection->realm_id);
        $preferences = (array) ($this->get('preferences')['Preferences'] ?? []);

        $this->connection->fill([
            'company_name' => $info['CompanyName'] ?? $this->connection->company_name,
            'preferences' => $preferences,
            'preferences_checked_at' => now(),
        ])->save();

        return $this->connection;
    }

    /**
     * @param  array<string, scalar>  $query
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>
     */
    private function send(string $method, string $path, array $query, ?array $body = null, bool $isRetry = false): array
    {
        $token = $this->tokens->accessToken($this->connection);
        $response = $this->request($method, $path, $query, $body, $token);

        if ($response->status() === 401 && ! $isRetry) {
            $this->tokens->refresh($this->connection, rejected: $token);

            return $this->send($method, $path, $query, $body, isRetry: true);
        }

        if ($response->failed()) {
            throw QuickBooksException::fromResponse($response);
        }

        return (array) $response->json();
    }

    /**
     * @param  array<string, scalar>  $query
     * @param  array<string, mixed>|null  $body
     */
    private function request(string $method, string $path, array $query, ?array $body, string $token): Response
    {
        $url = $this->connection->environment->apiBaseUrl().$this->connection->realm_id.'/'.ltrim($path, '/');
        $started = hrtime(true);

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(30)
                ->withQueryParameters([...$query, 'minorversion' => self::MINOR_VERSION])
                ->send($method, $url, $body === null ? [] : ['json' => $body]);
        } catch (ConnectionException $exception) {
            $this->log($method, $path, $query, null, $started, $exception->getMessage());

            throw QuickBooksException::unreachable($exception->getMessage());
        }

        $this->log($method, $path, $query, $response, $started, $response->failed() ? QuickBooksException::fromResponse($response)->getMessage() : null);

        return $response;
    }

    /**
     * @param  array<string, scalar>  $query
     */
    private function log(string $method, string $path, array $query, ?Response $response, int $started, ?string $detail): void
    {
        QuickBooksSyncLog::query()->create([
            'quickbooks_connection_id' => $this->connection->getKey(),
            'method' => $method,
            // A query's statement says what was asked; it holds no secrets.
            'path' => mb_substr(ltrim($path, '/').(isset($query['query']) ? ': '.$query['query'] : ''), 0, 255),
            'status' => $response?->status(),
            'intuit_tid' => $response?->header('intuit_tid') ?: null,
            'duration_ms' => intdiv(hrtime(true) - $started, 1_000_000),
            'detail' => $detail,
            'created_at' => now(),
        ]);
    }
}
