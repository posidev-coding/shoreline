<?php

namespace App\Support;

use App\Enums\VisitStatus;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Str;
use Propaganistas\LaravelPhone\PhoneNumber;
use Throwable;

/**
 * Each admin's private calendar feed of visits, to subscribe to in a calendar app. The secret
 * in its URL is stored only as a hash, so the URL is shown once, when it's made. It carries
 * names, addresses, phone numbers, and visit notes, never a property's access info.
 */
final class VisitFeed
{
    /**
     * How far back the feed goes. Everything ahead is in it.
     */
    public const int DAYS_BACK = 60;

    /**
     * Makes a new link for the user, replacing any earlier one.
     */
    public static function issue(User $user): string
    {
        $token = Str::random(48);

        $user->forceFill(['calendar_feed_token' => hash('sha256', $token), 'calendar_feed_read_at' => null])->save();

        return route('feeds.visits', ['token' => $token]);
    }

    public static function revoke(User $user): void
    {
        $user->forceFill(['calendar_feed_token' => null, 'calendar_feed_read_at' => null])->save();
    }

    /**
     * Whose feed the token opens, as long as they can still sign in to the admin.
     */
    public static function owner(string $token): ?User
    {
        $user = User::query()->where('calendar_feed_token', hash('sha256', $token))->first();

        return $user?->role->canAccessAdmin() ? $user : null;
    }

    public static function render(): string
    {
        $calendar = new IcsCalendar('-//Shoreline Doors//Visits//EN', [
            'X-WR-CALNAME' => IcsCalendar::text(config('app.name').' visits'),
            'X-WR-TIMEZONE' => config('app.business_timezone'),
            // Apple Calendar and Outlook honor these; Google Calendar keeps its own pace of several hours.
            'REFRESH-INTERVAL;VALUE=DURATION' => 'PT1H',
            'X-PUBLISHED-TTL' => 'PT1H',
        ]);

        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'shorelinedoors.com';

        $visits = Visit::query()
            ->withoutTrashedProjects()
            ->with(['project.client', 'project.property'])
            ->where('ends_at', '>=', now()->subDays(self::DAYS_BACK))
            ->orderBy('starts_at')
            ->get();

        foreach ($visits as $visit) {
            $project = $visit->project;
            $property = $project->property;

            $calendar->addEvent([
                'uid' => "visit-{$visit->getKey()}@{$host}",
                'starts' => $visit->starts_at,
                'ends' => $visit->ends_at,
                'summary' => $visit->type->getLabel().' · '.$project->client->display_name.($visit->status === VisitStatus::Tentative ? ' (tentative)' : ''),
                'status' => $visit->status === VisitStatus::Tentative ? 'TENTATIVE' : 'CONFIRMED',
                'location' => $property->address(),
                'description' => collect([
                    $project->title,
                    self::phone($project->client->phone),
                    $property->site_contact_name || $property->site_contact_phone
                        ? 'On site: '.collect([$property->site_contact_name, self::phone($property->site_contact_phone)])->filter()->implode(', ')
                        : null,
                    $visit->notes,
                    ProjectResource::getUrl('view', ['record' => $project], panel: 'admin'),
                ])->filter()->implode("\n"),
                'url' => ProjectResource::getUrl('view', ['record' => $project], panel: 'admin'),
                'modified' => $visit->updated_at,
            ]);
        }

        return $calendar->toString();
    }

    private static function phone(?string $phone): ?string
    {
        if (blank($phone)) {
            return null;
        }

        try {
            return (new PhoneNumber($phone, 'US'))->formatNational();
        } catch (Throwable) {
            return $phone;
        }
    }
}
