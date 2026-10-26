<?php

namespace App\Filament\Resources\Properties\Pages;

use App\Filament\Resources\Properties\PropertyResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewProperty extends ViewRecord
{
    protected static string $resource = PropertyResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->getRecordTitle();
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            ActionGroup::make([
                PropertyHistory::action(),
                DeleteAction::make()
                    ->modalDescription('Its projects go to the trash with it. Restore the property to bring them back.'),
                RestoreAction::make(),
                ForceDeleteAction::make()
                    ->modalDescription('This deletes the property, its projects, and their photos for good.'),
            ]),
        ];
    }
}
