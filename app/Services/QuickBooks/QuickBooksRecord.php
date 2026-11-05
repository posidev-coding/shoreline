<?php

namespace App\Services\QuickBooks;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Reading the fields every QuickBooks record shares, the same way for each mirror.
 */
final class QuickBooksRecord
{
    /**
     * MetaData.LastUpdatedTime, in UTC.
     *
     * @param  array<string, mixed>  $record
     */
    public static function updatedAt(array $record): ?Carbon
    {
        $updated = data_get($record, 'MetaData.LastUpdatedTime');

        return is_string($updated) ? Carbon::parse($updated)->utc() : null;
    }

    /**
     * Whether a record is older than the copy already stored. Two pulls of one record can finish
     * out of order, and the older must not overwrite the newer.
     *
     * @param  array<string, mixed>  $record
     */
    public static function isOlderThan(array $record, ?CarbonInterface $stored): bool
    {
        $updated = self::updatedAt($record);

        return $updated !== null && $stored !== null && $updated->lt($stored);
    }

    /**
     * A QuickBooks amount as the two-decimal string a numeric(12,2) column holds.
     */
    public static function money(mixed $amount): string
    {
        return (string) BigDecimal::of(is_numeric($amount) ? (string) $amount : '0')->toScale(2, RoundingMode::HalfUp);
    }
}
