<?php

use App\Support\Hours;

it('names a run of three or more days as a range', function () {
    expect(Hours::days(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday']))->toBe('Monday–Friday');
});

it('lists days that aren’t a run', function () {
    expect(Hours::days(['Saturday', 'Monday']))->toBe('Monday, Saturday')
        ->and(Hours::days(['Saturday', 'Sunday']))->toBe('Saturday, Sunday');
});

it('formats times for people', function () {
    expect(Hours::time('08:00'))->toBe('8 AM')
        ->and(Hours::time('17:30'))->toBe('5:30 PM');
});
