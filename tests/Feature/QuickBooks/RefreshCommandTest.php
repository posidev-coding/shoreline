<?php

use App\Console\Commands\RefreshQuickBooks;
use App\Models\QuickBooksConnection;
use App\Models\User;
use App\Notifications\QuickBooksNeedsAttention;
use App\Services\QuickBooks\TokenManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->superAdmin = User::factory()->superAdmin()->create();
});

function fakeDailyCheck(QuickBooksConnection $connection, array $tokens = []): void
{
    Http::fake([
        TokenManager::TOKEN_URL => Http::response([...['access_token' => 'access-new', 'refresh_token' => 'refresh-new', 'expires_in' => 3600, 'x_refresh_token_expires_in' => 8_726_400], ...$tokens]),
        "sandbox-quickbooks.api.intuit.com/v3/company/{$connection->realm_id}/companyinfo/*" => Http::response(['CompanyInfo' => ['CompanyName' => 'Renamed Books']]),
        "sandbox-quickbooks.api.intuit.com/v3/company/{$connection->realm_id}/preferences*" => Http::response(['Preferences' => []]),
    ]);
}

it('refreshes the tokens every day, even fresh ones, and checks the company', function () {
    $connection = QuickBooksConnection::factory()->create(['refresh_token' => 'refresh-old']);
    fakeDailyCheck($connection);

    $this->artisan('app:quickbooks-refresh')->assertSuccessful()->expectsOutputToContain('connected to Renamed Books');

    expect($connection->fresh())->refresh_token->toBe('refresh-new')->company_name->toBe('Renamed Books');
    Notification::assertNothingSent();
});

it('reminds the super admins as the five-year limit nears', function (int $daysLeft, bool $reminds) {
    $connection = QuickBooksConnection::factory()->create();
    fakeDailyCheck($connection, ['x_refresh_token_hard_expires_in' => 86_400 * $daysLeft]);

    $this->artisan('app:quickbooks-refresh')->assertSuccessful();

    $reminds
        ? Notification::assertSentTo($this->superAdmin, QuickBooksNeedsAttention::class, fn ($notice): bool => str_contains($notice->title, "within {$daysLeft} day"))
        : Notification::assertNothingSent();
})->with([
    '60 days' => [60, true],
    '59 days' => [59, false],
    '1 day' => [1, true],
]);

it('does nothing when QuickBooks isn’t connected', function () {
    Http::fake();
    QuickBooksConnection::factory()->disconnected()->create();

    $this->artisan('app:quickbooks-refresh')->assertSuccessful();

    Http::assertNothingSent();
});

it('fails loudly when QuickBooks refuses, having told the super admins', function () {
    $connection = QuickBooksConnection::factory()->create();
    Http::fake([TokenManager::TOKEN_URL => Http::response(['error' => 'invalid_grant'], 400)]);

    $this->artisan('app:quickbooks-refresh')->assertFailed();

    Notification::assertSentTo($this->superAdmin, QuickBooksNeedsAttention::class);
});

it('is on the daily schedule', function () {
    $this->artisan('schedule:list')->expectsOutputToContain('app:quickbooks-refresh');
    expect(RefreshQuickBooks::WARN_DAYS_BEFORE)->toContain(60);
});
