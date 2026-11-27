<?php

use App\Enums\PurchaseRequestStatus;
use App\Enums\StockMovementType;
use App\Models\Agency;
use App\Models\Bid;
use App\Models\BidEvaluation;
use App\Models\BudgetDisbursement;
use App\Models\BudgetReservation;
use App\Models\DocumentVersion;
use App\Models\InspectionItem;
use App\Models\Item;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
 * Database-level invariants from docs/03, 09, 10 and 16. Each violation is a separate test because a
 * failed statement aborts the surrounding PostgreSQL test transaction.
 */

function receipt(InspectionItem $line, array $overrides = []): StockMovement
{
    return StockMovement::factory()->create(array_merge([
        'movement_type' => StockMovementType::AcceptedReceipt,
        'inspection_item_id' => $line->id,
        'quantity' => 5,
    ], $overrides));
}

// ---- stock ledger (docs/10)

it('posts an accepted inspection line to stock exactly once', function () {
    $line = InspectionItem::factory()->create();
    receipt($line);

    expect(fn () => receipt($line))->toThrow(QueryException::class);
});

it('requires an inspection line for accepted receipts', function () {
    expect(fn () => StockMovement::factory()->create(['movement_type' => StockMovementType::AcceptedReceipt]))
        ->toThrow(QueryException::class);
});

it('rejects zero-quantity movements', function () {
    expect(fn () => StockMovement::factory()->create(['quantity' => 0]))->toThrow(QueryException::class);
});

it('rejects duplicate posting idempotency keys', function () {
    StockMovement::factory()->create(['idempotency_key' => 'post-1']);

    expect(fn () => StockMovement::factory()->create(['idempotency_key' => 'post-1']))->toThrow(QueryException::class);
});

it('refuses to update ledger movements through the model', function () {
    $movement = StockMovement::factory()->create();

    expect(fn () => $movement->update(['quantity' => 99]))->toThrow(LogicException::class)
        ->and(fn () => $movement->delete())->toThrow(LogicException::class);
});

it('refuses to update ledger movements in the database', function () {
    $movement = StockMovement::factory()->create();

    expect(fn () => DB::table('stock_movements')->where('id', $movement->id)->update(['quantity' => 99]))
        ->toThrow(QueryException::class, 'append-only');
});

it('allows a movement to be reversed only once', function () {
    $movement = StockMovement::factory()->create();
    $reverse = fn () => StockMovement::factory()->create([
        'movement_type' => StockMovementType::Reversal, 'reverses_movement_id' => $movement->id, 'quantity' => -$movement->quantity,
    ]);
    $reverse();

    expect($reverse)->toThrow(QueryException::class);
});

it('never lets stock balances go negative', function () {
    expect(fn () => StockBalance::factory()->create(['quantity_on_hand' => -1]))->toThrow(QueryException::class);
});

it('never reserves more than is on hand', function () {
    expect(fn () => StockBalance::factory()->create(['quantity_on_hand' => 5, 'quantity_reserved' => 6]))->toThrow(QueryException::class);
});

it('keeps one balance row per warehouse, item and lot even without a lot', function () {
    $balance = StockBalance::factory()->create();

    expect(fn () => StockBalance::factory()->create(['warehouse_id' => $balance->warehouse_id, 'item_id' => $balance->item_id]))
        ->toThrow(QueryException::class);
});

it('rejects transfers into the same warehouse', function () {
    $warehouse = Warehouse::factory()->create();

    expect(fn () => StockTransfer::factory()->create([
        'agency_id' => $warehouse->agency_id, 'from_warehouse_id' => $warehouse->id, 'to_warehouse_id' => $warehouse->id,
    ]))->toThrow(QueryException::class);
});

// ---- budget (docs/09)

it('rejects non-positive budget reservations', function () {
    expect(fn () => BudgetReservation::factory()->create(['amount' => 0]))->toThrow(QueryException::class);
});

it('keeps disbursements append-only', function () {
    $disbursement = BudgetDisbursement::factory()->create();

    expect(fn () => DB::table('budget_disbursements')->where('id', $disbursement->id)->delete())->toThrow(QueryException::class);
});

// ---- bidding (docs/14, 16)

it('accepts one bid per supplier and lot, including lotless biddings', function () {
    $bid = Bid::factory()->create();

    expect(fn () => Bid::factory()->create(['bidding_id' => $bid->bidding_id, 'supplier_id' => $bid->supplier_id]))
        ->toThrow(QueryException::class);
});

it('keeps bid amounts sealed until opening by default', function () {
    expect(Bid::factory()->create()->bid_amount)->toBeNull();
});

it('requires an evaluation to target exactly one bid or quotation', function () {
    expect(fn () => BidEvaluation::factory()->create(['bid_id' => null, 'quotation_id' => null]))->toThrow(QueryException::class);
});

// ---- documents and references (docs/05, 12)

it('never overwrites a stored document version', function () {
    $version = DocumentVersion::factory()->create();

    expect(fn () => DB::table('document_versions')->where('id', $version->id)->update(['sha256' => str_repeat('0', 64)]))
        ->toThrow(QueryException::class);
});

it('keeps references unique per agency but not across agencies', function () {
    $pr = PurchaseRequest::factory()->create(['reference_no' => 'PR-2026-0001']);
    $other = PurchaseRequest::factory()->create(['reference_no' => 'PR-2026-0001', 'agency_id' => Agency::factory()]);
    expect($other->exists)->toBeTrue();

    expect(fn () => PurchaseRequest::factory()->create(['reference_no' => 'PR-2026-0001', 'agency_id' => $pr->agency_id]))
        ->toThrow(QueryException::class);
});

it('rejects non-positive request quantities', function () {
    expect(fn () => PurchaseRequestItem::factory()->create(['quantity' => 0]))->toThrow(QueryException::class);
});

it('casts workflow statuses to enums and wires relations', function () {
    $item = PurchaseRequestItem::factory()->create();
    $pr = $item->purchaseRequest;

    expect($pr->status)->toBe(PurchaseRequestStatus::Draft)
        ->and($pr->items)->toHaveCount(1)
        ->and(Item::factory()->create()->asset_class->value)->toBe('consumable');
});
