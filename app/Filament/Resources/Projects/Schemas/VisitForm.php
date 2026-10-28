<?php

namespace App\Filament\Resources\Projects\Schemas;

use App\Enums\VisitStatus;
use App\Enums\VisitType;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Component;

/**
 * A visit's fields, on the project's Visits tab and on the Schedule. Times are entered and shown
 * in the business's time zone.
 */
class VisitForm
{
    /**
     * @return list<Component>
     */
    public static function components(): array
    {
        return [
            Select::make('type')
                ->options(VisitType::class)
                ->required(),
            Select::make('status')
                ->options(VisitStatus::class)
                ->default(VisitStatus::Tentative)
                ->helperText('Confirmed once the client has agreed to the time.')
                ->required(),
            DateTimePicker::make('starts_at')
                ->label('Arrive between')
                ->seconds(false)
                ->minutesStep(15)
                ->required()
                ->live(onBlur: true),
            DateTimePicker::make('ends_at')
                ->label('and')
                ->seconds(false)
                ->minutesStep(15)
                ->required()
                ->after('starts_at')
                ->validationMessages(['after' => 'The window has to end after it starts.']),
            Textarea::make('notes')
                ->rows(2)
                ->columnSpanFull(),
        ];
    }
}
