<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Support\RecordHistory;

class ProjectHistory extends RecordHistory
{
    protected static string $resource = ProjectResource::class;
}
