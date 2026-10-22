<?php

use App\Support\HurricaneReadiness;

it('needs at least three valid answers to say anything', function () {
    expect(HurricaneReadiness::fromInput(['age' => 'over-20', 'label' => 'no'])->hasResult())->toBeFalse()
        ->and(HurricaneReadiness::fromInput(['age' => 'over-20', 'label' => 'no', 'windows' => 'none'])->hasResult())->toBeTrue();
});

it('ignores unknown questions, unknown options, and non-string values', function () {
    $check = HurricaneReadiness::fromInput(['age' => 'ancient', 'label' => ['no'], 'roof' => 'flat', 'windows' => 'none']);

    expect($check->answers)->toBe(['windows' => 'none']);
});

it('rates a door from its answers', function (array $answers, string $level) {
    expect(HurricaneReadiness::fromInput($answers)->level())->toBe($level);
})->with([
    'newer, labeled, maintained' => [['age' => 'under-10', 'label' => 'yes', 'windows' => 'none', 'bracing' => 'yes', 'service' => 'within-2', 'condition' => 'none'], 'ready'],
    'some unknowns' => [['age' => '10-20', 'label' => 'not-sure', 'windows' => 'none', 'service' => 'over-2'], 'check'],
    'old, unlabeled, glass, worn' => [['age' => 'over-20', 'label' => 'no', 'windows' => 'standard', 'condition' => 'some'], 'inspect'],
]);

it('summarizes the answers as question-and-answer lines', function () {
    expect(HurricaneReadiness::fromInput(['age' => 'over-20', 'label' => 'no', 'windows' => 'impact'])->summaryLines())->toBe([
        'How old is your garage door? Over 20 years',
        'Can you find a wind-load label on the door? No',
        'Does the door have windows? Yes, impact-rated glass',
    ]);
});
