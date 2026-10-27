<?php

namespace App\Support;

use App\Enums\LeadStatus;
use App\Enums\PropertyType;
use App\Models\Client;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Property;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Turns a quote request into a project, for a new client or one we already have.
 *
 * The same person is found by email (ignoring case) or phone, the way the inbox finds earlier
 * requests, and their property by address. Those are suggestions: the owner picks.
 */
final class LeadConverter
{
    /**
     * Clients who might be the person who sent the request.
     *
     * @return Collection<int, Client>
     */
    public static function matchingClients(Lead $lead): Collection
    {
        return Client::matching($lead->email, $lead->phone)->with('properties')->orderBy('display_name')->get();
    }

    /**
     * The client's property at the address in the request, if they have one.
     */
    public static function matchingProperty(Client $client, Lead $lead): ?Property
    {
        return $client->properties->first(fn (Property $property): bool => $property->isAt($lead->street, $lead->zip));
    }

    /**
     * Their name, or their name and town when another client has it, or a number after that.
     */
    public static function availableDisplayName(Lead $lead): string
    {
        $name = Str::squish($lead->name);

        foreach ([$name, "{$name} ({$lead->town})"] as $candidate) {
            if (! Client::displayNameTaken($candidate)) {
                return $candidate;
            }
        }

        $number = 2;

        while (Client::displayNameTaken("{$name} ({$lead->town}) {$number}")) {
            $number++;
        }

        return "{$name} ({$lead->town}) {$number}";
    }

    public static function defaultTitle(Lead $lead): string
    {
        return ($lead->service->name ?? 'Job').' at '.$lead->street;
    }

    /**
     * Creates what's missing (the client, then the property) and the project, and marks the
     * request converted. Pass null for the client or property to create one from the request.
     */
    public function convert(Lead $lead, ?Client $client, ?Property $property, string $title, ?string $displayName = null): Project
    {
        if ($property !== null && ($client === null || $property->client_id !== $client->getKey())) {
            throw new RuntimeException('The property has to belong to the chosen client.');
        }

        return DB::transaction(function () use ($lead, $client, $property, $title, $displayName): Project {
            // Locked, so two clicks can't convert it twice.
            $lead = Lead::query()->with('serviceArea')->lockForUpdate()->whereKey($lead->getKey())->firstOrFail();

            if ($lead->status === LeadStatus::Converted) {
                throw new RuntimeException('This request has already been converted.');
            }

            $client ??= Client::query()->create([
                'display_name' => $displayName ?? self::availableDisplayName($lead),
                'first_name' => Str::before(Str::squish($lead->name), ' '),
                'last_name' => Str::contains(Str::squish($lead->name), ' ') ? Str::after(Str::squish($lead->name), ' ') : null,
                'email' => $lead->email,
                'phone' => $lead->phone,
                'billing_street' => $lead->street,
                'billing_city' => $lead->town,
                'billing_state' => 'NC',
                'billing_zip' => $lead->zip,
            ]);

            $property ??= $client->properties()->create([
                'street' => $lead->street,
                'city' => $lead->town,
                'state' => 'NC',
                'zip' => $lead->zip,
                'service_area_id' => $lead->service_area_id,
                'county' => $lead->serviceArea?->county,
                'type' => PropertyType::Primary,
            ]);

            $project = $property->projects()->create([
                'title' => $title,
                'service_id' => $lead->service_id,
                'lead_id' => $lead->getKey(),
                'description' => collect([...$lead->answerLines(), $lead->message])->filter()->implode("\n"),
            ]);

            $lead->update(['status' => LeadStatus::Converted]);

            return $project;
        });
    }
}
