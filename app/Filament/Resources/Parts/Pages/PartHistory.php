<?php

namespace App\Filament\Resources\Parts\Pages;

use App\Filament\Resources\Parts\PartResource;
use App\Filament\Support\RecordHistory;

class PartHistory extends RecordHistory
{
    protected static string $resource = PartResource::class;
}
