<?php

namespace App\Filament\Resources\Leads\Pages;

use App\Enums\LeadStatus;
use App\Filament\Resources\Clients\Schemas\ClientForm;
use App\Filament\Resources\Leads\LeadResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Support\ContactFields;
use App\Models\Client;
use App\Models\Lead;
use App\Models\Property;
use App\Support\LeadConverter;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

/**
 * @property Lead $record
 */
class ViewLead extends ViewRecord
{
    protected static string $resource = LeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->convertAction(),
            Action::make('openProject')
                ->label('Open the project')
                ->icon(Heroicon::OutlinedWrenchScrewdriver)
                ->visible(fn (Lead $record): bool => $record->status === LeadStatus::Converted && $record->project()->exists())
                ->url(fn (Lead $record): string => ProjectResource::getUrl('view', ['record' => $record->project()->value('id')])),
            Action::make('markContacted')
                ->label('Mark contacted')
                ->icon(Heroicon::OutlinedCheck)
                ->color('gray')
                ->visible(fn (Lead $record): bool => $record->status === LeadStatus::New)
                ->action(function (Lead $record): void {
                    $record->update(['status' => LeadStatus::Contacted, 'contacted_at' => now()]);
                    $this->dispatch('refresh-sidebar');
                }),
            Action::make('markSpam')
                ->label('Spam')
                ->icon(Heroicon::OutlinedNoSymbol)
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('It moves to the Spam tab. Nothing is sent to them.')
                ->visible(fn (Lead $record): bool => in_array($record->status, [LeadStatus::New, LeadStatus::Contacted], true))
                ->action(function (Lead $record): void {
                    $record->update(['status' => LeadStatus::Spam]);
                    $this->dispatch('refresh-sidebar');
                }),
            Action::make('reopen')
                ->label('Move back to New')
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('gray')
                ->visible(fn (Lead $record): bool => in_array($record->status, [LeadStatus::Contacted, LeadStatus::Spam], true))
                ->action(function (Lead $record): void {
                    $record->update(['status' => LeadStatus::New]);
                    $this->dispatch('refresh-sidebar');
                }),
            DeleteAction::make()
                ->modalDescription(fn (Lead $record): string => $record->status === LeadStatus::Converted
                    ? 'This deletes the request and its photos for good. The client and project made from it stay. Use it when someone asks for their details to be erased, and delete the client too.'
                    : 'This deletes the request and its photos for good. Use it when someone asks for their details to be erased.'),
        ];
    }

    /**
     * Makes the request a project: for a client we already have when the email or phone
     * matches one, at their property when the address matches, or new ones from the request.
     */
    private function convertAction(): Action
    {
        return Action::make('convert')
            ->label('Convert to project')
            ->icon(Heroicon::OutlinedArrowRightCircle)
            ->visible(fn (Lead $record): bool => in_array($record->status, [LeadStatus::New, LeadStatus::Contacted], true))
            ->modalHeading('Convert to a project')
            ->modalDescription('Creates the project, and the client and property too when they’re new.')
            ->modalSubmitActionLabel('Convert')
            ->fillForm(function (Lead $record): array {
                $matches = LeadConverter::matchingClients($record);
                $client = $matches->count() === 1 ? $matches->sole() : null;

                return [
                    'client' => $client ? (string) $client->getKey() : 'new',
                    'display_name' => LeadConverter::availableDisplayName($record),
                    'property' => self::suggestedProperty($client, $record),
                    'title' => LeadConverter::defaultTitle($record),
                ];
            })
            ->schema(fn (Lead $record): array => self::convertSchema($record, LeadConverter::matchingClients($record)))
            ->action(function (Lead $record, array $data): void {
                $matches = LeadConverter::matchingClients($record);
                $client = $data['client'] === 'new' ? null : $matches->find((int) $data['client']);
                $property = $client && ($data['property'] ?? 'new') !== 'new' ? $client->properties->find((int) $data['property']) : null;

                if ($data['client'] !== 'new' && $client === null) {
                    Notification::make()->title('That client is gone. Pick again.')->danger()->send();

                    return;
                }

                try {
                    $project = app(LeadConverter::class)->convert($record, $client, $property, $data['title'], $data['display_name'] ?? null);
                } catch (RuntimeException $exception) {
                    // Converted from another tab in the meantime.
                    Notification::make()->title($exception->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Converted to a project')->success()->send();
                $this->dispatch('refresh-sidebar');
                $this->redirect(ProjectResource::getUrl('view', ['record' => $project]));
            });
    }

    /**
     * @param  Collection<int, Client>  $matches
     * @return list<Component|Radio|TextInput>
     */
    private static function convertSchema(Lead $record, Collection $matches): array
    {
        $clientOptions = ['new' => 'A new client'];
        $clientDescriptions = ['new' => "{$record->name}, from the request"];

        foreach ($matches as $client) {
            $clientOptions[(string) $client->getKey()] = $client->display_name;
            $clientDescriptions[(string) $client->getKey()] = collect([
                'Already a client',
                $client->email,
                ContactFields::national($client->phone),
            ])->filter()->implode(' · ');
        }

        $chosenClient = fn (Get $get): ?Client => $get('client') === 'new' ? null : $matches->find((int) $get('client'));

        return [
            Radio::make('client')
                ->label($matches->isEmpty() ? 'Client' : 'Same email or phone as a client we have')
                ->options($clientOptions)
                ->descriptions($clientDescriptions)
                ->required()
                ->live()
                ->afterStateUpdated(fn (Get $get, Set $set) => $set('property', self::suggestedProperty($chosenClient($get), $record))),
            ClientForm::displayName()
                ->visible(fn (Get $get): bool => $get('client') === 'new'),
            Radio::make('property')
                ->label('Property')
                // Added with +, which keeps the numeric property keys that spreading would renumber.
                ->options(fn (Get $get): array => ($chosenClient($get)?->properties->mapWithKeys(fn (Property $property): array => [$property->getKey() => $property->address()])->all() ?? [])
                    + ['new' => "A new property at {$record->street}, {$record->town}"])
                ->visible(fn (Get $get): bool => $get('client') !== 'new')
                ->required(fn (Get $get): bool => $get('client') !== 'new'),
            TextInput::make('title')
                ->label('Project')
                ->required()
                ->maxLength(255),
        ];
    }

    /**
     * The client's property at the request's address, or a new one.
     */
    private static function suggestedProperty(?Client $client, Lead $lead): string
    {
        $property = $client ? LeadConverter::matchingProperty($client, $lead) : null;

        return $property ? (string) $property->getKey() : 'new';
    }
}
