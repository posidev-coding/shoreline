<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Money\Context\CustomContext;
use Brick\Money\Money;

/**
 * Dollar amounts for people: "$1,234.50", and rates with their extra precision only when they use it.
 */
final class Usd
{
    public static function format(Money $amount): string
    {
        return $amount->formatToLocale('en_US');
    }

    /**
     * "$12.50", or "$0.4375" for a rate that needs the extra places.
     */
    public static function rate(string $rate): string
    {
        $scale = max(2, BigDecimal::of($rate)->strippedOfTrailingZeros()->getScale());

        return Money::of($rate, 'USD', new CustomContext($scale))->formatToLocale('en_US');
    }
}
