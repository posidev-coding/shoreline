<?php

namespace App\Support;

/**
 * The "Is your door hurricane-ready?" check. It can't say whether a door meets code
 * at an address (only the local building official can), only whether a closer look
 * is worth it.
 */
final class HurricaneReadiness
{
    /**
     * Each answer's weight toward "worth a closer look".
     *
     * @var array<string, array{question: string, help?: string, options: array<string, array{label: string, points: int}>}>
     */
    public const array QUESTIONS = [
        'age' => [
            'question' => 'How old is your garage door?',
            'options' => [
                'under-10' => ['label' => 'Under 10 years', 'points' => 0],
                '10-20' => ['label' => '10 to 20 years', 'points' => 1],
                'over-20' => ['label' => 'Over 20 years', 'points' => 2],
                'not-sure' => ['label' => 'Not sure', 'points' => 1],
            ],
        ],
        'label' => [
            'question' => 'Can you find a wind-load label on the door?',
            'help' => 'Wind-rated doors usually carry a sticker on an end stile or near the track, inside the garage, listing design pressures.',
            'options' => [
                'yes' => ['label' => 'Yes', 'points' => 0],
                'no' => ['label' => 'No', 'points' => 2],
                'not-sure' => ['label' => 'Not sure', 'points' => 1],
            ],
        ],
        'windows' => [
            'question' => 'Does the door have windows?',
            'options' => [
                'none' => ['label' => 'No windows', 'points' => 0],
                'impact' => ['label' => 'Yes, impact-rated glass', 'points' => 0],
                'standard' => ['label' => 'Yes, regular glass', 'points' => 2],
                'not-sure' => ['label' => 'Not sure', 'points' => 1],
            ],
        ],
        'bracing' => [
            'question' => 'Is the door reinforced with struts or wind braces?',
            'help' => 'Horizontal metal struts across the sections, or vertical posts that brace the door in place.',
            'options' => [
                'yes' => ['label' => 'Yes', 'points' => 0],
                'no' => ['label' => 'No', 'points' => 1],
                'not-sure' => ['label' => 'Not sure', 'points' => 1],
            ],
        ],
        'service' => [
            'question' => 'When were the springs, rollers, and hardware last serviced?',
            'options' => [
                'within-2' => ['label' => 'Within the last 2 years', 'points' => 0],
                'over-2' => ['label' => 'More than 2 years ago', 'points' => 1],
                'never' => ['label' => 'Never, or I don’t know', 'points' => 2],
            ],
        ],
        'condition' => [
            'question' => 'Any dents, bent track, or rusty hardware?',
            'options' => [
                'none' => ['label' => 'None that I can see', 'points' => 0],
                'some' => ['label' => 'Yes, some', 'points' => 2],
                'not-sure' => ['label' => 'Not sure', 'points' => 1],
            ],
        ],
    ];

    /** Fewer answers than this aren't enough to say anything. */
    public const int MINIMUM_ANSWERS = 3;

    /**
     * @param  array<string, string>  $answers  Question key => option key, only valid pairs.
     */
    private function __construct(public readonly array $answers) {}

    /**
     * Keeps only answers that match a question and one of its options.
     *
     * @param  array<array-key, mixed>  $input
     */
    public static function fromInput(array $input): self
    {
        $answers = [];

        foreach (self::QUESTIONS as $key => $question) {
            $value = $input[$key] ?? null;

            if (is_string($value) && array_key_exists($value, $question['options'])) {
                $answers[$key] = $value;
            }
        }

        return new self($answers);
    }

    public function hasResult(): bool
    {
        return count($this->answers) >= self::MINIMUM_ANSWERS;
    }

    public function score(): int
    {
        $score = 0;

        foreach ($this->answers as $key => $option) {
            $score += self::QUESTIONS[$key]['options'][$option]['points'];
        }

        return $score;
    }

    /**
     * @return 'ready'|'check'|'inspect'
     */
    public function level(): string
    {
        return match (true) {
            $this->score() <= 2 => 'ready',
            $this->score() <= 5 => 'check',
            default => 'inspect',
        };
    }

    public function headline(): string
    {
        return match ($this->level()) {
            'ready' => 'Your door sounds like it’s in good shape.',
            'check' => 'Worth a check before storm season.',
            'inspect' => 'Have someone look at it before the next storm.',
        };
    }

    public function advice(): string
    {
        return match ($this->level()) {
            'ready' => 'Keep it serviced so it stays that way. A once-a-year tune-up catches salt-air wear before it matters.',
            'check' => 'Some of your answers point to things a technician should look at: the rating, the hardware, or both.',
            'inspect' => 'Several answers suggest the door may not hold up well in high wind. An inspection will tell you what it needs.',
        };
    }

    /**
     * One line per answer, for a quote request or an email.
     *
     * @return list<string>
     */
    public function summaryLines(): array
    {
        $lines = [];

        foreach ($this->answers as $key => $option) {
            $lines[] = self::QUESTIONS[$key]['question'].' '.self::QUESTIONS[$key]['options'][$option]['label'];
        }

        return $lines;
    }
}
