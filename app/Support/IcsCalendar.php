<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Writes an iCalendar (RFC 5545) document: CRLF line endings, text escaped, and long lines
 * folded at 75 bytes without splitting a UTF-8 character.
 */
final class IcsCalendar
{
    /**
     * @var list<string>
     */
    private array $lines = [];

    /**
     * @param  array<string, string>  $properties  Calendar properties, such as X-WR-CALNAME, already in iCalendar form.
     */
    public function __construct(string $productId, array $properties = [])
    {
        $this->lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:'.$productId, 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH'];

        foreach ($properties as $name => $value) {
            $this->lines[] = "{$name}:{$value}";
        }
    }

    /**
     * @param  array{uid: string, starts: CarbonInterface, ends: CarbonInterface, summary: string, status?: 'TENTATIVE'|'CONFIRMED', location?: string|null, description?: string|null, url?: string|null, modified?: CarbonInterface|null}  $event
     */
    public function addEvent(array $event): self
    {
        $lines = [
            'BEGIN:VEVENT',
            'UID:'.self::text($event['uid']),
            'DTSTAMP:'.self::time(now()),
            'DTSTART:'.self::time($event['starts']),
            'DTEND:'.self::time($event['ends']),
            'SUMMARY:'.self::text($event['summary']),
        ];

        if (isset($event['status'])) {
            $lines[] = 'STATUS:'.$event['status'];
        }

        if (filled($event['location'] ?? null)) {
            $lines[] = 'LOCATION:'.self::text((string) $event['location']);
        }

        if (filled($event['description'] ?? null)) {
            $lines[] = 'DESCRIPTION:'.self::text((string) $event['description']);
        }

        if (filled($event['url'] ?? null)) {
            $lines[] = 'URL:'.$event['url'];
        }

        if (isset($event['modified'])) {
            $lines[] = 'LAST-MODIFIED:'.self::time($event['modified']);
        }

        $lines[] = 'END:VEVENT';
        array_push($this->lines, ...$lines);

        return $this;
    }

    public function toString(): string
    {
        return implode('', array_map(fn (string $line): string => self::fold($line)."\r\n", [...$this->lines, 'END:VCALENDAR']));
    }

    /**
     * A TEXT value: backslashes, semicolons, and commas escaped, newlines as \n.
     */
    public static function text(string $value): string
    {
        return str_replace(["\r\n", "\r", "\n"], '\n', addcslashes($value, '\\;,'));
    }

    /**
     * A UTC DATE-TIME, like 20261028T130000Z.
     */
    public static function time(CarbonInterface $moment): string
    {
        return $moment->clone()->utc()->format('Ymd\THis\Z');
    }

    /**
     * Splits a line into 75-byte pieces, each continuation starting with a space.
     */
    public static function fold(string $line): string
    {
        $pieces = [];
        $piece = '';
        $limit = 75;

        foreach (mb_str_split($line) as $character) {
            if (strlen($piece) + strlen($character) > $limit) {
                $pieces[] = $piece;
                $piece = '';
                // A continuation's leading space counts toward its 75 bytes.
                $limit = 74;
            }

            $piece .= $character;
        }

        $pieces[] = $piece;

        return implode("\r\n ", $pieces);
    }
}
