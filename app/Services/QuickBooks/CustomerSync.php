<?php

namespace App\Services\QuickBooks;

use App\Enums\CustomerSyncState;
use App\Enums\SyncIssueKind;
use App\Jobs\PushQuickBooksCustomer;
use App\Models\Client;
use App\Models\QuickBooksConnection;
use App\Models\QuickBooksCustomer;
use App\Models\User;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Context;
use InvalidArgumentException;

/**
 * Keeps a linked client and its QuickBooks customer in step, both ways, field by field
 * (CustomerFields), against the copy both sides last agreed on (clients.qb_synced_fields).
 *
 * A field changed on one side takes that change. Changed on both, QuickBooks wins, except the
 * phone, where the newer edit wins; either way a sync issue says what was dropped. The first sync
 * after linking has nothing agreed yet: QuickBooks wins wherever it has a value, and the client
 * fills its blanks. Pushes and pulls of one client take turns under a lock.
 */
final class CustomerSync
{
    /**
     * Set (as hidden Context) while QuickBooks' values are written to a client, so that save
     * isn't sent straight back.
     */
    public const string INBOUND = 'quickbooks.inbound';

    private const int MAX_PUSH_ATTEMPTS = 3;

    private const int MAX_NAME_SUFFIX = 5;

    public function __construct(private CustomerImporter $customers, private CustomerLinker $linker) {}

    /**
     * QuickBooks' copy of a linked client's customer just came in (a pull, the hourly sweep, a
     * webhook, an import page). Its changes go into the client, and whatever the client has that
     * QuickBooks doesn't is queued to go back.
     *
     * @param  array<string, mixed>  $customer
     */
    public function pulled(Client $client, array $customer): void
    {
        $needsPush = $this->locked($client, function () use ($client, $customer): bool {
            $client->refresh();

            if ($client->qb_id !== (string) $customer['Id']) {
                return false;
            }

            $theirs = CustomerFields::fromQuickBooks($customer);
            [$merged, $conflicts] = $this->merge($client, $theirs, QuickBooksRecord::updatedAt($customer));
            $merged = $this->apply($client, $merged);
            $needsPush = ! CustomerFields::same($merged, $theirs);

            $client->forceFill([
                'qb_synced_fields' => $theirs,
                'qb_sync_token' => $customer['SyncToken'] ?? $client->qb_sync_token,
                'qb_last_updated_at' => QuickBooksRecord::updatedAt($customer),
                'qb_sync_state' => $needsPush ? CustomerSyncState::Pending : CustomerSyncState::Clean,
            ]);
            self::inbound(fn () => $client->save());
            $this->raiseConflicts($client, $conflicts);

            return $needsPush;
        });

        if ($needsPush) {
            PushQuickBooksCustomer::dispatch($client->getKey());
        }
    }

