<?php

namespace Database\Seeders;

use App\Enums\ContactMethod;
use App\Enums\EquipmentType;
use App\Enums\LeadStatus;
use App\Enums\OrderStatus;
use App\Enums\PartCategory;
use App\Enums\PartUnit;
use App\Enums\ProjectStage;
use App\Enums\PropertyType;
use App\Enums\ReviewSource;
use App\Enums\Urgency;
use App\Enums\VisitStatus;
use App\Enums\VisitType;
use App\Models\Client;
use App\Models\Lead;
use App\Models\Part;
use App\Models\Service;
use App\Models\ServiceArea;
use App\Models\Showcase;
use App\Models\Supplier;
use App\Models\Testimonial;
use App\Support\Stock;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Sample jobs, reviews, quote requests, clients, and projects so the gallery, slider, review
 * cards, lead inbox, and project pages have something to show in local development. Every
 * record is labeled as a demo, and this refuses to run outside the local environment:
 * published reviews must be real.
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

        if (Lead::query()->doesntExist()) {
            $this->leads();
        }

        if (Client::query()->doesntExist()) {
            $this->clients();
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
     * Three quote requests for the inbox, all at example.com addresses.
     */
    private function leads(): void
    {
        $leads = [
            ['service' => 'garage-door-repair', 'area' => 'beaufort', 'urgency' => Urgency::Emergency, 'status' => LeadStatus::New, 'preferred' => ContactMethod::Text,
                'answers' => ['door_size' => 'double', 'symptoms' => ['spring', 'wont-move']], 'message' => 'Demo request: heard a bang and the door won’t lift.', 'age' => 2],
            ['service' => 'hurricane-wind-rated-doors', 'area' => 'emerald-isle', 'urgency' => Urgency::Flexible, 'status' => LeadStatus::New, 'preferred' => ContactMethod::Email,
                'answers' => ['door_size' => 'single', 'style' => 'carriage-house', 'hurricane' => ['age' => 'over-20', 'label' => 'no', 'windows' => 'standard']], 'message' => null, 'age' => 26],
            ['service' => 'handyman-home-repairs', 'area' => 'morehead-city', 'urgency' => Urgency::Soon, 'status' => LeadStatus::Contacted, 'preferred' => ContactMethod::Call,
                'answers' => [], 'message' => 'Demo request: replace a rotted porch step and rehang a sticking door.', 'age' => 72],
        ];

        foreach ($leads as $index => $data) {
            $area = ServiceArea::query()->where('slug', $data['area'])->firstOrFail();

            $lead = Lead::query()->create([
                'status' => $data['status'],
                'service_id' => Service::query()->where('slug', $data['service'])->value('id'),
                'urgency' => $data['urgency'],
                'answers' => $data['answers'],
                'message' => $data['message'],
                'name' => 'Demo Customer '.($index + 1),
                'email' => 'demo'.($index + 1).'@example.com',
                'phone' => '+125255501'.($index + 10),
                'preferred_contact' => $data['preferred'],
                'sms_consent' => $data['preferred'] === ContactMethod::Text,
                'street' => (100 + $index * 7).' Demo St',
                'town' => $area->name,
                'zip' => '28557',
                'service_area_id' => $area->getKey(),
                'attribution' => ['landing_page' => '/services/'.$data['service'], 'utm_source' => 'demo'],
                'contacted_at' => $data['status'] === LeadStatus::Contacted ? now()->subHours($data['age'] - 4) : null,
            ]);
            $lead->forceFill(['created_at' => now()->subHours($data['age'])])->save();

            if ($index === 0) {
                $lead->addMedia($this->door(rusted: true))->usingName('IMG_0412')->toMediaCollection('photos');
                $lead->addMedia($this->door(rusted: false))->usingName('IMG_0413')->toMediaCollection('photos');
            }
        }
    }

    /**
     * A homeowner with a job on the calendar, and a rental company with two beach houses,
     * so the client, property, and project pages have something to show.
     */
    private function clients(): void
    {
        $beaufort = ServiceArea::query()->where('slug', 'beaufort')->firstOrFail();
        $emeraldIsle = ServiceArea::query()->where('slug', 'emerald-isle')->firstOrFail();

        $homeowner = Client::query()->create([
            'display_name' => 'Demo Homeowner',
            'first_name' => 'Demo',
            'last_name' => 'Homeowner',
            'email' => 'homeowner@example.com',
            'phone' => '+12525550190',
            'billing_street' => '210 Demo St',
            'billing_city' => 'Beaufort',
            'billing_state' => 'NC',
            'billing_zip' => '28516',
        ]);
        $home = $homeowner->properties()->create([
            'street' => '210 Demo St', 'city' => 'Beaufort', 'zip' => '28516',
            'service_area_id' => $beaufort->getKey(), 'county' => $beaufort->county,
        ]);
        $springJob = $home->projects()->create([
            'title' => 'Replace broken torsion spring',
            'service_id' => Service::query()->where('slug', 'springs-and-cables')->value('id'),
            'stage' => ProjectStage::Scheduled,
            'description' => 'Demo project: one broken spring on a 16 x 7 door. Replace both and new cables.',
        ]);
        $parts = $this->parts();
        $lines = [
            [$parts['left'], 'Torsion spring 0.243 x 2 x 32, left wind', '1', '89.0000'],
            [$parts['right'], 'Torsion spring 0.243 x 2 x 32, right wind', '1', '89.0000'],
            [$parts['cables'], 'Lift cables, pair', '1', '24.5000'],
            [null, 'Labor', '1.5', '95.0000'],
        ];
        foreach ($lines as $index => [$part, $description, $quantity, $rate]) {
            $springJob->lineItems()->create(['part_id' => $part?->getKey(), 'description' => $description, 'quantity' => $quantity, 'unit_price' => $rate, 'sort_order' => $index, 'is_taxable' => $part !== null]);
        }

        // What's already on the house: the door and opener he'll find when he gets there.
        $door = $home->equipment()->create(['type' => EquipmentType::Door, 'manufacturer' => 'Clopay', 'model' => '4050', 'size' => '16x7', 'material' => 'Steel', 'installed_on' => now()->subYears(9)->toDateString()]);
        $home->equipment()->create(['type' => EquipmentType::Opener, 'parent_id' => $door->getKey(), 'manufacturer' => 'LiftMaster', 'model' => '8500', 'installed_on' => now()->subYears(6)->toDateString(), 'maintenance_due_on' => now()->addMonths(2)->toDateString()]);
        $home->equipment()->create(['type' => EquipmentType::SpringSet, 'parent_id' => $door->getKey(), 'notes' => 'Demo: the left spring broke.', 'installed_on' => now()->subYears(9)->toDateString()]);
        $tomorrow = now(config('app.business_timezone'))->addDay()->setTime(9, 0);
        $springJob->visits()->create(['type' => VisitType::Service, 'status' => VisitStatus::Confirmed, 'starts_at' => $tomorrow->clone()->utc(), 'ends_at' => $tomorrow->clone()->addHours(2)->utc()]);
        $springJob->tasks()->create(['title' => 'Bring winding bars', 'sort_order' => 0]);
        $springJob->tasks()->create(['title' => 'Lube rollers and hinges', 'sort_order' => 1]);

        $rentals = Client::query()->create([
            'display_name' => 'Demo Beach Rentals',
            'company' => 'Demo Beach Rentals',
            'email' => 'rentals@example.com',
            'phone' => '+12525550191',
            'is_property_manager' => true,
        ]);
        foreach (['8 Demo Ocean Dr', '14 Demo Ocean Dr'] as $index => $street) {
            $rental = $rentals->properties()->create([
                'label' => 'Demo rental '.($index + 1),
                'street' => $street, 'city' => 'Emerald Isle', 'zip' => '28594',
                'service_area_id' => $emeraldIsle->getKey(), 'county' => $emeraldIsle->county,
                'type' => PropertyType::VacationRental,
                'access_info' => 'Demo lockbox 0000',
                'turnover_notes' => 'Saturday changeover, 10 AM to 4 PM',
            ]);
            $job = $rental->projects()->create([
                'title' => $index === 0 ? 'Wind-rated door install' : 'Opener install',
                'service_id' => Service::query()->where('slug', $index === 0 ? 'hurricane-wind-rated-doors' : 'openers-and-smart-controls')->value('id'),
                'stage' => $index === 0 ? ProjectStage::Approved : ProjectStage::Completed,
            ]);

            if ($index === 0) {
                // Ordered for the job, so it shows on Parts to order.
                $job->lineItems()->create(['part_id' => $parts['door']->getKey(), 'description' => $parts['door']->name, 'quantity' => '1', 'unit_price' => '2290', 'order_status' => OrderStatus::ToOrder, 'supplier_id' => $parts['door']->supplier_id]);
            }
        }
    }

    /**
     * Two suppliers and the parts the demo jobs use, with springs counted on the truck.
     *
     * @return array<string, Part>
     */
    private function parts(): array
    {
        $coastal = Supplier::query()->create(['name' => 'Demo Coastal Door Supply', 'rep_name' => 'Demo Rep', 'account_number' => 'DEMO-1001']);
        $openers = Supplier::query()->create(['name' => 'Demo Opener Distributors']);

        $spring = fn (string $wind): array => [
            'category' => PartCategory::TorsionSpring, 'name' => "Torsion spring 0.243 x 2 x 32, {$wind} wind", 'supplier_id' => $coastal->getKey(),
            'specs' => ['wire_size' => '0.243', 'inside_diameter' => '2', 'length' => '32', 'wind' => $wind],
            'cost' => '38', 'price' => '89', 'warranty_months' => 36, 'track_stock' => true, 'reorder_point' => '2',
        ];

        $parts = [
            'left' => Part::query()->create($spring('left')),
            'right' => Part::query()->create($spring('right')),
            'cables' => Part::query()->create(['category' => PartCategory::Cable, 'name' => 'Lift cables, pair', 'supplier_id' => $coastal->getKey(), 'unit' => PartUnit::Pair, 'cost' => '9', 'price' => '24.50', 'track_stock' => true, 'reorder_point' => '3']),
            'opener' => Part::query()->create(['category' => PartCategory::Opener, 'name' => 'LiftMaster 87504 wall-mount opener', 'manufacturer' => 'LiftMaster', 'model' => '87504-267', 'supplier_id' => $openers->getKey(), 'specs' => ['drive' => 'jackshaft', 'horsepower' => '1/2', 'works_with' => 'myQ'], 'cost' => '520', 'price' => '849', 'warranty_months' => 60]),
            'door' => Part::query()->create(['category' => PartCategory::Door, 'name' => 'Clopay Classic 4050, 16 x 7, wind rated', 'manufacturer' => 'Clopay', 'model' => '4050', 'supplier_id' => $coastal->getKey(), 'specs' => ['material' => 'Steel', 'wind_rating' => '130 mph'], 'cost' => '1400', 'price' => '2290', 'warranty_months' => 120]),
        ];

        $stock = app(Stock::class);
        $stock->adjustTo($parts['left'], '3', 'Demo starting count');
        $stock->adjustTo($parts['right'], '1', 'Demo starting count');
        $stock->adjustTo($parts['cables'], '6', 'Demo starting count');

        return $parts;
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
