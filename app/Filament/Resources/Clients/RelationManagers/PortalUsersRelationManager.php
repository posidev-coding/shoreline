<?php

namespace App\Filament\Resources\Clients\RelationManagers;

use App\Enums\ClientUserRole;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\User;
use App\Support\PortalAccess;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use STS\FilamentImpersonate\Actions\Impersonate;

/**
 * The people who can sign in to this client's portal. They sign in with a code emailed to them,
 * so there are no passwords to set or reset here.
 *
 * @property Client $ownerRecord
 */
class PortalUsersRelationManager extends RelationManager
{
    protected static string $relationship = 'portalUsers';

    protected static ?string $title = 'Portal';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('email')
            ->paginated(false)
            ->columns([
                TextColumn::make('name')
                    ->weight('semibold')
                    ->description(fn (User $record): string => $record->email),
                TextColumn::make('access.client_role')
                    ->label('Role')
                    ->formatStateUsing(fn (User $record): string => $this->access($record)->client_role->getLabel()),
                TextColumn::make('access.invited_at')
                    ->label('Invited')
                    ->since()
                    ->dateTimeTooltip()
                    ->placeholder('Not yet'),
                TextColumn::make('last_signed_in_at')
                    ->label('Last signed in')
                    ->since()
                    ->dateTimeTooltip()
                    ->placeholder('Never'),
            ])
            ->headerActions([
                Action::make('invite')
                    ->label('Invite to portal')
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->authorize(fn (): bool => auth()->user()?->can('update', $this->ownerRecord) ?? false)
                    ->modalHeading('Invite someone to the portal')
                    ->modalDescription('They get an email with a link to sign in. There’s no password: each time, a code is emailed to them.')
                    ->modalSubmitActionLabel('Send invitation')
                    ->fillForm(fn (): array => [
                        'name' => trim("{$this->ownerRecord->first_name} {$this->ownerRecord->last_name}") ?: $this->ownerRecord->display_name,
                        'email' => $this->ownerRecord->email,
                        'role' => $this->ownerRecord->portalUsers()->exists() ? ClientUserRole::Manager : ClientUserRole::Primary,
                    ])
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->rules([
                                fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                    if (PortalAccess::adminWithEmail((string) $value) !== null) {
                                        $fail('That address belongs to an admin, who uses the admin instead.');
                                    } elseif (PortalAccess::hasAccess($this->ownerRecord, (string) $value)) {
                                        $fail('They already have access. Use “Resend invitation” on their row.');
                                    }
                                },
                            ]),
                        Radio::make('role')
                            ->options(ClientUserRole::class)
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        $user = PortalAccess::invite($this->ownerRecord, $data['name'], $data['email'], self::role($data['role']));

                        Notification::make()->title("Invitation sent to {$user->email}")->success()->send();
                    }),
            ])
            ->recordActions([
                Impersonate::make()
                    ->label('View their portal')
                    ->redirectTo(fn (): string => route('portal.dashboard'))
                    ->withoutSpa(),
                ActionGroup::make([
                    Action::make('resend')
                        ->label('Resend invitation')
                        ->icon(Heroicon::OutlinedEnvelope)
                        ->authorize(fn (): bool => auth()->user()?->can('update', $this->ownerRecord) ?? false)
                        ->requiresConfirmation()
                        ->modalDescription(fn (User $record): string => "It goes to {$record->email}.")
                        ->action(function (User $record): void {
                            PortalAccess::resendInvitation($this->ownerRecord, $record);

                            Notification::make()->title("Invitation sent to {$record->email}")->success()->send();
                        }),
                    Action::make('changeRole')
                        ->label('Change role')
                        ->icon(Heroicon::OutlinedUserCircle)
                        ->authorize(fn (): bool => auth()->user()?->can('update', $this->ownerRecord) ?? false)
                        ->fillForm(fn (User $record): array => ['role' => $this->access($record)->client_role])
                        ->schema([
                            Radio::make('role')
                                ->options(ClientUserRole::class)
                                ->required(),
                        ])
                        ->modalWidth('md')
                        ->action(fn (User $record, array $data) => PortalAccess::changeRole($this->ownerRecord, $record, self::role($data['role']))),
                    Action::make('remove')
                        ->label('Remove access')
                        ->icon(Heroicon::OutlinedNoSymbol)
                        ->color('danger')
                        ->authorize(fn (): bool => auth()->user()?->can('update', $this->ownerRecord) ?? false)
                        ->requiresConfirmation()
                        ->modalDescription(fn (User $record): string => "{$record->name} can no longer see {$this->ownerRecord->display_name}’s projects. Their next page load signs them out of it.")
                        ->action(fn (User $record) => PortalAccess::remove($this->ownerRecord, $record)),
                ]),
            ])
            ->emptyStateIcon(Heroicon::OutlinedUserGroup)
            ->emptyStateHeading('No one has portal access')
            ->emptyStateDescription('Invite the client to see their projects and visits online.');
    }

    /**
     * A form hands the role back as the enum or its value, depending on how it was filled.
     */
    private static function role(mixed $state): ClientUserRole
    {
        return $state instanceof ClientUserRole ? $state : ClientUserRole::from((string) $state);
    }

    /**
     * The row's access to this client, which the relationship loads as `access`.
     */
    private function access(User $user): ClientUser
    {
        return ClientUser::of($user);
    }
}
