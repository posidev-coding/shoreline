<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Filament\Resources\Clients\ClientResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewClient extends ViewRecord
{
    protected static string $resource = ClientResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->getRecordTitle();
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            ActionGroup::make([
                ClientHistory::action(),
                DeleteAction::make()
                    ->modalDescription('Their properties and projects go to the trash with them. Restore the client to bring them all back.'),
                RestoreAction::make(),
                ForceDeleteAction::make()
                    ->modalDescription('This deletes the client, their properties, their projects, and the project photos for good. Use it when someone asks for their details to be erased.'),
            ]),
        ];
    }
}
