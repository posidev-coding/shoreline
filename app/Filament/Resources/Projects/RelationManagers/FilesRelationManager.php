<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Models\Project;
use App\Support\PrivateMedia;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The job's photos and documents, and which of them the client sees in the portal. They're
 * added on the Edit page; new photos start shared, documents start private.
 *
 * @property Project $ownerRecord
 */
class FilesRelationManager extends RelationManager
{
    protected static string $relationship = 'media';

    protected static ?string $title = 'Files';

    public const array COLLECTIONS = ['photos' => 'Photos', 'documents' => 'Documents'];

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('collection_name', array_keys(self::COLLECTIONS)))
            // Photos first, in the order the Edit page shows them.
            ->defaultGroup(Group::make('collection_name')
                ->label('Kind')
                ->titlePrefixedWithLabel(false)
                ->getTitleFromRecordUsing(fn (Media $record): string => self::COLLECTIONS[$record->collection_name] ?? $record->collection_name)
                ->orderQueryUsing(fn (Builder $query) => $query->orderBy('collection_name', 'desc')))
            ->defaultSort('order_column')
            ->paginated(false)
            ->columns([
                ImageColumn::make('preview')
                    ->label('')
                    ->state(fn (Media $record): ?string => $record->collection_name === 'photos' ? PrivateMedia::thumbnailUrl($record) : null)
                    ->imageHeight(48)
                    ->width('1%'),
                TextColumn::make('name')
                    ->label('File')
                    ->icon(fn (Media $record): ?Heroicon => $record->collection_name === 'documents' ? Heroicon::OutlinedDocumentText : null)
                    ->description(fn (Media $record): string => $record->human_readable_size)
                    ->wrap(),
                TextColumn::make('created_at')
                    ->label('Added')
                    ->date('M j, Y'),
                ToggleColumn::make('in_portal')
                    ->label('In the portal')
                    ->state(fn (Media $record): bool => Project::isInPortal($record))
                    ->updateStateUsing(function (Media $record, bool $state): bool {
                        self::share(collect([$record]), $state);

                        return $state;
                    })
                    ->width('1%'),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Open')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (Media $record): string => PrivateMedia::url($record), shouldOpenInNewTab: true),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkAction::make('show')
                    ->label('Show in the portal')
                    ->icon(Heroicon::OutlinedEye)
                    ->action(fn (Collection $records) => self::share($records, true))
                    ->deselectRecordsAfterCompletion(),
                BulkAction::make('hide')
                    ->label('Hide from the portal')
                    ->icon(Heroicon::OutlinedEyeSlash)
                    ->action(fn (Collection $records) => self::share($records, false))
                    ->deselectRecordsAfterCompletion(),
            ])
            ->emptyStateIcon(Heroicon::OutlinedPhoto)
            ->emptyStateHeading('No photos or documents yet')
            ->emptyStateDescription('Add them with Edit, from your phone on site.');
    }

    /**
     * @param  Collection<int, Media>  $records
     */
    private static function share(Collection $records, bool $inPortal): void
    {
        $records->each(fn (Media $media) => $media->setCustomProperty(Project::IN_PORTAL, $inPortal)->save());
    }
}
