<?php

use App\Enums\Urgency;
use App\Mail\QuoteRequestReceived;
use App\Models\Lead;
use App\Models\Service;
use App\Models\ServiceArea;
use App\Models\User;
use App\Notifications\NewLead;
use App\Settings\SiteSettings;
use Database\Seeders\ContentSeeder;

beforeEach(function () {
    $this->seed(ContentSeeder::class);
    $this->lead = Lead::factory()->create([
        'name' => 'Jane Smith',
        'email' => 'jane@example.com',
        'phone' => '+12525550142',
        'urgency' => Urgency::Emergency,
        'service_id' => Service::query()->where('slug', 'garage-door-repair')->value('id'),
        'service_area_id' => ServiceArea::query()->where('slug', 'beaufort')->value('id'),
        'town' => 'Beaufort',
        'message' => 'Heard a bang this morning.',
    ]);
});

describe('the owner’s alert', function () {
    it('flags urgency, carries the details, and replies to the customer', function () {
        $mail = (new NewLead($this->lead))->toMail(User::factory()->owner()->create());

        expect($mail->subject)->toBe('Urgent: Quote request: Garage Door Repair')
            ->and($mail->replyTo)->toBe([['jane@example.com', 'Jane Smith']])
            ->and($mail->actionUrl)->toBe(url("/admin/leads/{$this->lead->getKey()}"))
            ->and(implode("\n", $mail->introLines))
            ->toContain('[(252) 555-0142](tel:+12525550142)')
            ->toContain('Door: One double door')
            ->toContain('Heard a bang this morning.');
    });

    it('lands in the admin’s notification bell', function () {
        $owner = User::factory()->owner()->create();

        $owner->notify(new NewLead($this->lead));

        expect($owner->notifications()->sole()->data)
            ->title->toBe('Urgent: Quote request: Garage Door Repair')
            ->body->toBe('Jane Smith, Beaufort');
    });

    it('goes to the owner, or to super admins until there is one', function () {
        $admin = User::factory()->superAdmin()->create();
        User::factory()->create();

        expect(User::leadRecipients()->modelKeys())->toBe([$admin->getKey()]);

        $owner = User::factory()->owner()->create();

        expect(User::leadRecipients()->modelKeys())->toBe([$owner->getKey()]);
    });
});

describe('the customer’s confirmation', function () {
    it('greets them by first name and never repeats what they wrote', function () {
        $mail = new QuoteRequestReceived($this->lead);

        $mail->assertHasReplyTo(app(SiteSettings::class)->email)
            ->assertHasSubject('We got your quote request')
            ->assertSeeInHtml('Thanks, Jane')
            ->assertSeeInHtml('for Garage Door Repair in Beaufort', false)
            ->assertDontSeeInHtml('Heard a bang');
    });

    it('doesn’t greet a “name” that isn’t one, or repeat a town they typed', function () {
        $this->lead->update(['name' => 'www.cheap-pills.example', 'service_area_id' => null, 'town' => 'Visit cheap-pills.example']);

        (new QuoteRequestReceived($this->lead))
            ->assertSeeInHtml('Thanks</h1>', false)
            ->assertDontSeeInHtml('cheap-pills');
    });

    it('points an urgent request to the phone once there’s a real number', function () {
        (new QuoteRequestReceived($this->lead))->assertDontSeeInHtml('stuck or unsafe');

        $settings = app(SiteSettings::class);
        $settings->phone = '+12525551234';
        $settings->save();

        (new QuoteRequestReceived($this->lead))->assertSeeInHtml('If your door is stuck or unsafe right now, please call us at');
    });
});
