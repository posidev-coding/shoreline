<?php

namespace App\Support;

use App\Models\Service;
use App\Models\ServiceArea;
use App\Settings\SiteSettings;
use Illuminate\Support\Collection;
use Spatie\SchemaOrg\BreadcrumbList;
use Spatie\SchemaOrg\City;
use Spatie\SchemaOrg\HomeAndConstructionBusiness;
use Spatie\SchemaOrg\Schema;
use Spatie\SchemaOrg\Service as ServiceSchema;

/**
 * JSON-LD for the public site: the business on every page, plus Service and BreadcrumbList
 * where they apply. FAQ and review markup are left out on purpose: Google restricts those
 * rich results, and self-served review stars aren't eligible.
 */
final class StructuredData
{
    public static function businessId(): string
    {
        return url('/').'#business';
    }

    /**
     * @param  Collection<int, ServiceArea>  $areas
     */
    public static function business(SiteSettings $settings, Collection $areas): HomeAndConstructionBusiness
    {
        $address = Schema::postalAddress()
            ->addressLocality($settings->city)
            ->addressRegion($settings->region)
            ->postalCode($settings->postal_code)
            ->addressCountry('US')
            ->if(filled($settings->street_address), fn ($address) => $address->streetAddress($settings->street_address));

        $sameAs = collect($settings->social_links)->pluck('url')
            ->push($settings->google_business_profile_url)
            ->filter()
            ->values()
            ->all();

        return Schema::homeAndConstructionBusiness()
            ->identifier(self::businessId())
            ->name($settings->business_name)
            ->url(url('/'))
            ->logo(asset('icon-512.png'))
            ->image(asset('images/og/default.png'))
            ->email($settings->email)
            // A placeholder number must never reach search engines.
            ->if(! $settings->hasPlaceholderPhone(), fn ($business) => $business->telephone($settings->phone))
            ->address($address)
            ->areaServed(self::cities($areas))
            ->if($sameAs !== [], fn ($business) => $business->sameAs($sameAs))
            ->if($settings->hours !== [], fn ($business) => $business->openingHoursSpecification(
                // Plain strings are valid JSON-LD here; the typed setters only take objects.
                collect($settings->hours)->map(fn (array $row) => Schema::openingHoursSpecification()
                    ->setProperty('dayOfWeek', array_map(fn (string $day): string => "https://schema.org/{$day}", $row['days']))
                    ->setProperty('opens', $row['opens'])
                    ->setProperty('closes', $row['closes']))->all(),
            ));
    }

    /**
     * @param  Collection<int, ServiceArea>  $areas
     */
    public static function service(Service $service, Collection $areas): ServiceSchema
    {
        return Schema::service()
            ->name($service->name)
            ->serviceType($service->name)
            ->description($service->summary)
            ->url(route('services.show', $service))
            ->provider(Schema::homeAndConstructionBusiness()->identifier(self::businessId()))
            ->areaServed(self::cities($areas));
    }

    /**
     * @param  list<array{name: string, url: string}>  $trail
     */
    public static function breadcrumbs(array $trail): BreadcrumbList
    {
        $items = [['name' => 'Home', 'url' => url('/')], ...$trail];

        return Schema::breadcrumbList()->itemListElement(
            collect($items)->values()->map(fn (array $item, int $index) => Schema::listItem()
                ->position($index + 1)
                ->name($item['name'])
                ->setProperty('item', $item['url']))->all(),
        );
    }

    /**
     * @param  Collection<int, ServiceArea>  $areas
     * @return list<City>
     */
    private static function cities(Collection $areas): array
    {
        return array_values($areas->map(fn (ServiceArea $area) => Schema::city()
            ->name("{$area->name}, NC")
            ->containedInPlace(Schema::administrativeArea()->name($area->county->getLabel().', NC')))
            ->all());
    }
}
