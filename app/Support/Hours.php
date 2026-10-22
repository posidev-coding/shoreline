<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Formats the opening hours rows from SiteSettings for people (the JSON-LD uses the raw values).
 */
final class Hours
{
    private const array DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    /**
     * "Monday–Friday", "Saturday", or "Monday, Wednesday" for days that don't run in a row.
     *
     * @param  list<string>  $days
     */
    public static function days(array $days): string
    {
        $indexes = array_keys(array_intersect(self::DAYS, $days));

        if ($indexes === []) {
            return '';
        }

        $first = $indexes[0];
        $last = $indexes[count($indexes) - 1];
        $isRun = count($indexes) > 2 && $last - $first === count($indexes) - 1;

        return $isRun
            ? self::DAYS[$first].'–'.self::DAYS[$last]
            : implode(', ', array_map(fn (int $index): string => self::DAYS[$index], $indexes));
    }

    /**
     * "08:00" becomes "8 AM"; "17:30" becomes "5:30 PM".
     */
    public static function time(string $time): string
    {
        $parsed = Carbon::createFromFormat('H:i', $time);

        if ($parsed === null) {
            return $time;
        }

        return $parsed->format($parsed->minute === 0 ? 'g A' : 'g:i A');
    }
}
