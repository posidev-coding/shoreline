<?php

namespace App\Support;

use App\Enums\PartCategory;

/**
 * The details worth recording for each kind of part, stored in its `specs`. Anything else
 * goes under "other", as free key and value pairs.
 */
final class PartSpecs
{
    /**
     * @return array<string, array{label: string, options?: array<string, string>, suffix?: string, placeholder?: string}>
     */
    public static function for(?PartCategory $category): array
    {
        return match ($category) {
            PartCategory::TorsionSpring => [
                'wire_size' => ['label' => 'Wire size', 'suffix' => 'in'],
                'inside_diameter' => ['label' => 'Inside diameter', 'suffix' => 'in'],
                'length' => ['label' => 'Length', 'suffix' => 'in'],
                'wind' => ['label' => 'Wind', 'options' => ['left' => 'Left', 'right' => 'Right']],
            ],
            PartCategory::ExtensionSpring => [
                'lift_rating' => ['label' => 'Lift rating', 'suffix' => 'lb'],
                'length' => ['label' => 'Length', 'suffix' => 'in'],
                'color_code' => ['label' => 'Color code'],
            ],
            PartCategory::Opener => [
                'drive' => ['label' => 'Drive', 'options' => ['chain' => 'Chain', 'belt' => 'Belt', 'screw' => 'Screw', 'jackshaft' => 'Jackshaft (wall mount)']],
                'horsepower' => ['label' => 'Horsepower'],
                'works_with' => ['label' => 'Works with', 'placeholder' => 'myQ, HomeLink'],
            ],
            PartCategory::Door => [
                'material' => ['label' => 'Material'],
                'r_value' => ['label' => 'Insulation R-value'],
                'wind_rating' => ['label' => 'Wind rating'],
            ],
            PartCategory::Cable => [
                'diameter' => ['label' => 'Diameter', 'suffix' => 'in'],
                'length' => ['label' => 'Length', 'suffix' => 'in'],
            ],
            default => [],
        };
    }

    /**
     * "Wire size: 0.243 in", for the part's page.
     *
     * @param  array<string, mixed>  $specs
     * @return list<string>
     */
    public static function lines(?PartCategory $category, array $specs): array
    {
        $lines = [];

        foreach (self::for($category) as $key => $field) {
            $value = $specs[$key] ?? null;

            if (blank($value)) {
                continue;
            }

            $shown = $field['options'][$value] ?? $value;
            $suffix = isset($field['suffix']) ? ' '.$field['suffix'] : '';
            $lines[] = "{$field['label']}: {$shown}{$suffix}";
        }

        foreach ($specs['other'] ?? [] as $key => $value) {
            if (filled($value)) {
                $lines[] = "{$key}: {$value}";
            }
        }

        return $lines;
    }
}
