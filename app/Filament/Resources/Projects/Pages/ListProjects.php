<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Enums\ProjectStage;
use App\Filament\Resources\Projects\ProjectResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListProjects extends ListRecords
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'open' => Tab::make('Open')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotIn('stage', ProjectStage::finished())),
            'done' => Tab::make('Done')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('stage', [ProjectStage::Completed, ProjectStage::Closed])),
            'lost' => Tab::make('Lost or cancelled')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('stage', [ProjectStage::Lost, ProjectStage::Cancelled])),
            'all' => Tab::make('All'),
        ];
    }

    public function getDefaultActiveTab(): string
    {
        return 'open';
    }
}
