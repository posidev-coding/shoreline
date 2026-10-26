<?php

namespace App\Models;

use App\Enums\VisitStatus;
use App\Enums\VisitType;
use Database\Factories\VisitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A trip to the property with the arrival window promised to the client, shown on the calendar.
 */
#[Fillable(['project_id', 'type', 'status', 'starts_at', 'ends_at', 'notes'])]
class Visit extends Model
{
    /** @use HasFactory<VisitFactory> */
    use HasFactory, LogsActivity;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'tentative',
    ];

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * "Tue, Oct 28 · 8–10 AM" in the business's time zone, or with both times when the
     * window crosses noon or runs into another day.
     */
    public function window(): string
    {
        $timezone = config('app.business_timezone');
        $starts = $this->starts_at->clone()->setTimezone($timezone);
        $ends = $this->ends_at->clone()->setTimezone($timezone);

        $time = fn ($moment): string => $moment->format($moment->minute === 0 ? 'g' : 'g:i');

        if (! $starts->isSameDay($ends)) {
            return $starts->format('D, M j · g:i A').' to '.$ends->format('D, M j · g:i A');
        }

        $range = $starts->format('A') === $ends->format('A')
            ? $time($starts).'–'.$time($ends).' '.$ends->format('A')
            : $time($starts).' '.$starts->format('A').'–'.$time($ends).' '.$ends->format('A');

        return $starts->format('D, M j').' · '.$range;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }

    protected function casts(): array
    {
        return [
            'type' => VisitType::class,
            'status' => VisitStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }
}
