<?php

namespace App\Support;

use App\Enums\ServiceIcon;
use App\Models\Service;

/**
 * The detail questions the quote wizard asks, which depend on the service. A service's
 * icon stands in for its kind: a door install asks for size and style, a repair for
 * what's wrong, and handyman work (or "something else") for a description.
 */
final class QuoteQuestions
{
    /** @var array<string, string> */
    public const array DOOR_SIZES = [
        'single' => 'One single door (8 to 10 ft wide)',
        'double' => 'One double door (16 to 18 ft wide)',
        'multiple' => 'Two or more doors',
        'not-sure' => 'Not sure',
    ];

    /** @var array<string, string> */
    public const array STYLES = [
        'raised-panel' => 'Traditional raised panel',
        'carriage-house' => 'Carriage house',
        'modern' => 'Modern or flush',
        'not-sure' => 'Not sure yet',
    ];

    /** @var array<string, string> */
    public const array SYMPTOMS = [
        'wont-move' => 'Won’t open or close',
        'noisy' => 'Loud, grinding, or shaky',
        'spring' => 'Broken spring, or I heard a loud bang',
        'off-track' => 'Off track or hanging crooked',
        'opener' => 'Opener, remote, or keypad not working',
        'damage' => 'Dented or damaged panel',
    ];

    public static function asksDoorSize(?Service $service): bool
    {
        return $service !== null && $service->icon !== ServiceIcon::Toolbox;
    }

    public static function asksStyle(?Service $service): bool
    {
        return in_array($service?->icon, [ServiceIcon::Install, ServiceIcon::Wind], true);
    }

    public static function asksSymptoms(?Service $service): bool
    {
        return in_array($service?->icon, [ServiceIcon::Repair, ServiceIcon::Spring, ServiceIcon::Opener], true);
    }

    /**
     * Handyman work and "something else" have no structured questions, so the description is required.
     */
    public static function requiresMessage(?Service $service): bool
    {
        return $service === null || $service->icon === ServiceIcon::Toolbox;
    }

    /**
     * One line per stored answer, for the admin and the owner's email.
     *
     * @param  array<string, mixed>  $answers
     * @return list<string>
     */
    public static function summaryLines(array $answers): array
    {
        $lines = [];

        if (is_string($answers['door_size'] ?? null) && isset(self::DOOR_SIZES[$answers['door_size']])) {
            $lines[] = 'Door: '.self::DOOR_SIZES[$answers['door_size']];
        }

        if (is_string($answers['style'] ?? null) && isset(self::STYLES[$answers['style']])) {
            $lines[] = 'Style: '.self::STYLES[$answers['style']];
        }

        $symptoms = array_values(array_intersect_key(self::SYMPTOMS, array_flip(array_filter((array) ($answers['symptoms'] ?? []), 'is_string'))));

        if ($symptoms !== []) {
            $lines[] = 'What’s wrong: '.implode('; ', $symptoms);
        }

        $hurricane = HurricaneReadiness::fromInput((array) ($answers['hurricane'] ?? []));

        if ($hurricane->hasResult()) {
            $lines[] = 'Hurricane-ready check: '.$hurricane->headline();
            array_push($lines, ...$hurricane->summaryLines());
        }

        return $lines;
    }
}
