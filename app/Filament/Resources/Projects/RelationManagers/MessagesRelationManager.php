<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Models\Project;
use App\Models\ProjectMessage;
use App\Models\User;
use App\Support\ClientNotices;
use App\Support\MessageThread;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The job's thread with the client, newest first. The client reads and replies on the job's page
 * in their portal. Opening the tab marks their messages read for whoever opened it (the pages'
 * MarksMessagesRead, which runs before this tab's badge is counted).
 *
 * @property Project $ownerRecord
 */
class MessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'messages';

    protected static ?string $title = 'Messages';

    /**
     * The badge is counted as the tab is drawn rather than when the tabs are built, which is
     * before the page marks an open thread read.
     */
    public static function getTabComponent(Model $ownerRecord, string $pageClass): Tab
    {
        return parent::getTabComponent($ownerRecord, $pageClass)
            ->badge(static fn (): ?string => static::getBadge($ownerRecord, $pageClass));
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return null;
        }

        $unread = MessageThread::unread($user)->where('project_id', $ownerRecord->getKey())->count();

        return $unread > 0 ? (string) $unread : null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitle(fn (ProjectMessage $record): string => 'message from '.$this->from($record))
            ->modifyQueryUsing(fn (Builder $query) => $query->reorder()->latest('id')->with('author'))
            ->paginated([25, 50])
            ->columns([
                TextColumn::make('body')
                    ->label('Message')
                    ->wrap()
                    ->description(fn (ProjectMessage $record): string => collect([
                        $this->from($record),
                        $record->created_at?->timezone(config('app.business_timezone'))->format('M j, g:i A'),
                    ])->filter()->implode(' · '), position: 'above'),
                TextColumn::make('is_from_client')
                    ->label('From')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Client' : 'Us')
                    ->color(fn (bool $state): string => $state ? 'info' : 'gray')
                    ->width('1%'),
            ])
            ->headerActions([
                Action::make('send')
                    ->label('Send a message')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->visible(fn (): bool => ClientNotices::isVisible($this->ownerRecord))
                    ->modalDescription(fn (): string => $this->whoHears())
                    ->schema([
                        Textarea::make('body')
                            ->hiddenLabel()
                            ->required()
                            ->rows(5)
                            ->maxLength(MessageThread::MAX_LENGTH),
                    ])
                    ->modalSubmitActionLabel('Send')
                    ->action(function (array $data): void {
                        /** @var User $user */
                        $user = auth()->user();
                        MessageThread::post($this->ownerRecord, $user, $data['body']);
                    })
                    ->successNotificationTitle('Sent'),
            ])
            ->recordActions([
                DeleteAction::make(),
            ])
            ->emptyStateIcon(Heroicon::OutlinedChatBubbleLeftRight)
            ->emptyStateHeading('No messages yet')
            ->emptyStateDescription(fn (): string => ClientNotices::isVisible($this->ownerRecord)
                ? 'Messages show on this job in the client’s portal, and they can reply there.'
                : 'This job doesn’t show in the client’s portal, so there’s no thread for it.');
    }

    /**
     * Who will be emailed, so the owner knows whether anyone hears about it.
     */
    private function whoHears(): string
    {
        $names = ClientNotices::messageRecipients($this->ownerRecord)->pluck('name');

        if ($names->isEmpty()) {
            return "Nobody at {$this->ownerRecord->client->display_name} can reply in the portal yet, so nobody is emailed. Invite them from the client’s Portal tab.";
        }

        return 'It shows on this job in their portal. '.$names->join(', ', ' and ').' will be emailed in '.MessageThread::EMAIL_DELAY_MINUTES.' minutes, unless they read it there first.';
    }

    private function from(ProjectMessage $record): string
    {
        return $record->author->name ?? ($record->is_from_client ? $this->ownerRecord->client->display_name : 'Former admin');
    }
}
