<?php

namespace App\Filament\Resources\Parts\Pages;

use App\Filament\Resources\Parts\PartResource;
use App\Models\Part;
use App\Models\Project;
use App\Support\Stock;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * @property Part $record
 */
class ViewPart extends ViewRecord
{
    protected static string $resource = PartResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->getRecordTitle();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('receive')
                ->label('Receive')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->visible(fn (Part $record): bool => $record->track_stock)
                ->modalHeading('Receive stock')
                ->modalWidth('md')
                ->schema([
                    self::quantity('How many came in')->minValue(0.01),
                    TextInput::make('note')->placeholder('Invoice or order number')->maxLength(255),
                ])
                ->action(fn (Part $record, array $data) => $this->changed(app(Stock::class)->receive($record, (string) $data['quantity'], $data['note'] ?? null))),
            ActionGroup::make([
                Action::make('count')
                    ->label('Count stock')
                    ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                    ->modalDescription('Enter what’s on the shelf. The difference is recorded as an adjustment.')
                    ->modalWidth('md')
                    ->schema([
                        self::quantity('Counted on hand')->minValue(0),
                        TextInput::make('note')->maxLength(255),
                    ])
                    ->action(fn (Part $record, array $data) => $this->changed(app(Stock::class)->adjustTo($record, (string) $data['quantity'], $data['note'] ?? 'Counted'))),
                Action::make('returnFromJob')
                    ->label('Return from a job')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->modalWidth('md')
                    ->schema([
                        Select::make('project_id')
                            ->label('Job')
                            ->options(fn (): array => Project::query()->latest('updated_at')->limit(100)->pluck('title', 'id')->all())
                            ->searchable()
                            ->required(),
                        self::quantity('How many came back')->minValue(0.01),
                        TextInput::make('note')->maxLength(255),
                    ])
                    ->action(fn (Part $record, array $data) => $this->changed(app(Stock::class)->returnFromProject(
                        $record,
                        (string) $data['quantity'],
                        Project::query()->whereKey($data['project_id'])->firstOrFail(),
                        $data['note'] ?? null,
                    ))),
                Action::make('writeOff')
                    ->label('Write off')
                    ->icon(Heroicon::OutlinedMinusCircle)
                    ->modalDescription('For parts damaged, lost, or used outside a job.')
                    ->modalWidth('md')
                    ->schema([
                        self::quantity('How many')->minValue(0.01),
                        TextInput::make('note')->label('Why')->required()->maxLength(255),
                    ])
                    ->action(fn (Part $record, array $data) => $this->changed(app(Stock::class)->adjustBy($record, '-'.$data['quantity'], $data['note']))),
            ])
                ->label('Stock')
                ->icon(Heroicon::OutlinedCube)
                ->button()
                ->color('gray')
                ->visible(fn (): bool => $this->record->track_stock),
            EditAction::make(),
            ActionGroup::make([
                PartHistory::action(),
                DeleteAction::make()
                    ->visible(fn (Part $record): bool => ! $record->movements()->exists() && ! $record->lineItems()->exists() && ! $record->installedEquipment()->exists())
                    ->modalDescription('Only a part nothing uses can be deleted. Turn off "Still sold" to retire one that has history.'),
            ]),
        ];
    }

    private static function quantity(string $label): TextInput
    {
        return TextInput::make('quantity')
            ->label($label)
            ->numeric()
            ->step(0.01)
            ->required();
    }

    /**
     * Shows the new count, and refreshes the page's numbers.
     */
    private function changed(mixed $movement): void
    {
        $this->record->refresh();

        Notification::make()
            ->title($movement === null ? 'The count already matched' : 'On hand: '.$this->record->quantityLabel($this->record->on_hand))
            ->success()
            ->send();

        $this->dispatch('refresh-movements');
    }
}
