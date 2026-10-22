<?php

namespace App\Models;

use App\Enums\PageKey;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Editable copy for one of the fixed pages in PageKey.
 */
#[Fillable(['key', 'title', 'intro', 'body', 'seo_title', 'seo_description'])]
class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use HasFactory;

    public static function for(PageKey $key): self
    {
        return self::query()->where('key', $key)->firstOrFail();
    }

    protected function casts(): array
    {
        return [
            'key' => PageKey::class,
        ];
    }
}
