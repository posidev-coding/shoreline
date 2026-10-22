<?php

namespace App\Jobs;

use App\Mail\CloudSmokeMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Proves a queue worker picks up jobs (and, optionally, can send mail) for `app:cloud-smoke`,
 * which polls the cache for the result this job writes.
 */
class CloudSmokeJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public string $runId,
        public ?string $mailTo = null,
    ) {}

    public static function cacheKey(string $runId): string
    {
        return "cloud-smoke:{$runId}";
    }

    public function handle(): void
    {
        $mailError = null;

        if ($this->mailTo !== null) {
            try {
                Mail::to($this->mailTo)->send(new CloudSmokeMail($this->runId));
            } catch (Throwable $e) {
                $mailError = $e::class.': '.$e->getMessage();
            }
        }

        Cache::put(self::cacheKey($this->runId), [
            'host' => gethostname() ?: 'unknown host',
            'mail_error' => $mailError,
        ], now()->addMinutes(10));
    }
}
