<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * One call to Intuit: what was asked, what came back, and the intuit_tid that Intuit's support
 * asks for. Pruned after 30 days by the weekly model:prune.
 */
#[Fillable(['quickbooks_connection_id', 'method', 'path', 'status', 'intuit_tid', 'duration_ms', 'detail', 'created_at'])]
#[Table('quickbooks_sync_logs')]
class QuickBooksSyncLog extends Model
{
    use MassPrunable;

    public const int KEEP_DAYS = 30;

    public const null UPDATED_AT = null;

    /**
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return self::query()->where('created_at', '<', now()->subDays(self::KEEP_DAYS));
    }
}
