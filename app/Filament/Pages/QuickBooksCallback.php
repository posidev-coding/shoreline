<?php

namespace App\Filament\Pages;

use App\Jobs\ImportQuickBooksCustomers;
use App\Jobs\ImportQuickBooksItems;
use App\Models\User;
use App\Services\QuickBooks\QuickBooksClient;
use App\Services\QuickBooks\QuickBooksException;
use App\Services\QuickBooks\TokenManager;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Http\Request;

/**
 * Where Intuit sends the super admin back after they sign in to QuickBooks. A panel page, so it
 * gets the admin's sign-in and MFA; it finishes connecting and goes back to the QuickBooks page.
 * The state must match the one this session sent, so nobody can send a stranger here with their
 * own company's code.
 */
class QuickBooksCallback extends Page
{
    protected static ?string $slug = 'quickbooks/callback';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Connecting QuickBooks';

    public static function canAccess(): bool
    {
        return QuickBooks::canAccess();
    }

    /**
     * Must match one of the Intuit app's Redirect URIs exactly.
     */
    public static function redirectUri(): string
    {
        return self::getUrl(panel: 'admin');
    }

    public function mount(Request $request, TokenManager $tokens): void
    {
        $expected = session()->pull(QuickBooks::STATE_SESSION_KEY);
        $state = (string) $request->query('state', '');

        if (! is_string($expected) || $state === '' || ! hash_equals($expected, $state)) {
            $this->finish(Notification::make()->danger()->title('That QuickBooks sign-in didn’t start from this page. Connect again from here.'));

            return;
        }

        if ($request->filled('error')) {
            $this->finish(Notification::make()->warning()->title($request->query('error') === 'access_denied'
                ? 'QuickBooks wasn’t connected: access was declined.'
                : 'QuickBooks wasn’t connected: '.$request->query('error')));

            return;
        }

        if (! $request->filled('code') || ! $request->filled('realmId')) {
            $this->finish(Notification::make()->danger()->title('QuickBooks came back without a company. Connect again from here.'));

            return;
        }

        /** @var User $user */
        $user = auth()->user();

        try {
            $connection = $tokens->connect((string) $request->query('code'), (string) $request->query('realmId'), self::redirectUri(), $user);
        } catch (QuickBooksException $exception) {
            $this->finish(Notification::make()->danger()->title('QuickBooks wasn’t connected')->body($exception->getMessage())->persistent());

            return;
        }

        // Its items, so parts and job lines have something to bill as, and its customers to match to clients.
        ImportQuickBooksItems::dispatch($user);
        ImportQuickBooksCustomers::dispatch($user);

        try {
            $company = QuickBooksClient::for($connection)->refreshCompanyDetails()->company_name;
            $this->finish(Notification::make()->success()->title("Connected to {$company}")->body('Its items and customers are importing now. The bell says when they’re in.'));
        } catch (QuickBooksException $exception) {
            $this->finish(Notification::make()->warning()->title('Connected, but the company’s details didn’t load')->body($exception->getMessage().' Try Check the connection.')->persistent());
        }
    }

    private function finish(Notification $notification): void
    {
        $notification->send();
        $this->redirect(QuickBooks::getUrl());
    }
}