    /**
     * Sends a linked client's changes to its QuickBooks customer, reading the customer first for
     * its SyncToken and anything changed there. A stale SyncToken (5010) reads and tries again; a
     * name QuickBooks already has keeps QuickBooks' name and raises an issue.
     */
    public function push(QuickBooksConnection $connection, Client $client): void
    {
        $this->locked($client, function () use ($connection, $client): void {
            $client->refresh();

            if ($client->qb_id === null) {
                return;
            }

            $api = QuickBooksClient::for($connection);
            $conflicts = [];
            $updated = null;

            for ($attempt = 1; $updated === null; $attempt++) {
                try {
                    $remote = $api->read('Customer', $client->qb_id);
                } catch (QuickBooksException $exception) {
                    if ($exception->isNotFound()) {
                        app(EntitySync::class)->apply($connection, 'Customer', ['Id' => $client->qb_id, 'status' => 'Deleted']);

                        return;
                    }

                    throw $exception;
                }

                $theirs = CustomerFields::fromQuickBooks($remote);
                [$merged, $conflicts] = $this->merge($client, $theirs, QuickBooksRecord::updatedAt($remote));
                $merged = $this->apply($client, $merged);
                $changed = array_values(array_filter(CustomerFields::FIELDS, fn (string $field): bool => $merged[$field] !== $theirs[$field]));

                if ($changed === []) {
                    $updated = $remote;

                    break;
                }

                try {
                    $updated = (array) ($api->post('customer', [
                        'Id' => $remote['Id'],
                        'SyncToken' => $remote['SyncToken'],
                        'sparse' => true,
                        ...CustomerFields::toQuickBooks($merged, $changed, $remote),
                    ])['Customer'] ?? []);
                } catch (QuickBooksException $exception) {
                    if ($attempt >= self::MAX_PUSH_ATTEMPTS) {
                        throw $exception;
                    }

                    if ($exception->isDuplicateName() && in_array('display_name', $changed, true)) {
                        SyncIssues::raise(SyncIssueKind::NameTaken, "QuickBooks already has a customer, vendor, or employee named “{$merged['display_name']}”, so {$client->display_name} keeps the name “{$theirs['display_name']}” in both places.", 'Customer', $client->qb_id);
                        $client->display_name = (string) $theirs['display_name'];
                    } elseif (! $exception->isStale()) {
                        throw $exception;
                    }
                }
            }

            $this->settle($client, $updated);
            $this->customers->mirror($connection->realm_id, $updated);
            $this->raiseConflicts($client, $conflicts);
            SyncIssues::resolveFor('Customer', (string) $client->qb_id, SyncIssueKind::PushFailed);
        });
    }

    /**
     * Makes the client a new QuickBooks customer and links them. A name QuickBooks already has
     * gets " (2)", " (3)"... and an issue saying so.
     */
    public function create(QuickBooksConnection $connection, Client $client, User $by): QuickBooksCustomer
    {
        if ($client->qb_id !== null) {
            throw new InvalidArgumentException("{$client->display_name} is already linked to a QuickBooks customer.");
        }

        $fields = CustomerFields::fromClient($client);
        $api = QuickBooksClient::for($connection);
        $created = [];

        for ($n = 1; $created === []; $n++) {
            $name = $n === 1 ? (string) $fields['display_name'] : "{$fields['display_name']} ({$n})";

            try {
                $created = (array) ($api->post('customer', CustomerFields::toQuickBooks([...$fields, 'display_name' => $name]))['Customer'] ?? []);
            } catch (QuickBooksException $exception) {
                if (! $exception->isDuplicateName() || $n >= self::MAX_NAME_SUFFIX) {
                    throw $exception;
                }
            }
        }

        $customer = $this->customers->mirror($connection->realm_id, $created);
        $this->linker->added($customer, $client, $by);
        $this->locked($client, fn () => $this->settle($client, $created));

        if ($customer->display_name !== $fields['display_name']) {
            SyncIssues::raise(SyncIssueKind::NameTaken, "QuickBooks already had a customer, vendor, or employee named “{$fields['display_name']}”, so the client was added as “{$customer->display_name}”. Rename either one in QuickBooks if that’s not right.", 'Customer', $customer->qb_id);
        }

        return $customer;
    }

    /**
     * Writes values to the client as coming from QuickBooks: its save isn't sent back.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function inbound(Closure $callback): mixed
    {
        return Context::scope($callback, hidden: [self::INBOUND => true]);
    }

    public static function isInbound(): bool
    {
        return Context::hasHidden(self::INBOUND);
    }

    /**
     * QuickBooks' copy after a push or a create is what both sides agree on now: it goes into the
     * client, and becomes the agreed copy.
     *
     * @param  array<string, mixed>  $customer
     */
    private function settle(Client $client, array $customer): void
    {
        $fields = $this->apply($client, CustomerFields::fromQuickBooks($customer));

        $client->forceFill([
            'qb_synced_fields' => $fields,
            'qb_sync_token' => $customer['SyncToken'] ?? $client->qb_sync_token,
            'qb_last_updated_at' => QuickBooksRecord::updatedAt($customer),
            'qb_sync_state' => CustomerSyncState::Clean,
        ]);
        self::inbound(fn () => $client->save());
    }

