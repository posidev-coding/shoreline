<?php

use App\Enums\SyncIssueKind;
use App\Filament\Pages\QuickBooks;
use App\Jobs\ProcessQuickBooksWebhook;
use App\Jobs\PushQuickBooksCustomer;
use App\Jobs\SyncQuickBooksEntity;
use App\Jobs\SyncQuickBooksMerge;
use App\Models\CatalogItem;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Part;
use App\Models\ProjectLineItem;
use App\Models\QuickBooksConnection;
use App\Models\QuickBooksCustomer;
use App\Models\QuickBooksSyncIssue;
use App\Models\QuickBooksWebhookEvent;
use App\Models\User;
use App\Services\QuickBooks\EntitySync;
use App\Services\QuickBooks\QuickBooksException;
use App\Services\QuickBooks\TokenManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->connection = QuickBooksConnection::factory()->create(['realm_id' => '9130350000000001']);
});

/**
 * One CloudEvent, as Intuit sends it.
 *
 * @param  array<string, mixed>  $data
 * @return array<string, mixed>
 */
function cloudEvent(string $id, string $type, string $entityId, array $data = [], string $realm = '9130350000000001'): array
{
    return [
        'specversion' => '1.0', 'id' => $id, 'source' => 'intuit.abc', 'type' => $type,
        'datacontenttype' => 'application/json', 'time' => '2026-11-05T17:00:00Z',
        'intuitentityid' => $entityId, 'intuitaccountid' => $realm, 'data' => $data,
    ];
}

/**
 * Posts a delivery signed with the test verifier token, as Intuit would.
 *
 * @param  list<array<string, mixed>>|array<string, mixed>  $events
 */
function deliver(array $events, ?string $signature = null, string $contentType = 'application/json'): TestResponse
{
    $body = json_encode($events, JSON_THROW_ON_ERROR);

    return test()->call('POST', '/webhooks/quickbooks', server: [
        'CONTENT_TYPE' => $contentType,
        'HTTP_INTUIT_SIGNATURE' => $signature ?? base64_encode(hash_hmac('sha256', $body, 'test-verifier-token', true)),
    ], content: $body);
}

function webhookApi(string $path): string
{
    return 'sandbox-quickbooks.api.intuit.com/v3/company/9130350000000001/'.$path;
}

describe('the endpoint', function () {
    it('signs the way Intuit documents it', function () {
        // Hookdeck's published test vector for Intuit's scheme.
        $body = '[{"specversion":"1.0","id":"88cd52aa-33b6-4351-9aa4-47572edbd068","type":"qbo.account.created.v1","intuitentityid":"1234","intuitaccountid":"310687"}]';

        expect(base64_encode(hash_hmac('sha256', $body, 'quickbooks-test-verifier-token', true)))->toBe('PTs9BSwjYqOgFhLMBnVFCOlBZ+EC6WWqVDBTUoSfxH8=');
    });

    it('stores the connected company’s events and queues one job to pull them, whatever the content type', function (string $contentType) {
        Queue::fake();

        deliver([
            cloudEvent('evt-1', 'qbo.invoice.updated.v1', '20'),
            cloudEvent('evt-2', 'qbo.customer.created.v1', '41', realm: '1234567890'),
            cloudEvent('evt-3', 'qbo.salesreceipt.created.v1', '7'),
        ], contentType: $contentType)->assertNoContent(200);

        expect(QuickBooksWebhookEvent::query()->pluck('event_id')->all())->toBe(['evt-1', 'evt-3'])
            ->and(QuickBooksWebhookEvent::query()->where('event_id', 'evt-1')->sole())
            ->type->toBe('qbo.invoice.updated.v1')
            ->qb_id->toBe('20')
            ->realm_id->toBe('9130350000000001');
        Queue::assertPushed(ProcessQuickBooksWebhook::class, fn (ProcessQuickBooksWebhook $job): bool => count($job->eventIds) === 2);
    })->with(['application/json', 'application/cloudevents+json']);

    it('refuses a delivery Intuit didn’t sign', function (?string $signature) {
        Queue::fake();

        deliver([cloudEvent('evt-1', 'qbo.invoice.updated.v1', '20')], signature: $signature ?? '')->assertUnauthorized();

        expect(QuickBooksWebhookEvent::query()->exists())->toBeFalse();
        Queue::assertNothingPushed();
    })->with(['forged' => base64_encode(hash_hmac('sha256', 'something else', 'test-verifier-token', true)), 'missing' => null]);

    it('refuses everything while no verifier token is set', function () {
        config(['services.quickbooks.webhook_verifier_token' => null]);

        deliver([cloudEvent('evt-1', 'qbo.invoice.updated.v1', '20')])->assertUnauthorized();
    });

    it('takes a retried delivery without processing its events again', function () {
        Queue::fake();
        $events = [cloudEvent('evt-1', 'qbo.invoice.updated.v1', '20')];

        deliver($events)->assertOk();
        deliver($events)->assertOk();

        expect(QuickBooksWebhookEvent::query()->count())->toBe(1);
        Queue::assertPushed(ProcessQuickBooksWebhook::class, 1);
    });

    it('acknowledges a signed delivery it can’t read, and raises it', function () {
        deliver(['eventNotifications' => [['realmId' => '9130350000000001']]])->assertOk();

        expect(QuickBooksSyncIssue::query()->sole()->kind)->toBe(SyncIssueKind::WebhookUnreadable);
    });

    it('runs without a session, cookies, or CSRF, and with a rate limit', function () {
        Queue::fake();

        deliver([cloudEvent('evt-1', 'qbo.invoice.updated.v1', '20')])->assertOk()->assertCookieMissing(config('session.cookie'));

        // Filament adds its notification middleware to every route; it does nothing without a session.
        expect(Route::getRoutes()->getByName('webhooks.quickbooks')->gatherMiddleware())->toContain('throttle:webhooks')->not->toContain('web');
    });
});

