<?php

namespace Database\Seeders;

use App\Enums\County;
use App\Enums\PageKey;
use App\Enums\ServiceIcon;
use App\Models\Faq;
use App\Models\Page;
use App\Models\Service;
use App\Models\ServiceArea;
use Illuminate\Database\Seeder;

/**
 * Seeds the website copy from content/site-copy.php. It only creates what's missing, so
 * re-running it never overwrites the owner's edits. On a new environment, run it once:
 * `php artisan db:seed --class=ContentSeeder --force`.
 */
class ContentSeeder extends Seeder
{
    public function run(): void
    {
        /**
         * @var array{
         *     services: list<array{slug: string, name: string, icon: string, headline: string, summary: string, body: string, seo_title: string, seo_description: string, faqs: list<array{question: string, answer: string}>}>,
         *     service_areas: list<array{slug: string, name: string, county: string, intro: string, seo_title: string, seo_description: string}>,
         *     faqs: list<array{question: string, answer: string}>,
         *     pages: array<string, array{title: string, intro: string, body: string}>,
         * } $copy
         */
        $copy = require __DIR__.'/content/site-copy.php';

        foreach ($copy['services'] as $order => $data) {
            $service = Service::query()->firstOrCreate(['slug' => $data['slug']], [
                'name' => $data['name'],
                'icon' => ServiceIcon::from($data['icon']),
                'headline' => $data['headline'],
                'summary' => $data['summary'],
                'body' => $data['body'],
                'seo_title' => $data['seo_title'],
                'seo_description' => $data['seo_description'],
                'sort_order' => $order,
            ]);

            if ($service->wasRecentlyCreated) {
                foreach ($data['faqs'] as $faqOrder => $faq) {
                    $service->faqs()->create([...$faq, 'sort_order' => $faqOrder]);
                }
            }
        }

        foreach ($copy['service_areas'] as $order => $data) {
            ServiceArea::query()->firstOrCreate(['slug' => $data['slug']], [
                'name' => $data['name'],
                'county' => County::from($data['county']),
                'intro' => $data['intro'],
                'seo_title' => $data['seo_title'],
                'seo_description' => $data['seo_description'],
                'sort_order' => $order,
            ]);
        }

        if (Faq::query()->whereNull('service_id')->doesntExist()) {
            foreach ($copy['faqs'] as $order => $faq) {
                Faq::query()->create([...$faq, 'sort_order' => $order]);
            }
        }

        foreach ($copy['pages'] as $key => $data) {
            Page::query()->firstOrCreate(['key' => PageKey::from($key)], $data);
        }
    }
}
