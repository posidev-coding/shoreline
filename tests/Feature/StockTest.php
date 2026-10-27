<?php

use App\Enums\OrderStatus;
use App\Enums\ProjectStage;
use App\Enums\StockMovementType;
use App\Models\Part;
use App\Models\PartStockMovement;
use App\Models\Project;
use App\Models\ProjectLineItem;
use App\Support\Stock;
use Illuminate\Support\Facades\DB;

/**
 * on_hand must always equal the sum of the part's movements, and the last movement's balance.
 */
function expectStockInStep(Part $part): void
{
    $part->refresh();
    $sum = (string) DB::table('part_stock_movements')->where('part_id', $part->getKey())->sum('quantity');

    expect((float) $part->on_hand)->toBe((float) $sum)
        ->and($part->movements()->latest('id')->value('balance_after'))->toBe($part->on_hand);
}

it('keeps on hand equal to the movements through receiving, counting, returns, and use', function () {
    $part = Part::factory()->spring()->create();
    $stock = app(Stock::class);

    $stock->receive($part, '6', 'From the supply house');
    $stock->adjustBy($part, '-1', 'Bent in the truck');
    $stock->returnFromProject($part, '1', Project::factory()->create());
    $stock->adjustTo($part, '4.5');

    expect($part->refresh()->on_hand)->toBe('4.50')
        ->and($part->movements()->pluck('type')->all())->toBe([StockMovementType::Received, StockMovementType::Adjusted, StockMovementType::Returned, StockMovementType::Adjusted]);
    expectStockInStep($part);
});

it('records nothing when a count matches what’s on hand', function () {
    $part = Part::factory()->spring()->create();
    app(Stock::class)->receive($part, '3');

    expect(app(Stock::class)->adjustTo($part, '3'))->toBeNull()
        ->and($part->movements()->count())->toBe(1);
});

it('uses a completed job’s stocked parts once, however often it’s reopened and completed', function () {
    $spring = Part::factory()->spring()->create();
    app(Stock::class)->receive($spring, '5');
    $project = Project::factory()->stage(ProjectStage::InProgress)->create();
    $springs = ProjectLineItem::factory()->for($project)->create(['part_id' => $spring->getKey(), 'quantity' => '2']);
    ProjectLineItem::factory()->for($project)->create(['part_id' => Part::factory()->create()->getKey()]);
    ProjectLineItem::factory()->for($project)->create(['description' => 'Labor']);

    $project->update(['stage' => ProjectStage::Completed]);
    $project->update(['stage' => ProjectStage::InProgress]);
    $project->update(['stage' => ProjectStage::Completed]);
    $project->update(['stage' => ProjectStage::Closed]);

    expect($spring->refresh()->on_hand)->toBe('3.00')
        ->and(PartStockMovement::query()->where('type', StockMovementType::Used)->sole())
        ->project_line_item_id->toBe($springs->getKey())
        ->project_id->toBe($project->getKey())
        ->quantity->toBe('-2.00');
    expectStockInStep($spring);
});

it('uses parts added after a job was reopened when it’s completed again', function () {
    $spring = Part::factory()->spring()->create();
    $project = Project::factory()->stage(ProjectStage::Completed)->create();
    ProjectLineItem::factory()->for($project)->create(['part_id' => $spring->getKey(), 'quantity' => '1']);
    $project->update(['stage' => ProjectStage::InProgress]);

    $project->update(['stage' => ProjectStage::Completed]);

    // Stock goes below zero rather than refuse the job: the count was never entered.
    expect($spring->refresh()->on_hand)->toBe('-1.00');
    expectStockInStep($spring);
});

it('puts a stocked part ordered for a job into stock once when it arrives', function () {
    $spring = Part::factory()->spring()->create();
    $line = ProjectLineItem::factory()->create(['part_id' => $spring->getKey(), 'quantity' => '2', 'order_status' => OrderStatus::ToOrder]);

    $line->update(['order_status' => OrderStatus::Received]);
    $line->update(['order_status' => OrderStatus::Ordered]);
    $line->update(['order_status' => OrderStatus::Received]);

    expect($spring->refresh()->on_hand)->toBe('2.00')
        ->and($line->refresh())
        ->ordered_at->not->toBeNull()
        ->received_at->not->toBeNull();
    expectStockInStep($spring);
});

it('leaves parts that aren’t counted out of stock', function () {
    $opener = Part::factory()->opener()->create();
    $line = ProjectLineItem::factory()->create(['part_id' => $opener->getKey(), 'order_status' => OrderStatus::Ordered]);

    $line->update(['order_status' => OrderStatus::Received]);
    $line->project->update(['stage' => ProjectStage::Completed]);

    expect($opener->movements()->count())->toBe(0);
});

it('moves an approved job to materials ordered once its parts are ordered', function (ProjectStage $from, ProjectStage $to) {
    $project = Project::factory()->stage($from)->create();
    $line = ProjectLineItem::factory()->for($project)->create(['order_status' => OrderStatus::ToOrder]);

    expect($project->refresh()->stage)->toBe($from);

    $line->update(['order_status' => OrderStatus::Ordered]);

    expect($project->refresh()->stage)->toBe($to);
})->with([
    'approved moves on' => [ProjectStage::Approved, ProjectStage::MaterialsOrdered],
    'still estimating stays' => [ProjectStage::Estimating, ProjectStage::Estimating],
    'already scheduled stays' => [ProjectStage::Scheduled, ProjectStage::Scheduled],
]);
