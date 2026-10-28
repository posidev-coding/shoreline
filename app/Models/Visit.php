<?php

namespace App\Models;

use App\Enums\VisitStatus;
use App\Enums\VisitType;
use App\Models\Concerns\BelongsToProject;
use App\Support\ClientNotices;
use Carbon\CarbonInterface;
use Database\Factories\VisitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A trip to the property with the arrival window promised to the client, shown on the calendar.
 * The notified_* columns hold the window the client was last emailed; App\Support\ClientNotices
 * writes them, never a form.
 *
 * @property-read Project $project
 */
#[Fillable(['project_id', 'type', 'status', 'starts_at', 'ends_at', 'notes'])]
class Visit extends Model
{
    /** @use HasFactory<VisitFactory> */
    use BelongsToProject, HasFactory, LogsActivity;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'tentative',
    ];

    /**
     * "Tue, Oct 28 · 8–10 AM" in the business's time zone, or with both times when the
     * window crosses noon or runs into another day.
     */
    public function window(): string
    {
        return self::formatWindow($this->starts_at, $this->ends_at);
    }

    /**
     * The same, for any two moments, like the window a client was last told.
     */
    public static function formatWindow(CarbonInterface $startsAt, CarbonInterface $endsAt): string
    {
        $starts = $startsAt->clone()->setTimezone(config('app.business_timezone'));
        $ends = $endsAt->clone()->setTimezone(config('app.business_timezone'));

        if (! $starts->isSameDay($ends)) {
            return $starts->format('D, M j · g:i A').' to '.$ends->format('D, M j · g:i A');
        }

        return $starts->format('D, M j').' · '.self::formatTimes($starts, $ends);
    }

    /**
     * The window's times alone, "8–10 AM" or "11 AM–1 PM", for a list already headed by the day.
     * A window that runs into another day keeps its dates.
     */
    public function times(): string
    {
        $starts = $this->starts_at->clone()->setTimezone(config('app.business_timezone'));
        $ends = $this->ends_at->clone()->setTimezone(config('app.business_timezone'));

        if (! $starts->isSameDay($ends)) {
            return $this->window();
        }

        return self::formatTimes($starts, $ends);
    }

    /**
     * "8–10 AM" for two moments on the same day, already in the business's time zone.
     */
    private static function formatTimes(CarbonInterface $starts, CarbonInterface $ends): string
    {
        $time = fn (CarbonInterface $moment): string => $moment->format($moment->minute === 0 ? 'g' : 'g:i');

        return $starts->format('A') === $ends->format('A')
            ? $time($starts).'–'.$time($ends).' '.$ends->format('A')
            : $time($starts).' '.$starts->format('A').'–'.$time($ends).' '.$ends->format('A');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }

    protected static function booted(): void
    {
        // The client hears about a confirmed visit, a move, and one called off after they were told.
        static::saved(fn (self $visit) => ClientNotices::visitSaved($visit));
        static::deleted(fn (self $visit) => ClientNotices::visitDeleted($visit));
    }

    protected function casts(): array
    {
        return [
            'type' => VisitType::class,
            'status' => VisitStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'client_notified_at' => 'datetime',
            'notified_starts_at' => 'datetime',
            'notified_ends_at' => 'datetime',
        ];
    }
}
