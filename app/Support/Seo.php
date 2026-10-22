<?php

namespace App\Support;

use Spatie\SchemaOrg\Type;

/**
 * What a public page tells search engines and link previews about itself.
 */
final class Seo
{
    /**
     * @param  list<array{name: string, url: string}>  $breadcrumbs  Excluding Home, which is always first.
     * @param  list<Type>  $schemas  Extra JSON-LD for this page; the business itself is added on every page.
     */
    public function __construct(
        public string $title,
        public string $description,
        public ?string $canonical = null,
        public ?string $image = null,
        public string $type = 'website',
        public array $breadcrumbs = [],
        public array $schemas = [],
        public bool $isHome = false,
    ) {}

    public function fullTitle(string $businessName): string
    {
        return $this->isHome ? $this->title : "{$this->title} | {$businessName}";
    }

    /**
     * Canonical URLs never carry a query string, so filtered and tracked URLs fold into one.
     */
    public function canonicalUrl(): string
    {
        return $this->canonical ?? url()->current();
    }

    public function imageUrl(): string
    {
        return $this->image ?? asset('images/og/default.png');
    }
}
