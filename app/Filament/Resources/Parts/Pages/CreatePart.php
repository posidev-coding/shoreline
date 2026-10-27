<?php

namespace App\Filament\Resources\Parts\Pages;

use App\Filament\Resources\Parts\PartResource;
use App\Models\Part;
use App\Support\Stock;
use Filament\Resources\Pages\CreateRecord;

/**
 * @property Part $record
 */
class CreatePart extends CreateRecord
{
    protected static string $resource = PartResource::class;

    /**
     * A counted part's starting stock goes in as its first movement, so the count has a history.
     */
    protected function afterCreate(): void
    {
        $count = $this->data['starting_count'] ?? null;

        if ($this->record->track_stock && filled($count)) {
            app(Stock::class)->adjustTo($this->record, (string) $count, 'Starting count');
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
