<?php

namespace App\Services\QuickBooks;

use App\Filament\Support\ContactFields;
use App\Models\Client;

/**
 * The client fields kept in step with a QuickBooks customer, read from either side into the same
 * shape (trimmed, blanks as null, email lowercase, phone in E.164, state as two letters) so the
 * two compare field by field.
 */
final class CustomerFields
{
    public const array FIELDS = [
        'display_name', 'company', 'first_name', 'last_name', 'email', 'phone',
        'billing_street', 'billing_city', 'billing_state', 'billing_zip',
    ];

    /**
     * Fields where, when both sides changed, the newer edit wins. QuickBooks wins the rest.
     */
    public const array SHARED = ['phone'];

    public const array LABELS = [
        'display_name' => 'name', 'company' => 'company', 'first_name' => 'first name', 'last_name' => 'last name',
        'email' => 'email', 'phone' => 'phone', 'billing_street' => 'billing street', 'billing_city' => 'billing city',
        'billing_state' => 'billing state', 'billing_zip' => 'billing ZIP',
    ];

    private const array ADDRESS = ['billing_street', 'billing_city', 'billing_state', 'billing_zip'];

    /**
     * @return array<string, string|null>
     */
    public static function fromClient(Client $client): array
    {
        return self::normalize([
            // QuickBooks refuses a colon in a name: it separates a sub-customer from its parent.
            'display_name' => str_replace(':', '-', (string) $client->display_name),
            'company' => $client->company,
            'first_name' => $client->first_name,
            'last_name' => $client->last_name,
            'email' => $client->email,
            'phone' => $client->phone,
            'billing_street' => $client->billing_street,
            'billing_city' => $client->billing_city,
            'billing_state' => $client->billing_state,
            'billing_zip' => $client->billing_zip,
        ]);
    }

    /**
     * @param  array<string, mixed>  $customer
     * @return array<string, string|null>
     */
    public static function fromQuickBooks(array $customer): array
    {
        $address = (array) ($customer['BillAddr'] ?? []);

        return self::normalize([
            'display_name' => $customer['DisplayName'] ?? null,
            'company' => $customer['CompanyName'] ?? null,
            'first_name' => $customer['GivenName'] ?? null,
            'last_name' => $customer['FamilyName'] ?? null,
            'email' => CustomerImporter::email(data_get($customer, 'PrimaryEmailAddr.Address')),
            'phone' => CustomerImporter::phone(data_get($customer, 'PrimaryPhone.FreeFormNumber')),
            // The app keeps one street line; QuickBooks' second line joins it.
            'billing_street' => collect([$address['Line1'] ?? null, $address['Line2'] ?? null])->map(fn (mixed $line): string => trim((string) $line))->filter()->implode(', '),
            'billing_city' => $address['City'] ?? null,
            'billing_state' => $address['CountrySubDivisionCode'] ?? null,
            'billing_zip' => isset($address['PostalCode']) ? mb_substr((string) $address['PostalCode'], 0, 10) : null,
        ]);
    }

    /**
     * Whether two sets of fields are the same, whatever order their keys are in (jsonb reorders them).
     *
     * @param  array<string, string|null>  $fields
     * @param  array<string, mixed>|null  $other
     */
    public static function same(array $fields, ?array $other): bool
    {
        if ($other === null) {
            return false;
        }

        ksort($fields);
        ksort($other);

        return $fields === $other;
    }

    /**
     * A QuickBooks customer's body for these fields: all of them for a new customer, or only the
     * changed ones for a sparse update. The billing address goes whole; a second street line
     * QuickBooks has is emptied, since the app's street already holds it.
     *
     * @param  array<string, string|null>  $fields
     * @param  list<string>|null  $only
     * @param  array<string, mixed>  $remote
     * @return array<string, mixed>
     */
    public static function toQuickBooks(array $fields, ?array $only = null, array $remote = []): array
    {
        $send = fn (string $field): bool => $only === null || in_array($field, $only, true);
        $value = fn (string $field): string => (string) ($fields[$field] ?? '');
        $body = [];

        foreach (['display_name' => 'DisplayName', 'company' => 'CompanyName', 'first_name' => 'GivenName', 'last_name' => 'FamilyName'] as $field => $key) {
            if ($send($field) && ($only !== null || $fields[$field] !== null)) {
                $body[$key] = $value($field);
            }
        }

        if ($send('email') && ($only !== null || $fields['email'] !== null)) {
            $body['PrimaryEmailAddr'] = ['Address' => $value('email')];
        }

        if ($send('phone') && ($only !== null || $fields['phone'] !== null)) {
            $body['PrimaryPhone'] = ['FreeFormNumber' => (string) ContactFields::national($fields['phone'])];
        }

        if (array_filter(self::ADDRESS, $send) !== [] && ($only !== null || array_filter(array_intersect_key($fields, array_flip(self::ADDRESS))) !== [])) {
            $body['BillAddr'] = [
                'Line1' => $value('billing_street'),
                'City' => $value('billing_city'),
                'CountrySubDivisionCode' => $value('billing_state'),
                'PostalCode' => $value('billing_zip'),
                ...(filled(data_get($remote, 'BillAddr.Line2')) ? ['Line2' => ''] : []),
            ];
        }

        return $body;
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, string|null>
     */
    private static function normalize(array $fields): array
    {
        $clean = [];

        foreach (self::FIELDS as $field) {
            $value = trim((string) ($fields[$field] ?? ''));
            $clean[$field] = $value === '' ? null : $value;
        }

        if ($clean['email'] !== null) {
            $clean['email'] = mb_strtolower($clean['email']);
        }

        $state = strtoupper((string) $clean['billing_state']);
        $clean['billing_state'] = preg_match('/^[A-Z]{2}$/', $state) === 1 ? $state : null;

        return $clean;
    }
}
