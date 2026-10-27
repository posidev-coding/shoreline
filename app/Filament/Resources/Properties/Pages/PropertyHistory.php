<?php

namespace App\Filament\Resources\Properties\Pages;

use App\Filament\Resources\Properties\PropertyResource;
use App\Filament\Support\RecordHistory;

class PropertyHistory extends RecordHistory
{
    protected static string $resource = PropertyResource::class;
}
