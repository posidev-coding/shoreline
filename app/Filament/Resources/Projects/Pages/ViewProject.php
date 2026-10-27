<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Enums\EquipmentType;
use App\Filament\Resources\Projects\Actions\ChangeStageAction;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\Properties\PropertyResource;
use App\Models\InstalledEquipment;
use App\Models\Project;
use App\Support\EquipmentRecorder;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
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
            $this->recordEquipmentAction(),
            ChangeStageAction::make(fn () => $this->replaceMountedAction('recordEquipment')),
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

    private static function listsADoor(mixed $rows): bool
    {
        return is_array($rows) && collect($rows)->contains(fn (mixed $row): bool => is_array($row) && in_array($row['type'] ?? null, [EquipmentType::Door, EquipmentType::Door->value], true));
    }

    /**
     * Records what the completed job installed at the property, starting from its line items.
     * Offered right after the job is completed, and from the page any time after.
     */
    private function recordEquipmentAction(): Action
    {
        return Action::make('recordEquipment')
            ->label('Record installed equipment')
            ->icon(Heroicon::OutlinedClipboardDocumentList)
            ->color(fn (Project $record): string => $record->installedEquipment()->exists() ? 'gray' : 'primary')
            ->visible(fn (Project $record): bool => $record->completed_at !== null)
            ->modalHeading('Record what this job installed')
            ->modalDescription(fn (Project $record): string => 'It’s added to the equipment at '.$record->property->address().', so the next call starts with what’s on the door.')
            ->modalSubmitActionLabel('Record')
            ->modalWidth('4xl')
            ->fillForm(function (Project $record): array {
                $rows = EquipmentRecorder::draft($record);

                return ['door' => EquipmentRecorder::suggestedDoor($record, $rows), 'rows' => $rows];
            })
            ->schema(fn (Project $record): array => [
                Radio::make('door')
                    ->label('The opener, springs, and the rest go on')
                    // Joined with +, which keeps the doors' numeric keys that spreading would renumber.
                    ->options(fn (Get $get): array => (self::listsADoor($get('rows')) ? ['new' => 'The new door in this list'] : [])
                        + EquipmentRecorder::doors($record)->mapWithKeys(fn (InstalledEquipment $door): array => [$door->getKey() => 'The door already there: '.$door->title()])->all()
                        + ['none' => 'No door'])
                    ->required(),
                Repeater::make('rows')
                    ->label('Equipment')
                    ->addActionLabel('Add something else')
                    ->columns(4)
                    ->live()
                    ->schema([
                        Hidden::make('part_id'),
                        Select::make('type')
                            ->options(EquipmentType::class)
                            ->required()
                            ->live(),
                        TextInput::make('manufacturer')
                            ->maxLength(255),
                        TextInput::make('model')
                            ->maxLength(255),
                        TextInput::make('serial')
                            ->maxLength(255),
                        TextInput::make('size')
                            ->placeholder('16x7')
                            ->maxLength(20)
                            ->visible(fn (Get $get): bool => $get('type') === EquipmentType::Door->value || $get('type') === EquipmentType::Door),
                        DatePicker::make('warranty_expires_on')
                            ->label('Warranty until')
                            ->native(false),
                        Select::make('replaces')
                            ->label('Replaces')
                            ->placeholder('Nothing')
                            ->options(function (Get $get) use ($record): array {
                                $type = $get('type') instanceof EquipmentType ? $get('type') : EquipmentType::tryFrom((string) $get('type'));

                                return $type === null ? [] : EquipmentRecorder::replaceable($record, $type)
                                    ->mapWithKeys(fn (InstalledEquipment $old): array => [(string) $old->getKey() => $old->title().($old->installed_on ? ', from '.$old->installed_on->format('Y') : '')])
                                    ->all();
                            })
                            ->columnSpan(2),
                    ]),
            ])
            ->action(function (Project $record, array $data): void {
                $created = app(EquipmentRecorder::class)->record($record, array_values($data['rows'] ?? []), (string) $data['door']);

                Notification::make()
                    ->title($created->count() === 1 ? 'Recorded 1 piece of equipment' : "Recorded {$created->count()} pieces of equipment")
                    ->body('They’re on the property’s Equipment tab.')
                    ->actions([
                        Action::make('open')
                            ->label('Open the property')
                            ->url(PropertyResource::getUrl('view', ['record' => $record->property_id])),
                    ])
                    ->success()
                    ->send();
            });
    }
}
