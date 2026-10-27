<?php

namespace App\Filament\Resources\Properties\RelationManagers;

use App\Enums\EquipmentStatus;
use App\Enums\EquipmentType;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\InstalledEquipment;
use App\Models\Part;
use App\Models\Property;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * What's installed at the property, as a tree: each door with its springs, opener, and
 * hardware listed under it.
 *
 * @property Property $ownerRecord
 */
class EquipmentRelationManager extends RelationManager
{
    protected static string $relationship = 'equipment';

    protected static ?string $title = 'Equipment';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Select::make('type')
                    ->options(EquipmentType::class)
                    ->default(EquipmentType::Door)
                    ->required()
                    ->live(),
                Select::make('parent_id')
                    ->label('On which door')
                    ->options(fn (?InstalledEquipment $record): array => $this->ownerRecord->equipment()
                        ->where('type', EquipmentType::Door)
                        ->when($record, fn (Builder $query) => $query->whereKeyNot($record?->getKey()))
                        ->get()
                        ->mapWithKeys(fn (InstalledEquipment $door): array => [$door->getKey() => $door->title()])
                        ->all())
                    ->placeholder('Not on a door')
                    ->visible(fn (Get $get): bool => self::type($get) !== EquipmentType::Door),
                Select::make('part_id')
                    ->label('From the catalog')
                    ->relationship('part', 'name')
                    ->searchable(['name', 'manufacturer', 'model'])
                    ->live()
                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                        $part = filled($state) ? Part::query()->find($state) : null;

                        if ($part !== null) {
                            $set('manufacturer', $part->manufacturer);
                            $set('model', $part->model);
                        }
                    }),
                TextInput::make('manufacturer')
                    ->maxLength(255),
                TextInput::make('model')
                    ->maxLength(255),
                TextInput::make('serial')
                    ->maxLength(255),
                Section::make('Door')
                    ->columns(3)
                    ->columnSpanFull()
                    ->visible(fn (Get $get): bool => self::type($get) === EquipmentType::Door)
                    ->schema([
                        TextInput::make('size')
                            ->placeholder('16x7')
                            ->maxLength(20),
                        TextInput::make('material')
                            ->placeholder('Steel')
                            ->maxLength(100),
                        Toggle::make('is_impact_rated')
                            ->label('Impact rated')
                            ->inline(false),
                        TextInput::make('design_pressure_positive')
                            ->label('Design pressure +')
                            ->numeric()
                            ->suffix('psf'),
                        TextInput::make('design_pressure_negative')
                            ->label('Design pressure −')
                            ->numeric()
                            ->suffix('psf'),
                        SpatieMediaLibraryFileUpload::make('label')
                            ->label('Photo of the label')
                            ->collection('label')
                            ->image()
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(intdiv((int) config('media-library.max_file_size'), 1024)),
                    ]),
                DatePicker::make('installed_on')
                    ->label('Installed')
                    ->native(false),
                DatePicker::make('warranty_expires_on')
                    ->label('Warranty until')
                    ->native(false),
                DatePicker::make('last_serviced_on')
                    ->label('Last serviced')
                    ->native(false),
                DatePicker::make('maintenance_due_on')
                    ->label('Maintenance due')
                    ->helperText('Salt air wears springs and rollers fast.')
                    ->native(false),
                Select::make('status')
                    ->options(EquipmentStatus::class)
                    ->default(EquipmentStatus::Active)
                    ->required()
                    ->visibleOn('edit'),
                Textarea::make('notes')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitle(fn (InstalledEquipment $record): string => $record->title())
            // Each door, then what's on it.
            ->modifyQueryUsing(fn (Builder $query) => $query->with('installedByProject')->orderByRaw('coalesce(parent_id, id), parent_id is not null, id'))
            ->paginated(false)
            ->columns([
                TextColumn::make('type')
                    ->label('Equipment')
                    ->formatStateUsing(fn (InstalledEquipment $record): string => ($record->parent_id ? '↳ ' : '').$record->title())
                    ->extraAttributes(fn (InstalledEquipment $record): array => $record->parent_id ? ['class' => 'ps-6'] : [])
                    ->weight(fn (InstalledEquipment $record): ?string => $record->parent_id ? null : 'semibold')
                    ->description(fn (InstalledEquipment $record): ?string => $record->serial ? "Serial {$record->serial}" : null),
                TextColumn::make('installed_on')
                    ->label('Installed')
                    ->date()
                    ->description(fn (InstalledEquipment $record): ?string => $record->installedByProject?->title)
                    ->url(fn (InstalledEquipment $record): ?string => $record->installed_by_project_id ? ProjectResource::getUrl('view', ['record' => $record->installed_by_project_id]) : null)
                    ->placeholder('Unknown'),
                TextColumn::make('warranty_expires_on')
                    ->label('Warranty until')
                    ->date()
                    ->color(fn (InstalledEquipment $record): ?string => $record->warranty_expires_on?->isPast() ? 'gray' : null)
                    ->placeholder('None'),
                TextColumn::make('maintenance_due_on')
                    ->label('Maintenance due')
                    ->date()
                    ->color(fn (InstalledEquipment $record): ?string => $record->maintenance_due_on?->isPast() ? 'danger' : null)
                    ->placeholder('Not set'),
                TextColumn::make('status')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(EquipmentStatus::class)
                    ->default(EquipmentStatus::Active->value),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add equipment'),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('remove')
                        ->label('Mark removed')
                        ->icon(Heroicon::OutlinedArchiveBoxXMark)
                        ->color('gray')
                        ->requiresConfirmation()
                        ->modalDescription('It stays in the history here, marked removed.')
                        ->visible(fn (InstalledEquipment $record): bool => $record->status === EquipmentStatus::Active)
                        ->action(fn (InstalledEquipment $record) => $record->update(['status' => EquipmentStatus::Removed])),
                    EditAction::make(),
                    DeleteAction::make()
                        ->modalDescription('For a record entered by mistake. Anything listed on this door is deleted with it. To keep the history, mark it removed instead.'),
                ]),
            ])
            ->emptyStateHeading('No equipment recorded')
            ->emptyStateDescription('Completing a job offers to record what it installed. You can also add what’s already there.');
    }

    private static function type(Get $get): ?EquipmentType
    {
        $type = $get('type');

        return $type instanceof EquipmentType ? $type : EquipmentType::tryFrom((string) $type);
    }
}