describe('processing', function () {
    it('pulls each record the app follows, whatever happened to it, and follows a merge', function () {
        Queue::fake();
        $ids = collect([
            cloudEvent('evt-1', 'qbo.invoice.voided.v1', '20'),
            cloudEvent('evt-2', 'qbo.customer.merged.v1', '123', ['deletedid' => '27']),
            cloudEvent('evt-3', 'qbo.salesreceipt.created.v1', '7'),
            cloudEvent('evt-4', 'qbo.vendor.merged.v1', '5', ['deletedid' => '6']),
        ])->map(fn (array $event): int => QuickBooksWebhookEvent::query()->create([
            'event_id' => $event['id'], 'realm_id' => '9130350000000001', 'type' => $event['type'],
            'qb_id' => $event['intuitentityid'], 'payload' => $event, 'created_at' => now(),
        ])->getKey())->all();

        (new ProcessQuickBooksWebhook($ids))->handle();

        Queue::assertPushed(SyncQuickBooksEntity::class, 1);
        Queue::assertPushed(SyncQuickBooksEntity::class, fn (SyncQuickBooksEntity $job): bool => $job->entity === 'Invoice' && $job->qbId === '20');
        Queue::assertPushed(SyncQuickBooksMerge::class, 1);
        Queue::assertPushed(SyncQuickBooksMerge::class, fn (SyncQuickBooksMerge $job): bool => $job->entity === 'Customer' && $job->survivorQbId === '123' && $job->mergedQbId === '27');
    });

    it('keeps events for 30 days', function () {
        foreach (['old' => 31, 'new' => 29] as $eventId => $daysAgo) {
            QuickBooksWebhookEvent::query()->create(['event_id' => $eventId, 'realm_id' => '1', 'type' => 'qbo.invoice.updated.v1', 'payload' => [], 'created_at' => now()->subDays($daysAgo)]);
        }

        expect((new QuickBooksWebhookEvent)->prunable()->pluck('event_id')->all())->toBe(['old']);
    });
});

