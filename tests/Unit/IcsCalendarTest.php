<?php

use App\Support\IcsCalendar;

it('escapes text values', function () {
    expect(IcsCalendar::text("Gate; side door, back\\left\nthen ring"))
        ->toBe('Gate\; side door\, back\\\\left\nthen ring');
});

it('folds long lines at 75 bytes without splitting a character', function () {
    $line = 'DESCRIPTION:'.str_repeat('é', 100);

    $folded = IcsCalendar::fold($line);
    $pieces = explode("\r\n", $folded);

    expect($pieces)->toHaveCount(3)
        ->and(collect($pieces)->every(fn (string $piece): bool => strlen($piece) <= 75 && mb_check_encoding($piece, 'UTF-8')))->toBeTrue()
        ->and(collect($pieces)->skip(1)->every(fn (string $piece): bool => str_starts_with($piece, ' ')))->toBeTrue()
        ->and(implode('', array_map(fn (string $piece): string => ltrim($piece, ' '), $pieces)))->toBe($line);
});

it('leaves short lines alone and ends every line with CRLF', function () {
    $document = (new IcsCalendar('-//Test//EN'))->toString();

    expect(IcsCalendar::fold('SUMMARY:Install'))->toBe('SUMMARY:Install')
        ->and($document)->toEndWith("END:VCALENDAR\r\n")
        ->and(substr_count($document, "\r\n"))->toBe(substr_count($document, "\n"));
});
