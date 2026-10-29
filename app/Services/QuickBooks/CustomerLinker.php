<?php

namespace App\Services\QuickBooks;

use App\Models\Client;
use App\Models\QuickBooksCustomer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Matches QuickBooks customers to clients, makes clients from them, and undoes either. A match
 * changes neither side's details: QuickBooks' fields take over when two-way sync arrives (M6).
 * Each change goes in the client's history, since the link columns aren't fillable and so
 * aren't logged as edits.
 */
final class CustomerLinker
{
    /**
     * Links the client to the customer. Without someone doing it, it was the automatic match.
     */
    public function link(QuickBooksCustomer $customer, Client $client, ?User $by = null): void
    {
        if ($client->qb_id !== null) {
            throw new InvalidArgumentException("{$client->display_name} is already linked to a QuickBooks customer.");
        }

        if ($customer->client()->exists()) {
            throw new InvalidArgumentException("{$customer->display_name} is already linked to a client.");
        }

        $client->forceFill([
            'realm_id' => $customer->realm_id,
            'qb_id' => $customer->qb_id,
            'qb_sync_token' => $customer->qb_sync_token,
            'qb_last_updated_at' => $customer->qb_last_updated_at,
            'qb_linked_at' => now(),
        ])->save();
        $customer->forceFill(['skipped_at' => null])->save();

        activity()->performedOn($client)->causedBy($by)->event('quickbooks')
            ->log(($by === null ? 'Matched automatically to' : 'Linked to')." QuickBooks customer {$customer->display_name} ({$customer->qb_id})");
    }

    public function unlink(Client $client, User $by): void
    {
        $qbId = $client->qb_id;

        $client->forceFill(['realm_id' => null, 'qb_id' => null, 'qb_sync_token' => null, 'qb_last_updated_at' => null, 'qb_linked_at' => null])->save();

        activity()->performedOn($client)->causedBy($by)->event('quickbooks')
            ->log("Unlinked from QuickBooks customer {$qbId}");
    }

    /**
     * A client made from the customer's name, contact details, and billing address, linked to it.
     * No property: a job adds one where the work is.
     */
    public function createClient(QuickBooksCustomer $customer, User $by): Client
    {
        if (Client::displayNameTaken($customer->display_name)) {
            throw new InvalidArgumentException("A client named {$customer->display_name} already exists. Link the customer to it instead.");
        }

        return DB::transaction(function () use ($customer, $by): Client {
            $address = (array) data_get($customer->qb_payload, 'BillAddr', []);
            $state = strtoupper((string) ($address['CountrySubDivisionCode'] ?? ''));

            $client = Client::query()->create([
                'display_name' => $customer->display_name,
                'first_name' => $customer->given_name,
                'last_name' => $customer->family_name,
                'company' => $customer->company_name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'billing_street' => collect([$address['Line1'] ?? null, $address['Line2'] ?? null])->filter()->implode(', ') ?: null,
                'billing_city' => $address['City'] ?? null,
                'billing_state' => preg_match('/^[A-Z]{2}$/', $state) === 1 ? $state : null,
                'billing_zip' => isset($address['PostalCode']) ? mb_substr((string) $address['PostalCode'], 0, 10) : null,
            ]);

            $this->link($customer, $client, $by);

            return $client;
        });
    }

    public function skip(QuickBooksCustomer $customer): void
    {
        $customer->forceFill(['skipped_at' => now()])->save();
    }

    public function unskip(QuickBooksCustomer $customer): void
    {
        $customer->forceFill(['skipped_at' => null])->save();
    }
}
