<?php

namespace App\Filament\Resources\Clients\RelationManagers;

use App\Filament\Resources\Properties\PropertyResource;
use App\Filament\Support\CreatesRelatedInModal;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class PropertiesRelationManager extends RelationManager
{
    use CreatesRelatedInModal;

    protected static string $relationship = 'properties';

    protected static ?string $relatedResource = PropertyResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->headerActions([
                CreateAction::make()
                    ->label('Add property'),
            ]);
    }
}
