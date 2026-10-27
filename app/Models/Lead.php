<?php

namespace App\Models;

use App\Enums\ContactMethod;
use App\Enums\LeadStatus;
use App\Enums\Urgency;
use App\Support\QuoteQuestions;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A quote request from the website. Its photos stay on the private disk, and so do
 * their thumbnails: only the owner sees them, through temporary URLs.
 */
#[Fillable([
    'status', 'service_id', 'urgency', 'answers', 'message', 'name', 'email', 'phone', 'preferred_contact',
    'street', 'town', 'zip', 'service_area_id', 'attribution', 'sms_consent', 'marketing_consent', 'ip_address',
    'contacted_at',
])]
class Lead extends Model implements HasMedia
{
    /** @use HasFactory<LeadFactory> */
    use HasFactory, InteractsWithMedia;

    /** The most photos one request can carry. */
    public const int MAX_PHOTOS = 6;

    /**
     * The largest photo the site takes: the media library's own limit, so a photo the quote
     * form accepts is never refused when the job attaches it.
     */
    public static function maxPhotoKilobytes(): int
    {
        return intdiv((int) config('media-library.max_file_size'), 1024);
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return BelongsTo<ServiceArea, $this>
     */
    public function serviceArea(): BelongsTo
    {
        return $this->belongsTo(ServiceArea::class);
    }

    /**
     * The project it became, once converted.
     *
     * @return HasOne<Project, $this>
     */
    public function project(): HasOne
    {
        return $this->hasOne(Project::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos')->useDisk('private');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->performOnCollections('photos')
            ->fit(Fit::Max, 640, 640)
            ->format('webp')
            ->quality(75);
    }

    /**
     * Other requests from the same person, matched on email or phone, newest first.
     *
     * @return Builder<self>
     */
    public function earlierRequests(): Builder
    {
        return self::query()
            ->whereKeyNot($this->getKey())
            ->where(fn (Builder $query) => $query
                ->whereRaw('lower(email) = ?', [$this->email])
                ->orWhere('phone', $this->phone))
            ->latest();
    }

    /**
     * The first name, for greeting them in an email, or null when it doesn't look like a name
     * (a link, say), so nobody can use the form to put their own text in our emails.
     */
    public function greetingName(): ?string
    {
        $first = Str::before(trim($this->name), ' ');

        return preg_match("/^\\p{L}[\\p{L}'’-]{0,29}$/u", $first) === 1 ? $first : null;
    }

    /**
     * @return list<string>
     */
    public function answerLines(): array
    {
        return QuoteQuestions::summaryLines($this->answers);
    }

    /**
     * Stored lowercase, so earlier requests match regardless of case.
     *
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(set: fn (string $value): string => mb_strtolower(trim($value)));
    }

    protected function casts(): array
    {
        return [
            'status' => LeadStatus::class,
            'urgency' => Urgency::class,
            'preferred_contact' => ContactMethod::class,
            'answers' => 'array',
            'attribution' => 'array',
            'sms_consent' => 'boolean',
            'marketing_consent' => 'boolean',
            'contacted_at' => 'datetime',
        ];
    }
}
