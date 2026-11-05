<?php

namespace App\Models;

use App\Services\QuickBooks\EntitySync;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One webhook event from Intuit, in the CloudEvents format: which record changed (qb_id) and
 * how (type, e.g. qbo.invoice.updated.v1). It carries no record data; the app pulls the record.
 * Pruned after 30 days by the weekly model:prune.
 *
 * @property string $event_id
 * @property string $type
 * @property string|null $qb_id
 * @property array<string, mixed> $payload
 * @property Carbon $created_at
 */
#[Fillable(['event_id', 'realm_id', 'type', 'qb_id', 'payload', 'created_at'])]
#[Table('quickbooks_webhook_events')]
class QuickBooksWebhookEvent extends Model
{
    use MassPrunable;

    public const int KEEP_DAYS = 30;

    public const null UPDATED_AT = null;

    /**
     * The QuickBooks entity the event is about, spelled as the API spells it, when it's one the
     * app follows. The event's own name (created, voided, deleted...) doesn't matter: the app
     * pulls the record and sees for itself. Intuit hasn't published every event name.
     */
    public function entity(): ?string
    {
        $segment = $this->typePart(1);

        foreach (EntitySync::ENTITIES as $entity) {
            if (strtolower($entity) === $segment) {
                return $entity;
            }
        }

        return null;
    }

    /**
     * For a merge, the record merged away (data.deletedid); the event's own record is the one
     * left.
     */
    public function mergedQbId(): ?string
    {
        $merged = data_get($this->payload, 'data.deletedid');

        return $this->typePart(2) === 'merged' && filled($merged) ? (string) $merged : null;
    }

    /**
     * One part of namespace.entity.event.version, lowercase.
     */
    private function typePart(int $index): string
    {
        return strtolower(explode('.', $this->type)[$index] ?? '');
    }

    /**
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return self::query()->where('created_at', '<', now()->subDays(self::KEEP_DAYS));
    }

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
