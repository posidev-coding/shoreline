<?php

namespace App\Filament\Resources\Clients\RelationManagers;

use App\Filament\Resources\Estimates\EstimateResource;
use App\Models\Client;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The client's QuickBooks estimates, through the customer it's linked to. Read-only, and
 * only for a linked client.
 */
class EstimatesRelationManager extends RelationManager
{
    protected static string $relationship = 'estimates';

    protected static ?string $relatedResource = EstimateResource::class;

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Client && $ownerRecord->qb_id !== null;
    }

    public function table(Table $table): Table
    {
        return $table;
    }
}