describe('merges', function () {
    beforeEach(function () {
        $this->notFound = Http::response(['Fault' => ['Error' => [['Message' => 'Object Not Found', 'code' => '610']]]], 400);
        // A moved client's first sync (tests/Feature/QuickBooks/CustomerSyncTest.php covers it).
        Queue::fake([PushQuickBooksCustomer::class]);
    });

    it('moves a customer’s client, documents, and sub-customers to the customer that’s left', function () {
        Http::fake([
            webhookApi('customer/27*') => $this->notFound,
            webhookApi('customer/123*') => Http::response(['Customer' => ['Id' => '123', 'SyncToken' => '4', 'DisplayName' => 'Pat Lee']]),
        ]);
        QuickBooksCustomer::factory()->create(['qb_id' => '27', 'display_name' => 'Patricia Lee']);
        QuickBooksCustomer::factory()->create(['qb_id' => '28', 'parent_qb_id' => '27']);
        $client = Client::factory()->create();
        $client->forceFill(['realm_id' => '9130350000000001', 'qb_id' => '27'])->save();
        $theirs = Invoice::factory()->create(['customer_qb_id' => '27', 'client_qb_id' => '27']);
        $subCustomers = Invoice::factory()->create(['customer_qb_id' => '28', 'client_qb_id' => '27']);

        app(EntitySync::class)->merge($this->connection, 'Customer', '123', '27');

        expect($client->refresh())->qb_id->toBe('123')->qb_sync_token->toBe('4')
            ->and($theirs->refresh())->customer_qb_id->toBe('123')->client_qb_id->toBe('123')
            ->and($subCustomers->refresh())->customer_qb_id->toBe('28')->client_qb_id->toBe('123')
            ->and(QuickBooksCustomer::query()->where('qb_id', '28')->sole()->parent_qb_id)->toBe('123')
            ->and(QuickBooksCustomer::query()->where('qb_id', '27')->exists())->toBeFalse()
            ->and(Activity::query()->where('event', 'quickbooks')->sole()->description)
            ->toBe('Moved to QuickBooks customer Pat Lee (123): Patricia Lee (27) was merged into it in QuickBooks')
            ->and(QuickBooksSyncIssue::query()->exists())->toBeFalse();
    });

    it('asks a person when the customer that’s left already has a client', function () {
        Http::fake([
            webhookApi('customer/27*') => $this->notFound,
            webhookApi('customer/123*') => Http::response(['Customer' => ['Id' => '123', 'SyncToken' => '4', 'DisplayName' => 'Pat Lee']]),
        ]);
        $merged = Client::factory()->create(['display_name' => 'Patricia Lee']);
        $merged->forceFill(['realm_id' => '9130350000000001', 'qb_id' => '27'])->save();
        $survivor = Client::factory()->create(['display_name' => 'Pat Lee']);
        $survivor->forceFill(['realm_id' => '9130350000000001', 'qb_id' => '123'])->save();

        app(EntitySync::class)->merge($this->connection, 'Customer', '123', '27');

        expect($merged->refresh()->qb_id)->toBeNull()
            ->and($survivor->refresh()->qb_id)->toBe('123')
            ->and(QuickBooksSyncIssue::query()->sole())
            ->kind->toBe(SyncIssueKind::CustomerDeleted)
            ->message->toContain('already linked to Pat Lee');
    });

    it('moves nothing when QuickBooks still has the record said to be merged away', function () {
        Http::fake([webhookApi('customer/*') => Http::response(['Customer' => ['Id' => '27', 'SyncToken' => '1', 'DisplayName' => 'Patricia Lee']])]);
        $client = Client::factory()->create();
        $client->forceFill(['realm_id' => '9130350000000001', 'qb_id' => '27'])->save();

        app(EntitySync::class)->merge($this->connection, 'Customer', '123', '27');

        expect($client->refresh()->qb_id)->toBe('27')
            ->and(QuickBooksSyncIssue::query()->sole()->kind)->toBe(SyncIssueKind::MergeNeedsReview);
    });

    it('bills parts and job lines as the item that’s left', function () {
        Http::fake([
            webhookApi('item/9*') => $this->notFound,
            webhookApi('item/3*') => Http::response(['Item' => ['Id' => '3', 'SyncToken' => '2', 'Name' => 'Springs', 'FullyQualifiedName' => 'Springs', 'Type' => 'NonInventory']]),
        ]);
        $merged = CatalogItem::factory()->create(['realm_id' => '9130350000000001', 'qb_id' => '9', 'fully_qualified_name' => 'Torsion springs']);
        $part = Part::factory()->create(['catalog_item_id' => $merged->getKey()]);
        $line = ProjectLineItem::factory()->create(['catalog_item_id' => $merged->getKey()]);

        app(EntitySync::class)->merge($this->connection, 'Item', '3', '9');

        $survivor = CatalogItem::query()->where('qb_id', '3')->sole();
        expect($part->refresh()->catalog_item_id)->toBe($survivor->getKey())
            ->and($line->refresh()->catalog_item_id)->toBe($survivor->getKey())
            ->and(CatalogItem::query()->where('qb_id', '9')->exists())->toBeFalse()
            ->and(Activity::query()->where('event', 'quickbooks')->sole()->description)
            ->toBe('QuickBooks item Torsion springs was merged into Springs: 1 part and 1 job line bill as it now');
    });

    it('asks a person when it keeps failing', function () {
        (new SyncQuickBooksMerge('Customer', '123', '27'))->failed(new QuickBooksException('QuickBooks answered 500: Down', 500, null, 'tid-7'));

        expect(QuickBooksSyncIssue::query()->sole())->kind->toBe(SyncIssueKind::MergeNeedsReview)->intuit_tid->toBe('tid-7');
    });
});

describe('the QuickBooks page', function () {
    beforeEach(function () {
        $this->actingAs(User::factory()->superAdmin()->withMfa()->create());
    });

    it('shows what the Intuit app’s settings ask for, and the last webhook', function () {
        QuickBooksWebhookEvent::query()->create(['event_id' => 'evt-1', 'realm_id' => '9130350000000001', 'type' => 'qbo.invoice.updated.v1', 'payload' => [], 'created_at' => now()]);

        Livewire::test(QuickBooks::class)
            ->assertSee(url('/webhooks/quickbooks'))
            ->assertSee(QuickBooks::getUrl(['disconnected' => 1]))
            ->assertSee(route('pages.privacy'))
            ->assertSee('Received');

        config(['services.quickbooks.webhook_verifier_token' => null]);
        Livewire::test(QuickBooks::class)->assertSee('QUICKBOOKS_WEBHOOK_VERIFIER_TOKEN isn’t set');
    });

    it('checks the connection when Intuit sends someone back after disconnecting the app', function () {
        Notification::fake();
        Http::fake([
            webhookApi('companyinfo/*') => Http::response([], 401),
            TokenManager::TOKEN_URL => Http::response(['error' => 'invalid_grant'], 400),
        ]);

        $this->get(QuickBooks::getUrl(['disconnected' => 1]))->assertOk();

        expect($this->connection->refresh()->isConnected())->toBeFalse();
    });
});
