<?php

namespace App\Support;

use App\Enums\StockMovementType;
use App\Models\Part;
use App\Models\PartStockMovement;
use App\Models\Project;
use App\Models\ProjectLineItem;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

/**
 * The only way a part's stock changes. Each change locks the part, writes a movement with the
 * new balance, and updates on_hand in one transaction, so on_hand is always the sum of the
 * part's movements, even with two people working at once.
 *
 * Stock can go below zero: he may use a part before anyone records receiving it, and the
 * count should say so rather than refuse the job.
 */
final class Stock
{
    public function receive(Part $part, string $quantity, ?string $note = null): PartStockMovement
    {
        return $this->move($part, StockMovementType::Received, $quantity, note: $note);
    }

    /**
     * Sets on_hand to a count taken on the shelf, recording the difference. Null when the
     * count already matches.
     */
    public function adjustTo(Part $part, string $counted, ?string $note = null): ?PartStockMovement
    {
        return DB::transaction(function () use ($part, $counted, $note): ?PartStockMovement {
            $part = $this->lock($part);
            $difference = BigDecimal::of($counted)->minus($part->on_hand);

            return $difference->isZero() ? null : $this->move($part, StockMovementType::Adjusted, (string) $difference, note: $note);
        });
    }

    public function adjustBy(Part $part, string $difference, ?string $note = null): PartStockMovement
    {
        return $this->move($part, StockMovementType::Adjusted, $difference, note: $note);
    }

    public function returnFromProject(Part $part, string $quantity, Project $project, ?string $note = null): PartStockMovement
    {
        return $this->move($part, StockMovementType::Returned, $quantity, $project, note: $note);
    }

    /**
     * Takes the completed job's stocked parts out of stock, once per line item.
     */
    public function useOnProject(Project $project): void
    {
        $lines = $project->lineItems()->with('part')->whereHas('part', fn ($query) => $query->where('track_stock', true))->get();

        foreach ($lines as $line) {
            if ($line->part !== null) {
                $this->moveForLine($line, $line->part, $project, StockMovementType::Used, (string) BigDecimal::of($line->quantity)->negated());
            }
        }
    }

    /**
     * Puts a stocked part ordered for a job into stock when it arrives, once per line item.
     * Completing the job takes it out again.
     */
    public function receiveForLineItem(ProjectLineItem $line): void
    {
        $part = $line->part()->first();

        if ($part?->track_stock) {
            $this->moveForLine($line, $part, $line->project()->first(), StockMovementType::Received, (string) $line->quantity);
        }
    }

    private function moveForLine(ProjectLineItem $line, Part $part, ?Project $project, StockMovementType $type, string $quantity): void
    {
        DB::transaction(function () use ($line, $part, $project, $type, $quantity): void {
            $part = $this->lock($part);

            // Checked under the part's lock, so two saves of the same line can't both get past it.
            $alreadyMoved = PartStockMovement::query()
                ->where('project_line_item_id', $line->getKey())
                ->where('type', $type)
                ->exists();

            if (! $alreadyMoved) {
                $this->move($part, $type, $quantity, $project, $line);
            }
        });
    }

    private function move(Part $part, StockMovementType $type, string $quantity, ?Project $project = null, ?ProjectLineItem $line = null, ?string $note = null): PartStockMovement
    {
        return DB::transaction(function () use ($part, $type, $quantity, $project, $line, $note): PartStockMovement {
            $part = $this->lock($part);
            $balance = BigDecimal::of($part->on_hand)->plus($quantity)->toScale(2);

            $movement = $part->movements()->create([
                'type' => $type,
                'quantity' => $quantity,
                'balance_after' => (string) $balance,
                'project_id' => $project?->getKey(),
                'project_line_item_id' => $line?->getKey(),
                'user_id' => auth()->id(),
                'note' => $note,
            ]);

            // Quietly: the movement is the record of this change, so the activity log needn't repeat it.
            $part->forceFill(['on_hand' => (string) $balance])->saveQuietly();

            return $movement;
        });
    }

    /**
     * The part's row, fresh and locked for the rest of the transaction.
     */
    private function lock(Part $part): Part
    {
        return Part::query()->lockForUpdate()->whereKey($part->getKey())->firstOrFail();
    }
}
