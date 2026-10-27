<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Support\RecordHistory;

class ClientHistory extends RecordHistory
{
    protected static string $resource = ClientResource::class;
}
