<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Trashing a record trashes its children with the same timestamp, and restoring it brings back
 * only those, not children trashed on their own before. Deleting it for good deletes its children
 * one by one, so their own cleanup (photos, further children) runs too.
 *
 * @phpstan-require-extends Model
 */
trait CascadesSoftDeletes
{
    /**
     * The HasMany relations to cascade to, children of soft-deletable models.
     *
     * @return list<string>
     */
    abstract protected function softDeleteCascades(): array;

    protected static function bootCascadesSoftDeletes(): void
    {
        static::deleted(function (self $model): void {
            if ($model->isForceDeleting()) {
                return;
            }

            foreach ($model->softDeleteCascades() as $relation) {
                $model->cascadingRelation($relation)->update(['deleted_at' => $model->deleted_at]);
            }
        });

        static::restoring(function (self $model): void {
            foreach ($model->softDeleteCascades() as $relation) {
                /** @phpstan-ignore method.notFound (the children use SoftDeletes) */
                $model->cascadingRelation($relation)->onlyTrashed()->where('deleted_at', $model->deleted_at)->get()->each->restore();
            }
        });

        static::forceDeleting(function (self $model): void {
            foreach ($model->softDeleteCascades() as $relation) {
                /** @phpstan-ignore method.notFound (the children use SoftDeletes) */
                $model->cascadingRelation($relation)->withTrashed()->get()->each->forceDelete();
            }
        });
    }

    /**
     * @return HasMany<Model, $this>
     */
    private function cascadingRelation(string $relation): HasMany
    {
        return $this->{$relation}();
    }
}
