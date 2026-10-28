<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Day and week boundaries as the owner counts them, in the business's time zone. Convert with
 * utc() before querying: the database stores UTC, and a query binding keeps the zone it's given.
 */
final class BusinessCalendar
{
    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(config('app.business_timezone'))->startOfDay();
    }

    /**
     * Monday at midnight, the same week the Schedule shows.
     */
    public static function weekStart(): CarbonImmutable
    {
        return self::today()->startOfWeek(CarbonInterface::MONDAY);
    }
}
