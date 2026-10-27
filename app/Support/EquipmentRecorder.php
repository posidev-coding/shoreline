<?php

namespace App\Support;

use App\Enums\EquipmentStatus;
use App\Enums\EquipmentType;
use App\Models\InstalledEquipment;
use App\Models\Part;
use App\Models\Project;
use App\Models\ProjectLineItem;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Records what a completed job installed at the property, starting from its line items: each
 * line whose part becomes equipment (a door, opener, keypad, and so on) is one row, and all
 * its springs together are one spring set. Everything that isn't a door goes on one door,
 * new from this job or already there.
 */
final class EquipmentRecorder
{
    /**
     * The rows to start the form from.
     *
     * @return list<array{type: string, part_id: int|null, manufacturer: string|null, model: string|null, size: string|null, serial: null, warranty_expires_on: string|null, replaces: string|null}>
     */
    public static function draft(Project $project): array
    {
        $installedOn = self::installedOn($project);
        $warrantyEnds = fn (Part $part): ?string => $part->warranty_months ? $installedOn->clone()->addMonths($part->warranty_months)->toDateString() : null;

        $parts = $project->lineItems()->with('part')->get()
            ->map(fn (ProjectLineItem $line): ?Part => $line->part)
            ->filter(fn (?Part $part): bool => $part?->category->equipmentType() !== null);

        // A door's springs are one set, though a torsion pair is billed as two lines: the first
        // spring stands for the set, with the longest warranty among them.
        $springs = $parts->filter(fn (Part $part): bool => $part->category->isSpring());
        $rows = [];

        foreach ($parts as $index => $part) {
            $type = $part->category->equipmentType();

            // By position, not by part: two lines can share one part, and then one model instance.
            if ($type === null || ($part->category->isSpring() && $index !== $springs->keys()->first())) {
                continue;
            }

            $replaceable = self::replaceable($project, $type);

            $rows[] = [
                'type' => $type->value,
                'part_id' => (int) $part->getKey(),
                'manufacturer' => $part->manufacturer,
                'model' => $part->model,
                'size' => null,
                'serial' => null,
                'warranty_expires_on' => $part->category->isSpring() ? $springs->map($warrantyEnds)->filter()->max() : $warrantyEnds($part),
                // Suggested only when there's no doubt: exactly one of its kind at the property.
                'replaces' => $replaceable->count() === 1 ? (string) $replaceable->sole()->getKey() : null,
            ];
        }

        return $rows;
    }

    /**
     * Whether to offer recording right after the job is completed: it installed something that
     * becomes equipment, and nothing's been recorded for it yet.
     */
    public static function hasSomethingToRecord(Project $project): bool
    {
        return self::draft($project) !== [] && ! $project->installedEquipment()->exists();
    }

    /**
     * Which door the rest goes on: the new one when the job installed a door, the property's
     * only door when it has just one, or none.
     *
     * @param  list<array{type: string}>  $rows
     */
    public static function suggestedDoor(Project $project, array $rows): string
    {
        if (collect($rows)->contains('type', EquipmentType::Door->value)) {
            return 'new';
        }

        $doors = self::doors($project);

        return $doors->count() === 1 ? (string) $doors->sole()->getKey() : 'none';
    }

    /**
     * The property's doors that are still in place.
     *
     * @return EloquentCollection<int, InstalledEquipment>
     */
    public static function doors(Project $project): EloquentCollection
    {
        return self::replaceable($project, EquipmentType::Door);
    }

    /**
     * What a new piece of this type could be replacing at the property.
     *
     * @return EloquentCollection<int, InstalledEquipment>
     */
    public static function replaceable(Project $project, EquipmentType $type): EloquentCollection
    {
        return InstalledEquipment::query()
            ->where('property_id', $project->property_id)
            ->where('type', $type)
            ->where('status', EquipmentStatus::Active)
            ->where(fn ($query) => $query->whereNull('installed_by_project_id')->orWhere('installed_by_project_id', '!=', $project->getKey()))
            ->orderBy('id')
            ->get();
    }

    /**
     * Creates the equipment, doors first so the rest can hang on them, and retires what it replaces.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return Collection<int, InstalledEquipment>
     */
    public function record(Project $project, array $rows, string $door): Collection
    {
        // A form hands the type back as the enum or its value, depending on how the row was filled.
        $rows = array_map(fn (array $row): array => [...$row, 'type' => $row['type'] instanceof EquipmentType ? $row['type'] : EquipmentType::from((string) $row['type'])], $rows);

        return DB::transaction(function () use ($project, $rows, $door): Collection {
            $created = collect();
            $doorRows = array_filter($rows, fn (array $row): bool => $row['type'] === EquipmentType::Door);
            $otherRows = array_filter($rows, fn (array $row): bool => $row['type'] !== EquipmentType::Door);

            foreach ($doorRows as $row) {
                $created->push($this->create($project, $row, null));
            }

            $parentId = match (true) {
                $door === 'new' => $created->first()?->getKey(),
                $door === 'none' => null,
                default => self::doors($project)->find((int) $door)?->getKey(),
            };

            foreach ($otherRows as $row) {
                $created->push($this->create($project, $row, $parentId));
            }

            return $created;
        });
    }

    /**
     * @param  array{type: EquipmentType, part_id?: int|string|null, manufacturer?: string|null, model?: string|null, serial?: string|null, size?: string|null, warranty_expires_on?: string|null, replaces?: string|null}  $row
     */
    private function create(Project $project, array $row, ?int $parentId): InstalledEquipment
    {
        $equipment = InstalledEquipment::query()->create([
            'property_id' => $project->property_id,
            'parent_id' => $parentId,
            'type' => $row['type'],
            'part_id' => $row['part_id'] ?? null,
            'manufacturer' => $row['manufacturer'] ?? null,
            'model' => $row['model'] ?? null,
            'serial' => $row['serial'] ?? null,
            'size' => $row['size'] ?? null,
            'installed_on' => self::installedOn($project)->toDateString(),
            'installed_by_project_id' => $project->getKey(),
            'warranty_expires_on' => $row['warranty_expires_on'] ?? null,
        ]);

        $replaced = filled($row['replaces'] ?? null)
            ? self::replaceable($project, $row['type'])->find((int) $row['replaces'])
            : null;

        $replaced?->replaceWith($equipment);

        return $equipment;
    }

    private static function installedOn(Project $project): CarbonInterface
    {
        return ($project->completed_at ?? now())->clone()->timezone(config('app.business_timezone'))->startOfDay();
    }
}
