<?php

namespace App\Filament\Resources\Activities;

use App\Filament\Resources\Activities\Pages\ListActivities;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use UnitEnum;

/**
 * Every change the activity log recorded, across all records. Super admins only.
 */
class ActivityResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Activity log';

    protected static ?string $modelLabel = 'activity';

    protected static ?string $pluralModelLabel = 'activity log';

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextEntry::make('causer.name')
                    ->label('Who')
                    ->placeholder('The system'),
                TextEntry::make('created_at')
                    ->label('When')
                    ->dateTime(),
                TextEntry::make('subject_type')
                    ->label('Record')
                    ->formatStateUsing(fn (Activity $record): string => self::subjectLabel($record)),
                TextEntry::make('description'),
                KeyValueEntry::make('attribute_changes.old')
                    ->label('Before')
                    ->visible(fn (Activity $record): bool => filled($record->attribute_changes?->get('old'))),
                KeyValueEntry::make('attribute_changes.attributes')
                    ->label('After')
                    ->visible(fn (Activity $record): bool => filled($record->attribute_changes?->get('attributes'))),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('causer'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('causer.name')
                    ->label('Who')
                    ->placeholder('The system'),
                TextColumn::make('event')
                    ->badge(),
                TextColumn::make('subject_type')
                    ->label('Record')
                    ->formatStateUsing(fn (Activity $record): string => self::subjectLabel($record)),
                TextColumn::make('description')
                    ->limit(60),
            ])
            ->filters([
                SelectFilter::make('subject_type')
                    ->label('Record type')
                    ->options(fn (): array => Activity::query()->distinct()->pluck('subject_type')->filter()
                        ->mapWithKeys(fn (string $type): array => [$type => Str::headline(class_basename($type))])
                        ->all()),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivities::route('/'),
        ];
    }

    /**
     * "Project 12", from the subject's class and key, which still reads after the record is deleted.
     */
    private static function subjectLabel(Activity $activity): string
    {
        return filled($activity->subject_type)
            ? Str::headline(class_basename($activity->subject_type)).' '.$activity->subject_id
            : 'None';
    }
}
