<?php

namespace App\Filament\Widgets;

use App\Enums\PageKey;
use App\Models\Page;
use App\Settings\SiteSettings;
use Filament\Widgets\Widget;

/**
 * What still needs real details before the site goes live. Hidden once everything's done.
 */
class LaunchChecklist extends Widget
{
    protected string $view = 'filament.widgets.launch-checklist';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -10;

    public static function canView(): bool
    {
        return self::items() !== [];
    }

    /**
     * @return list<string>
     */
    public static function items(): array
    {
        $settings = app(SiteSettings::class);
        $items = [];

        if ($settings->hasPlaceholderPhone()) {
            $items[] = 'The phone number is still the placeholder (252) 555-0100. Set the real one in Business details.';
        }

        if ($settings->hours === []) {
            $items[] = 'No business hours are set. The site asks people to call for a time until you add them.';
        }

        if (blank($settings->google_review_url)) {
            $items[] = 'Add your Google review link in Business details, so the Reviews page can ask for reviews.';
        }

        $drafts = Page::query()
            ->whereIn('key', [PageKey::Privacy, PageKey::Terms])
            ->where('body', 'like', '%DRAFT%')
            ->get()
            ->map(fn (Page $page): string => $page->title);

        if ($drafts->isNotEmpty()) {
            $items[] = 'Have the '.$drafts->join(' and ').' reviewed, then remove the DRAFT note at the top of each.';
        }

        return $items;
    }
}
