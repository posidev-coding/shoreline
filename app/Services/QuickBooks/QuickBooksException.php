<?php

namespace App\Services\QuickBooks;

use App\Models\QuickBooksConnection;
use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * Anything QuickBooks refused or couldn't do, worded for the person who'll read it in the admin.
 * Keeps Intuit's error code and intuit_tid for a support case.
 */
final class QuickBooksException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $status = null,
        public readonly ?string $intuitCode = null,
        public readonly ?string $intuitTid = null,
    ) {
        parent::__construct($message);
    }

    public static function notConfigured(): self
    {
        return new self('QuickBooks isn’t set up: QUICKBOOKS_CLIENT_ID and QUICKBOOKS_CLIENT_SECRET are missing.');
    }

    public static function notConnected(): self
    {
        return new self('QuickBooks isn’t connected. A super admin can connect it from Settings → QuickBooks.');
    }

    public static function refused(): self
    {
        return new self('QuickBooks no longer accepts this connection, so it’s been disconnected. Connect it again from Settings → QuickBooks.');
    }

    public static function differentCompany(QuickBooksConnection $existing): self
    {
        $name = $existing->company_name ?? "company {$existing->realm_id}";

        return new self("This app is linked to {$name}. Forget that company on the QuickBooks page before connecting another one.");
    }

    public static function unreachable(string $reason): self
    {
        return new self("Couldn’t reach QuickBooks: {$reason}");
    }

    public static function fromResponse(Response $response): self
    {
        $error = $response->json('Fault.Error.0');
        $message = is_array($error)
            ? trim(($error['Message'] ?? '').'. '.($error['Detail'] ?? ''), '. ')
            : ($response->json('error_description') ?? $response->json('error') ?? $response->reason());

        return new self(
            'QuickBooks answered '.$response->status().': '.$message,
            $response->status(),
            is_array($error) ? (string) ($error['code'] ?? '') : $response->json('error'),
            $response->header('intuit_tid') ?: null,
        );
    }

    /**
     * Intuit's 610, Object Not Found: the record was deleted (or merged away) in QuickBooks.
     */
    public function isNotFound(): bool
    {
        return $this->intuitCode === '610';
    }

    /**
     * Too many requests: the job should wait and try again.
     */
    public function isRateLimited(): bool
    {
        return $this->status === 429;
    }
}
