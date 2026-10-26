<?php

namespace Database\Seeders;

use App\Enums\ReviewSource;
use App\Models\Service;
use App\Models\ServiceArea;
use App\Models\Showcase;
use App\Models\Testimonial;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Sample jobs and reviews so the gallery, slider, and review cards have something to show
 * in local development. Every review is labeled as a demo, and this refuses to run outside
 * the local environment: published reviews must be real.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->isLocal()) {
            throw new RuntimeException('DemoSeeder only runs in the local environment.');
        }

        // Generate the photo renditions now instead of waiting for a queue worker.
        config(['media-library.queue_conversions_by_default' => false]);

        $jobs = [
            ['title' => 'Rusted single door replaced', 'service' => 'garage-door-installation', 'area' => 'atlantic-beach', 'featured' => true],
            ['title' => 'Off-track door rehung and balanced', 'service' => 'garage-door-repair', 'area' => 'beaufort', 'featured' => false],
            ['title' => 'Wind-rated double door install', 'service' => 'hurricane-wind-rated-doors', 'area' => 'emerald-isle', 'featured' => true],
        ];

        foreach ($jobs as $index => $job) {
            $showcase = Showcase::query()->firstOrCreate(['slug' => str($job['title'])->slug()->toString()], [
                'title' => $job['title'],
                'service_id' => Service::query()->where('slug', $job['service'])->value('id'),
                'service_area_id' => ServiceArea::query()->where('slug', $job['area'])->value('id'),
                'story' => '<p>Demo job for local development. Replace with real work in the admin.</p>',
                'has_photo_release' => true,
                'is_featured' => $job['featured'],
                'published_at' => now()->subDays($index + 1),
            ]);

            if (! $showcase->hasMedia('before')) {
                $showcase->addMedia($this->door(rusted: true))->toMediaCollection('before');
                $showcase->addMedia($this->door(rusted: false))->toMediaCollection('after');
            }
        }

        if (Testimonial::query()->doesntExist()) {
            foreach (['beaufort', 'emerald-isle', 'morehead-city'] as $index => $area) {
                Testimonial::query()->create([
                    'quote' => 'Demo review for local development. Real reviews are entered by the owner and published only with permission.',
                    'author_name' => 'Demo Customer '.($index + 1),
                    'service_area_id' => ServiceArea::query()->where('slug', $area)->value('id'),
                    'rating' => 5,
                    'source' => ReviewSource::Direct,
                    'reviewed_on' => now()->subMonths($index + 1),
                    'is_featured' => $index === 0,
                    'is_published' => true,
                ]);
            }
        }
    }

    /**
     * @param  int<0, 255>  $red
     * @param  int<0, 255>  $green
     * @param  int<0, 255>  $blue
     */
    private function color(\GdImage $image, int $red, int $green, int $blue): int
    {
        $color = imagecolorallocate($image, $red, $green, $blue);

        if ($color === false) {
            throw new RuntimeException('Could not allocate an image color.');
        }

        return $color;
    }

    /**
     * Draws a simple garage door photo stand-in and returns its temporary path.
     */
    private function door(bool $rusted): string
    {
        $image = imagecreatetruecolor(1600, 1200);
        $wall = $this->color($image, 214, 207, 192);
        $panel = $rusted ? $this->color($image, 139, 99, 66) : $this->color($image, 244, 240, 232);
        $groove = $rusted ? $this->color($image, 98, 66, 44) : $this->color($image, 205, 198, 184);
        imagefilledrectangle($image, 0, 0, 1599, 1199, $wall);
        imagefilledrectangle($image, 200, 250, 1399, 1199, $panel);

        for ($row = 1; $row < 4; $row++) {
            imagefilledrectangle($image, 200, 250 + $row * 237, 1399, 256 + $row * 237, $groove);
        }

        if ($rusted) {
            $rust = $this->color($image, 168, 82, 30);

            for ($spot = 0; $spot < 60; $spot++) {
                imagefilledellipse($image, random_int(220, 1380), random_int(270, 1180), random_int(10, 60), random_int(10, 40), $rust);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'demo-door').'.jpg';
        imagejpeg($image, $path, 85);

        return $path;
    }
}
