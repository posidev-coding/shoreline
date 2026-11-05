<?php

namespace App\Filament\Resources\Estimates\Pages;

use App\Filament\Resources\Estimates\EstimateResource;
use Filament\Resources\Pages\ListRecords;

class ListEstimates extends ListRecords
{
    protected static string $resource = EstimateResource::class;
}
