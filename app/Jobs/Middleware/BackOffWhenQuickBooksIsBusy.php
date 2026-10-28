<?php

namespace App\Jobs\Middleware;

use App\Services\QuickBooks\QuickBooksException;
use Closure;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * When QuickBooks answers 429 (too many requests), puts the job back for a minute instead of
 * failing it. Every QuickBooks job uses it.
 */
class BackOffWhenQuickBooksIsBusy
{
    public const int RETRY_AFTER_SECONDS = 60;

    /**
     * @param  Closure(object): void  $next
     */
    public function handle(object $job, Closure $next): void
    {
        try {
            $next($job);
        } catch (QuickBooksException $exception) {
            if (! $exception->isRateLimited() || ! $job instanceof ShouldQueue || ! method_exists($job, 'release')) {
                throw $exception;
            }

            $job->release(self::RETRY_AFTER_SECONDS);
        }
    }
}