    /**
     * Each field's value once both sides' changes are counted, and the fields both sides
     * changed differently.
     *
     * @param  array<string, string|null>  $theirs
     * @return array{0: array<string, string|null>, 1: list<array{field: string, kept: string|null, dropped: string|null, keptFrom: string}>}
     */
    private function merge(Client $client, array $theirs, ?CarbonInterface $theirsUpdatedAt): array
    {
        $mine = CustomerFields::fromClient($client);
        $agreed = $client->qb_synced_fields;
        $merged = [];
        $conflicts = [];

        foreach (CustomerFields::FIELDS as $field) {
            if ($agreed === null) {
                $merged[$field] = $theirs[$field] ?? $mine[$field];

                continue;
            }

            $was = $agreed[$field] ?? null;

            if ($mine[$field] === $theirs[$field] || $mine[$field] === $was) {
                $merged[$field] = $theirs[$field];
            } elseif ($theirs[$field] === $was) {
                $merged[$field] = $mine[$field];
            } else {
                $appIsNewer = in_array($field, CustomerFields::SHARED, true)
                    && ($theirsUpdatedAt === null || $client->updated_at?->gt($theirsUpdatedAt));
                $merged[$field] = $appIsNewer ? $mine[$field] : $theirs[$field];
                $conflicts[] = [
                    'field' => $field,
                    'kept' => $merged[$field],
                    'dropped' => $appIsNewer ? $theirs[$field] : $mine[$field],
                    'keptFrom' => $appIsNewer ? 'the app’s, the newer edit' : 'QuickBooks’',
                ];
            }
        }

        return [$merged, $conflicts];
    }

    /**
     * Puts the fields on the client, unsaved, where they differ. A name another client has stays
     * as it was, with an issue. Returns the fields as the client now has them.
     *
     * @param  array<string, string|null>  $fields
     * @return array<string, string|null>
     */
    private function apply(Client $client, array $fields): array
    {
        $current = CustomerFields::fromClient($client);

        if ($fields['display_name'] !== $current['display_name'] && Client::displayNameTaken((string) $fields['display_name'], $client->getKey())) {
            SyncIssues::raise(SyncIssueKind::NameTaken, "QuickBooks calls {$client->display_name} “{$fields['display_name']}”, a name another client here has, so the app keeps “{$client->display_name}”. Rename one of them.", 'Customer', $client->qb_id);
            $fields['display_name'] = $current['display_name'];
        }

        // A client always has a name; QuickBooks requires one too.
        if ($fields['display_name'] === null) {
            $fields['display_name'] = $current['display_name'];
        }

        if ($fields['display_name'] !== $current['display_name']) {
            $client->display_name = (string) $fields['display_name'];
        }

        $client->fill(array_filter(
            array_diff_key($fields, ['display_name' => true]),
            fn (?string $value, string $field): bool => $value !== $current[$field],
            ARRAY_FILTER_USE_BOTH,
        ));

        return $fields;
    }

    /**
     * @param  list<array{field: string, kept: string|null, dropped: string|null, keptFrom: string}>  $conflicts
     */
    private function raiseConflicts(Client $client, array $conflicts): void
    {
        if ($conflicts === []) {
            return;
        }

        $details = collect($conflicts)->map(fn (array $conflict): string => CustomerFields::LABELS[$conflict['field']]
            .': kept '.$conflict['keptFrom'].' “'.($conflict['kept'] ?? 'blank').'”, not “'.($conflict['dropped'] ?? 'blank').'”')->implode('; ');

        SyncIssues::raise(SyncIssueKind::CustomerConflict, "{$client->display_name} changed in the app and in QuickBooks since they last matched. {$details}.", 'Customer', $client->qb_id);
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    private function locked(Client $client, Closure $callback): mixed
    {
        return Cache::lock("quickbooks:client:{$client->getKey()}", 60)->block(15, $callback);
    }
}
