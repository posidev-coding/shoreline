<?php

namespace App\Settings;

use Propaganistas\LaravelPhone\PhoneNumber;
use Spatie\LaravelSettings\Settings;

/**
 * Business details and editable hero copy. Keep the name, address, and phone identical
 * to the Google Business Profile: search engines compare them.
 */
class SiteSettings extends Settings
{
    /** A reserved fictional number (555-0100), shown until the owner enters the real one. */
    public const string PLACEHOLDER_PHONE = '+12525550100';

    public string $business_name;

    /** E.164, e.g. +12525550100. */
    public string $phone;

    public string $email;

    /** Null hides the street address, as a service-area business may. */
    public ?string $street_address;

    public string $city;

    public string $region;

    public string $postal_code;

    /**
     * Only a phpstan-var tag here: spatie/laravel-settings reads `var` tags to build casts and
     * fails on nested arrays, while with none it stores the array as is.
     *
     * @phpstan-var list<array{days: list<string>, opens: string, closes: string}>
     */
    public array $hours;

    public ?string $license_info;

    public ?string $insurance_info;

    public ?string $google_review_url;

    public ?string $google_business_profile_url;

    /** @phpstan-var list<array{label: string, url: string}> */
    public array $social_links;

    public string $hero_headline;

    public string $hero_subhead;

    public static function group(): string
    {
        return 'site';
    }

    public function phoneForDisplay(): string
    {
        return (new PhoneNumber($this->phone, 'US'))->formatNational();
    }

    public function phoneHref(): string
    {
        return 'tel:'.$this->phone;
    }

    public function hasPlaceholderPhone(): bool
    {
        return $this->phone === self::PLACEHOLDER_PHONE;
    }
}
