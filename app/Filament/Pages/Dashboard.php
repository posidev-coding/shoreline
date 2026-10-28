<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

/**
 * The week's numbers and what's coming up, full width, then three short lists side by side
 * on a wide screen.
 */
class Dashboard extends BaseDashboard
{
    public function getColumns(): int|array
    {
        return ['md' => 2, 'xl' => 3];
    }
}
