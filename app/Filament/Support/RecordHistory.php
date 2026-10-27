<?php

namespace App\Filament\Support;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use pxlrbt\FilamentActivityLog\Pages\ListActivitiesBySubject;
use Spatie\Activitylog\Models\Activity;

/**
 * A record's change history from the activity log: who changed which fields, and when, with
 * values as people read them (labels, names, and local time rather than codes and ids).
 *
 * The plugin's one-click "restore" is turned off. It writes a past version's values back
 * without a confirmation, and the access info is logged hidden, so restoring it would
 * overwrite the real codes.
 */
abstract class RecordHistory extends ListActivitiesBySubject
{
    protected string $view = 'filament.record-history';

    public function getTitle(): string
    {
        return 'History';
    }

    public function canRestoreActivity(): bool
    {
        return false;
    }

    /**
     * The view page's header action that opens this page.
     */
    public static function action(): Action
    {
        return Action::make('history')
            ->label('History')
            ->icon(Heroicon::OutlinedClock)
            ->color('gray')
            ->url(fn ($record): string => static::getResource()::getUrl('history', ['record' => $record]));
    }

    public function getFieldLabel(string $name): string
    {
        $label = parent::getFieldLabel($name);

        return $label === $name ? Str::headline(Str::beforeLast($name, '_id')) : $label;
    }

    /**
     * The changed fields with their before and after values, leaving out the empty fields of a
     * new record.
     *
     * @return list<array{field: string, old: string, new: string}>
     */
    protected function changes(Activity $activity): array
    {
        $old = $activity->attribute_changes?->get('old') ?? [];
        $new = $activity->attribute_changes?->get('attributes') ?? [];
        $rows = [];

        foreach (array_keys([...$old, ...$new]) as $field) {
            if (blank($old[$field] ?? null) && blank($new[$field] ?? null)) {
                continue;
            }

            $rows[] = [
                'field' => $this->getFieldLabel($field),
                'old' => $this->readable($field, $old[$field] ?? null),
                'new' => $this->readable($field, $new[$field] ?? null),
            ];
        }

        return $rows;
    }

    protected function when(Activity $activity): string
    {
        return $activity->created_at?->timezone(config('app.business_timezone'))->format('M j, Y, g:i A') ?? '';
    }

    private function readable(string $field, mixed $value): string
    {
        if (blank($value)) {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $record = $this->getRecord();
        $cast = $record->getCasts()[$field] ?? null;

        if (is_string($cast) && is_subclass_of($cast, BackedEnum::class)) {
            $label = $cast::tryFrom($value);
            $label = $label instanceof HasLabel ? $label->getLabel() : null;

            return is_string($label) ? $label : (string) $value;
        }

        $relation = Str::camel(Str::beforeLast($field, '_id'));

        if (str_ends_with($field, '_id') && method_exists($record, $relation) && $record->{$relation}() instanceof BelongsTo) {
            $related = $record->{$relation}()->getRelated()->newQueryWithoutScopes()->find($value);

            return $related instanceof Model ? self::title($related) : "#{$value}";
        }

        return (string) $value;
    }

    private static function title(Model $model): string
    {
        if (method_exists($model, 'address')) {
            return $model->address();
        }

        return (string) ($model->getAttribute('display_name') ?? $model->getAttribute('name') ?? $model->getAttribute('title') ?? '#'.$model->getKey());
    }
}
