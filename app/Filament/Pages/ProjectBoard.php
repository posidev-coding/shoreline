<?php

namespace App\Filament\Pages;

use App\Enums\ProjectStage;
use App\Enums\VisitStatus;
use App\Filament\Resources\Projects\Actions\ChangeStageAction;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Project;
use App\Support\EquipmentRecorder;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Relaticle\Flowforge\Board;
use Relaticle\Flowforge\Column;
use Relaticle\Flowforge\Components\CardMeta;
use Relaticle\Flowforge\Exceptions\CardMoveRejectedException;
use Relaticle\Flowforge\Modern\BoardPage;

/**
 * Every open job by stage, plus the ones completed lately. Lost, cancelled, and closed jobs have
 * no column; a card's "Change stage" reaches them.
 */
class ProjectBoard extends BoardPage
{
    /**
     * How long a completed job stays on the board.
     */
    public const int COMPLETED_DAYS = 14;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedViewColumns;

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Board';

    protected static ?string $title = 'Project board';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', Project::class) ?? false;
    }

    public function getSubheading(): string
    {
        return 'Drag a card to move the job, or use its menu. Completed jobs leave the board after '.self::COMPLETED_DAYS.' days.';
    }

    /**
     * The board's columns, in the order a job moves through them.
     *
     * @return list<ProjectStage>
     */
    public static function stages(): array
    {
        return [
            ProjectStage::Estimating,
            ProjectStage::ProposalSent,
            ProjectStage::Approved,
            ProjectStage::MaterialsOrdered,
            ProjectStage::Scheduled,
            ProjectStage::InProgress,
            ProjectStage::Completed,
            ProjectStage::OnHold,
        ];
    }

    public function board(Board $board): Board
    {
        return $board
            ->query(Project::query()
                ->with(['client', 'property', 'nextVisit'])
                ->where(fn (Builder $query) => $query
                    ->whereIn('stage', array_filter(self::stages(), fn (ProjectStage $stage): bool => $stage->isOpen()))
                    ->orWhere(fn (Builder $query) => $query
                        ->where('stage', ProjectStage::Completed)
                        ->where('completed_at', '>=', now()->subDays(self::COMPLETED_DAYS)))))
            ->recordTitleAttribute('title')
            ->columnIdentifier('stage')
            ->positionIdentifier('board_position')
            ->cardLabel('job')
            ->collapseEmptyColumns()
            ->searchable(['title', 'client.display_name', 'property.street'])
            ->columns(array_map(fn (ProjectStage $stage): Column => Column::enum($stage), self::stages()))
            ->cardSchema(fn (Schema $schema): Schema => $schema->components([
                CardMeta::make([
                    TextEntry::make('client.display_name')
                        ->icon(Heroicon::OutlinedUser),
                    TextEntry::make('property.street')
                        ->icon(Heroicon::OutlinedMapPin),
                ]),
                CardMeta::make([
                    TextEntry::make('nextVisit.starts_at')
                        ->icon(Heroicon::OutlinedCalendar)
                        ->formatStateUsing(fn (Project $record): string => $record->nextVisit === null ? '' : $record->nextVisit->type->getLabel().', '.$record->nextVisit->window()
                            .($record->nextVisit->status === VisitStatus::Tentative ? ' (tentative)' : '')),
                ])->visible(fn (Project $record): bool => $record->nextVisit !== null),
            ]))
            ->recordActions([
                Action::make('open')
                    ->label('Open')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (Project $record): string => ProjectResource::getUrl('view', ['record' => $record])),
                ChangeStageAction::make(fn (Project $project) => self::offerToRecordEquipment($project)),
            ])
            ->cardAction('open');
    }

    protected function authorizeCardMove(Model $card, string $targetColumnId, ?string $toLaneId = null): void
    {
        if (! (auth()->user()?->can('update', $card) ?? false)) {
            throw CardMoveRejectedException::because('You can’t move this job.');
        }
    }

    protected function persistCardMove(Model $card, mixed $columnValue, string $position): void
    {
        parent::persistCardMove($card, $columnValue, $position);

        if ($card instanceof Project && $card->wasChanged('completed_at') && $card->completed_at !== null && EquipmentRecorder::hasSomethingToRecord($card)) {
            self::offerToRecordEquipment($card);
        }
    }

    /**
     * The board can't open the project page's form, so it links there with the form open.
     */
    private static function offerToRecordEquipment(Project $project): void
    {
        Notification::make()
            ->title("{$project->title} is completed")
            ->body('Record what it installed while it’s fresh.')
            ->actions([
                Action::make('record')
                    ->label('Record installed equipment')
                    ->button()
                    ->url(ProjectResource::getUrl('view', ['record' => $project, 'action' => 'recordEquipment'])),
            ])
            ->success()
            ->persistent()
            ->send();
    }
}
