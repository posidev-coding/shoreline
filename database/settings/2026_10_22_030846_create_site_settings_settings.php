<?php

use App\Settings\SiteSettings;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('site.business_name', 'Shoreline Doors');
        $this->migrator->add('site.phone', SiteSettings::PLACEHOLDER_PHONE);
        $this->migrator->add('site.email', 'hello@shorelinedoors.com');
        $this->migrator->add('site.street_address', null);
        $this->migrator->add('site.city', 'Morehead City');
        $this->migrator->add('site.region', 'NC');
        $this->migrator->add('site.postal_code', '28557');
        // Left empty rather than guessed: the owner sets real hours.
        $this->migrator->add('site.hours', []);
        $this->migrator->add('site.license_info', null);
        $this->migrator->add('site.insurance_info', null);
        $this->migrator->add('site.google_review_url', null);
        $this->migrator->add('site.google_business_profile_url', null);
        $this->migrator->add('site.social_links', []);
        $this->migrator->add('site.hero_headline', 'Up, down & done right.');
        $this->migrator->add('site.hero_subhead', 'Garage door installs, repairs, and the odd jobs around the house, from Morehead City to Emerald Isle.');
    }
};
