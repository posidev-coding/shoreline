<?php

namespace App\Services\QuickBooks;

use App\Models\Client;
use App\Models\QuickBooksConnection;
use App\Models\QuickBooksCustomer;
use Illuminate\Support\Carbon;
use Propaganistas\LaravelPhone\PhoneNumber;
use Throwable;

/**
 * Brings a page of QuickBooks customers into quickbooks_customers, keyed by realm and QuickBooks
 * id, with the email and phone stored the way clients store them so the two compare directly.
 * A client already matched to one gets its current SyncToken.
 */
final class CustomerImporter
{
    /**
     * Imports one page and returns how many customers it held.
     */
    public function importPage(QuickBooksConnection $connection, bool $inactive, int $start, int $pageSize): int
    {
        $customers = QuickBooksClient::for($connection)
            ->queryPage('Customer', $inactive ? 'where Active = false' : '', $start, $pageSize);

        foreach ($customers as $customer) {
            $this->store($connection->realm_id, $customer);
        }

        return count($customers);
    }

    /**
     * @param  array<string, mixed>  $customer
     */
    private function store(string $realmId, array $customer): void
    {
        $updated = data_get($customer, 'MetaData.LastUpdatedTime');
        $updatedAt = is_string($updated) ? Carbon::parse($updated)->utc() : null;

        $row = QuickBooksCustomer::query()->firstOrNew(['realm_id' => $realmId, 'qb_id' => (string) $customer['Id']]);
        $row->fill([
            'qb_sync_token' => $customer['SyncToken'] ?? null,
            'qb_last_updated_at' => $updatedAt,
            'qb_payload' => $customer,
            'display_name' => (string) ($customer['DisplayName'] ?? ''),
            'company_name' => $customer['CompanyName'] ?? null,
            'given_name' => $customer['GivenName'] ?? null,
            'family_name' => $customer['FamilyName'] ?? null,
            'email' => self::email(data_get($customer, 'PrimaryEmailAddr.Address')),
            'phone' => self::phone(data_get($customer, 'PrimaryPhone.FreeFormNumber')) ?? self::phone(data_get($customer, 'Mobile.FreeFormNumber')),
            'billing_city' => data_get($customer, 'BillAddr.City'),
            'is_active' => (bool) ($customer['Active'] ?? true),
            // QuickBooks marks a sub-customer with Job and ParentRef, and a QBO project with IsProject too.
            'parent_qb_id' => ($customer['Job'] ?? false) ? data_get($customer, 'ParentRef.value') : null,
            'is_project' => (bool) ($customer['IsProject'] ?? false),
        ])->save();

        Client::query()
            ->where('realm_id', $realmId)
            ->where('qb_id', $row->qb_id)
            ->toBase()
            ->update(['qb_sync_token' => $row->qb_sync_token, 'qb_last_updated_at' => $updatedAt]);
    }

    /**
     * Lowercase, as clients store it. QuickBooks allows several addresses separated by commas;
     * the first is the one to match on.
     */
    public static function email(mixed $email): ?string
    {
        if (! is_string($email) || blank($email)) {
            return null;
        }

        return mb_strtolower(trim(explode(',', $email)[0]));
    }

    /**
     * E.164, as clients store it, or nothing when QuickBooks holds something that isn't a phone
     * number. Without a country code, it's read as a US number.
     */
    public static function phone(mixed $phone): ?string
    {
        if (! is_string($phone) || blank($phone)) {
            return null;
        }

        try {
            $number = new PhoneNumber($phone, 'US');

            return $number->isValid() ? $number->formatE164() : null;
        } catch (Throwable) {
            return null;
        }
    }
}
