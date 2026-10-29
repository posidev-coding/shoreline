<?php

namespace App\Filament\Resources\Clients\RelationManagers;

use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\Client;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The client's QuickBooks invoices, through the customer it's linked to. Read-only, and
 * only for a linked client.
 */
class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    protected static ?string $relatedResource = InvoiceResource::class;

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
