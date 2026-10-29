<?php

namespace App\Filament\Resources\QuickBooksSyncIssues\Pages;

use App\Filament\Resources\QuickBooksSyncIssues\QuickBooksSyncIssueResource;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ManageQuickBooksSyncIssues extends ManageRecords
{
    protected static string $resource = QuickBooksSyncIssueResource::class;

    protected static ?string $title = 'Sync issues';

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'open' => Tab::make('Open')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('resolved_at')),
            'resolved' => Tab::make('Resolved')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('resolved_at')),
        ];
    }

    public function getDefaultActiveTab(): string
    {
        return 'open';
    }
}
