<?php

namespace App\Console\Commands;

use App\Models\QuickBooksConnection;
use App\Notifications\QuickBooksNeedsAttention;
use App\Services\QuickBooks\QuickBooksClient;
use App\Services\QuickBooks\QuickBooksException;
use App\Services\QuickBooks\TokenManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * The daily QuickBooks check. Refreshing every day keeps the refresh token from lapsing after 100
 * days unused and saves each rotated one; reading the company's details proves the connection
 * still works. Super admins hear when the refresh token's five-year limit is close.
 */
#[Signature('app:quickbooks-refresh')]
#[Description('Refresh the QuickBooks tokens and check the connection')]
class RefreshQuickBooks extends Command
{
    /**
     * Days before the refresh token stops for good on which super admins are reminded. The
     * command runs daily, so each lands once.
     */
    public const array WARN_DAYS_BEFORE = [60, 30, 14, 7, 3, 1];

    public function handle(TokenManager $tokens): int
    {
        $connection = QuickBooksConnection::current();

        if ($connection === null || ! $connection->isConnected()) {
            $this->info('QuickBooks isn’t connected; nothing to refresh.');

            return self::SUCCESS;
        }

        try {
            $tokens->refresh($connection, force: true);
            QuickBooksClient::for($connection)->refreshCompanyDetails();
        } catch (QuickBooksException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->warnBeforeHardExpiry($connection);
        $this->info("QuickBooks is connected to {$connection->company_name}.");

        return self::SUCCESS;
    }

    private function warnBeforeHardExpiry(QuickBooksConnection $connection): void
    {
        $expires = $connection->refresh_hard_expires_at;

        if ($expires === null) {
            return;
        }

        $daysLeft = (int) ceil(now()->diffInDays($expires, absolute: false));

        if (in_array($daysLeft, self::WARN_DAYS_BEFORE, true)) {
            Notification::send(TokenManager::superAdmins(), new QuickBooksNeedsAttention(
                "QuickBooks needs reconnecting within {$daysLeft} ".str('day')->plural($daysLeft),
                'QuickBooks limits how long one connection lasts. Before '.$expires->timezone(config('app.business_timezone'))->format('M j, Y').', disconnect and connect again from the QuickBooks page, or syncing stops.',
            ));
        }
    }
}
