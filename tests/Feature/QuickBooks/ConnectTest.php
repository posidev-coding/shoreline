<?php

use App\Filament\Pages\QuickBooks;
use App\Filament\Pages\QuickBooksCallback;
use App\Models\QuickBooksConnection;
use App\Models\QuickBooksSyncLog;
use App\Models\User;
use App\Services\QuickBooks\TokenManager;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->actingAs($this->admin = User::factory()->superAdmin()->withMfa()->create());
});

/**
 * Intuit's answers to a successful connection: tokens, then the company and its preferences.
 */
function fakeIntuitConnection(string $realm = '9130350000000001'): void
{
    Http::fake([
        TokenManager::TOKEN_URL => Http::response([
            'access_token' => 'access-1',
            'refresh_token' => 'refresh-1',
            'token_type' => 'bearer',
            'expires_in' => 3600,
            'x_refresh_token_expires_in' => 8_726_400,
        ], 200, ['intuit_tid' => 'tid-token']),
        "sandbox-quickbooks.api.intuit.com/v3/company/{$realm}/companyinfo/*" => Http::response(['CompanyInfo' => ['CompanyName' => 'Sandbox Company_US_1']], 200, ['intuit_tid' => 'tid-info']),
        "sandbox-quickbooks.api.intuit.com/v3/company/{$realm}/preferences*" => Http::response(['Preferences' => [
            'SalesFormsPrefs' => ['CustomTxnNumbers' => false],
            'TaxPrefs' => ['PartnerTaxEnabled' => true],
        ]]),
        // The item import that follows a connection.
        "sandbox-quickbooks.api.intuit.com/v3/company/{$realm}/query*" => Http::response(['QueryResponse' => []]),
    ]);
}

/**
 * Starts Connect on the page and returns the state it put in the session.
 */
function startConnecting(): string
{
    Livewire::test(QuickBooks::class)->callAction(TestAction::make('connect'));

    return (string) session(QuickBooks::STATE_SESSION_KEY);
}

it('sends a super admin to Intuit to sign in, with a state to check on the way back', function () {
    $page = Livewire::test(QuickBooks::class)->callAction(TestAction::make('connect'));

    $state = session(QuickBooks::STATE_SESSION_KEY);
    expect($state)->toBeString()->toHaveLength(40);

    $page->assertRedirect(TokenManager::AUTHORIZE_URL.'?'.http_build_query([
        'client_id' => 'test-client-id',
        'response_type' => 'code',
        'scope' => 'com.intuit.quickbooks.accounting',
        'redirect_uri' => url('/admin/quickbooks/callback'),
        'state' => $state,
    ]));
});

it('connects on the way back: trades the code for tokens, stores them encrypted, and loads the company', function () {
    fakeIntuitConnection();
    $state = startConnecting();

    $this->get('/admin/quickbooks/callback?'.http_build_query(['code' => 'auth-code', 'realmId' => '9130350000000001', 'state' => $state]))
        ->assertRedirect(QuickBooks::getUrl());

    Http::assertSent(fn (Request $request): bool => $request->url() === TokenManager::TOKEN_URL
        && $request->hasHeader('Authorization', 'Basic '.base64_encode('test-client-id:test-client-secret'))
        && $request->hasHeader('x-include-refresh-token-hard-expires-in', 'true')
        && $request['grant_type'] === 'authorization_code'
        && $request['code'] === 'auth-code'
        && $request['redirect_uri'] === url('/admin/quickbooks/callback'));

    $connection = QuickBooksConnection::current();
    expect($connection)
        ->realm_id->toBe('9130350000000001')
        ->company_name->toBe('Sandbox Company_US_1')
        ->access_token->toBe('access-1')
        ->refresh_token->toBe('refresh-1')
        ->connected_by->toBe($this->admin->getKey())
        ->and($connection->access_expires_at->between(now()->addMinutes(59), now()->addHour()))->toBeTrue()
        ->and($connection->refresh_expires_at->isSameDay(now()->addDays(101)))->toBeTrue()
        ->and(data_get($connection->preferences, 'TaxPrefs.PartnerTaxEnabled'))->toBeTrue()
        ->and(DB::table('quickbooks_connections')->value('refresh_token'))->not->toContain('refresh-1')
        ->and(QuickBooksSyncLog::query()->where('intuit_tid', 'tid-token')->value('detail'))->toBe('Fields: access_token, refresh_token, token_type, expires_in, x_refresh_token_expires_in')
        ->and(Activity::query()->where('event', 'quickbooks')->value('description'))->toBe('Connected QuickBooks (Sandbox) company 9130350000000001');
});

