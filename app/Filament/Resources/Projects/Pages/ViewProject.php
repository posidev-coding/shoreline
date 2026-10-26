<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Enums\ProjectStage;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\Projects\Schemas\ProjectForm;
use App\Models\Project;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * @property Project $record
 */
class ViewProject extends ViewRecord
{
    protected static string $resource = ProjectResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->getRecordTitle();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('changeStage')
                ->label('Change stage')
                ->icon(Heroicon::OutlinedArrowRightCircle)
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
                ->action(fn (Project $record, array $data) => $record->update($data)),
            EditAction::make(),
            ActionGroup::make([
                ProjectHistory::action(),
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make()
                    ->modalDescription('This deletes the project and its photos for good.'),
            ]),
        ];
    }
}
