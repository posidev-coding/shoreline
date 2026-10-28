<?php

namespace App\Filament\Resources\Projects\Actions;

use App\Enums\ProjectStage;
use App\Filament\Resources\Projects\Schemas\ProjectForm;
use App\Models\Project;
use App\Support\EquipmentRecorder;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;

/**
 * Moves a job to any stage, asking why when it's lost. Used on the project's page and on the
 * board's cards, where it's also the way to reach the stages the board has no column for.
 */
final class ChangeStageAction
{
    /**
     * @param  Closure(Project): void  $offerToRecordEquipment  Runs when the move completes a job that installed something to record.
     */
    public static function make(Closure $offerToRecordEquipment): Action
    {
        return Action::make('changeStage')
            ->label('Change stage')
            ->icon(Heroicon::OutlinedArrowRightCircle)
            ->authorize('update')
            ->fillForm(fn (Project $record): array => ['stage' => $record->stage, 'lost_reason' => $record->lost_reason])
            ->schema([
                Select::make('stage')
                    ->options(ProjectStage::class)
                    ->required()
                    ->live(),
                Textarea::make('lost_reason')
                    ->label('Why it was lost')
                    ->rows(2)
                    ->visible(fn (Get $get): bool => ProjectForm::stage($get) === ProjectStage::Lost)
                    ->required(fn (Get $get): bool => ProjectForm::stage($get) === ProjectStage::Lost),
            ])
            ->modalWidth('md')
            ->action(function (Project $record, array $data) use ($offerToRecordEquipment): void {
                $record->update($data);

                // Finishing a job is when to record what it installed, while it's fresh.
                if ($record->wasChanged('completed_at') && $record->completed_at !== null && EquipmentRecorder::hasSomethingToRecord($record)) {
                    $offerToRecordEquipment($record);
                }
            });
    }
}