it('refuses a callback whose state this session didn’t send, without spending the code', function (?string $state) {
    Http::fake();
    startConnecting();

    $this->get('/admin/quickbooks/callback?'.http_build_query(array_filter(['code' => 'auth-code', 'realmId' => '1', 'state' => $state])))
        ->assertRedirect(QuickBooks::getUrl());

    Http::assertNothingSent();
    expect(QuickBooksConnection::query()->exists())->toBeFalse()
        ->and(session(QuickBooks::STATE_SESSION_KEY))->toBeNull();
})->with(['someone else’s' => 'not-the-state', 'missing' => null]);

it('says so when access is declined at Intuit', function () {
    Http::fake();
    $state = startConnecting();

    $this->get('/admin/quickbooks/callback?'.http_build_query(['error' => 'access_denied', 'state' => $state]))
        ->assertRedirect(QuickBooks::getUrl());

    Http::assertNothingSent();
    expect(QuickBooksConnection::query()->exists())->toBeFalse();
});

it('refuses a second company until the first is forgotten', function () {
    QuickBooksConnection::factory()->disconnected()->create(['realm_id' => '111', 'company_name' => 'Old Books']);
    fakeIntuitConnection('222');

    $this->get('/admin/quickbooks/callback?'.http_build_query(['code' => 'auth-code', 'realmId' => '222', 'state' => startConnecting()]));

    Http::assertNothingSent();
    expect(QuickBooksConnection::query()->sole()->realm_id)->toBe('111');

    Livewire::test(QuickBooks::class)->callAction(TestAction::make('forget'));
    $this->get('/admin/quickbooks/callback?'.http_build_query(['code' => 'auth-code', 'realmId' => '222', 'state' => startConnecting()]));

    expect(QuickBooksConnection::query()->sole())->realm_id->toBe('222')->isConnected()->toBeTrue();
});

it('reconnects the same company in place', function () {
    $old = QuickBooksConnection::factory()->disconnected()->create(['realm_id' => '9130350000000001']);
    fakeIntuitConnection();

    $this->get('/admin/quickbooks/callback?'.http_build_query(['code' => 'auth-code', 'realmId' => '9130350000000001', 'state' => startConnecting()]));

    expect(QuickBooksConnection::query()->sole())
        ->getKey()->toBe($old->getKey())
        ->isConnected()->toBeTrue()
        ->disconnect_reason->toBeNull();
});

it('disconnects: revokes the token with Intuit and forgets it here, even if the revoke fails', function (int $revokeStatus) {
    $connection = QuickBooksConnection::factory()->create(['refresh_token' => 'refresh-x']);
    Http::fake([TokenManager::REVOKE_URL => Http::response(null, $revokeStatus)]);

    Livewire::test(QuickBooks::class)->callAction(TestAction::make('disconnect'));

    Http::assertSent(fn (Request $request): bool => $request->url() === TokenManager::REVOKE_URL && $request['token'] === 'refresh-x');
    expect($connection->refresh())
        ->isConnected()->toBeFalse()
        ->refresh_token->toBeNull()
        ->disconnect_reason->toBe("Disconnected by {$this->admin->email}");
})->with(['revoked' => 200, 'revoke failed' => 500]);

it('is for super admins only', function () {
    $this->actingAs(User::factory()->owner()->withMfa()->create());

    $this->get(QuickBooks::getUrl())->assertForbidden();
    $this->get(QuickBooksCallback::getUrl())->assertForbidden();
});

it('says what’s missing when the Intuit keys aren’t set', function () {
    config(['services.quickbooks.client_id' => null]);

    Livewire::test(QuickBooks::class)
        ->assertSee('QUICKBOOKS_CLIENT_ID and QUICKBOOKS_CLIENT_SECRET aren’t set')
        ->assertActionHidden(TestAction::make('connect'));
});
