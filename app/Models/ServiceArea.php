<?php

namespace App\Models;

use App\Enums\County;
use Database\Factories\ServiceAreaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A town the business serves. Its page shows that town's own jobs and reviews.
 */
#[Fillable(['name', 'slug', 'county', 'intro', 'seo_title', 'seo_description', 'sort_order', 'is_published'])]
class ServiceArea extends Model
{
    /** @use HasFactory<ServiceAreaFactory> */
    use HasFactory;

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return HasMany<Showcase, $this>
     */
    public function showcases(): HasMany
    {
        return $this->hasMany(Showcase::class);
    }

    /**
     * @return HasMany<Testimonial, $this>
     */
    public function testimonials(): HasMany
    {
        return $this->hasMany(Testimonial::class);
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
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }

    protected function casts(): array
    {
        return [
            'county' => County::class,
            'sort_order' => 'integer',
            'is_published' => 'boolean',
        ];
    }
}
