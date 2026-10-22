<?php

namespace App\Models;

use App\Enums\ReviewSource;
use Database\Factories\TestimonialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A real customer review the owner chose to show. Never seeded or written for the customer.
 */
#[Fillable(['quote', 'author_name', 'service_area_id', 'service_id', 'rating', 'source', 'source_url', 'reviewed_on', 'is_featured', 'is_published'])]
class Testimonial extends Model
{
    /** @use HasFactory<TestimonialFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<ServiceArea, $this>
     */
    public function serviceArea(): BelongsTo
    {
        return $this->belongsTo(ServiceArea::class);
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('is_published', true);
    }

    /**
     * Featured first, then newest.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderByDesc('is_featured')->orderByDesc('reviewed_on')->orderByDesc('id');
    }

    protected function casts(): array
    {
        return [
            'source' => ReviewSource::class,
            'rating' => 'integer',
            'reviewed_on' => 'date',
            'is_featured' => 'boolean',
            'is_published' => 'boolean',
        ];
    }
}
