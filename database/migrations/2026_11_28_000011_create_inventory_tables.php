<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stock ledger, balances, requisitions, issuances, transfers and counts. See docs/05-DATABASE-SCHEMA.md.
 * Check constraints, partial uniqueness and append-only triggers are PostgreSQL-specific.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->string('lot_number', 64);
            $table->date('expiry_date')->nullable();
            $table->date('manufactured_on')->nullable();
            $table->timestamps();
            $table->unique(['item_id', 'lot_number']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('warehouse_location_id')->nullable()->constrained('warehouse_locations')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('inventory_lot_id')->nullable()->constrained('inventory_lots')->restrictOnDelete();
            $table->string('movement_type', 40);
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_cost', 18, 4)->nullable();
            $table->nullableMorphs('source');
            $table->foreignId('inspection_item_id')->nullable()->constrained('inspection_items')->restrictOnDelete();
            $table->foreignId('reverses_movement_id')->nullable()->constrained('stock_movements')->restrictOnDelete();
            $table->string('idempotency_key', 128);
            $table->foreignId('posted_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('posted_at');
            $table->text('remarks')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['idempotency_key']);
            $table->unique(['inspection_item_id']);
            $table->unique(['reverses_movement_id']);
            $table->index(['warehouse_id', 'item_id', 'posted_at']);
        });

        Schema::create('stock_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('inventory_lot_id')->nullable()->constrained('inventory_lots')->restrictOnDelete();
            $table->decimal('quantity_on_hand', 18, 4)->default(0);
            $table->decimal('quantity_reserved', 18, 4)->default(0);
            $table->timestamps();
        });

        Schema::create('stock_requisitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->restrictOnDelete();
            $table->foreignId('department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->string('reference_no', 64);
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->text('purpose')->nullable();
            $table->string('status', 16)->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['agency_id', 'reference_no']);
        });

        Schema::create('stock_requisition_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_requisition_id')->constrained('stock_requisitions')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->decimal('quantity_requested', 18, 4);
            $table->decimal('quantity_approved', 18, 4)->nullable();
            $table->timestamps();
        });

        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_requisition_item_id')->nullable()->constrained('stock_requisition_items')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('inventory_lot_id')->nullable()->constrained('inventory_lots')->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->string('status', 16)->default('active');
            $table->foreignId('reserved_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_issuances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->restrictOnDelete();
            $table->foreignId('stock_requisition_id')->nullable()->constrained('stock_requisitions')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->string('reference_no', 64);
            $table->foreignId('issued_to')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('issued_at')->nullable();
            $table->timestampTz('acknowledged_at')->nullable();
            $table->string('status', 16)->default('draft');
            $table->string('idempotency_key', 128)->nullable();
            $table->timestamps();
            $table->unique(['agency_id', 'reference_no']);
            $table->unique(['idempotency_key']);
        });

        Schema::create('stock_issuance_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_issuance_id')->constrained('stock_issuances')->restrictOnDelete();
            $table->foreignId('stock_requisition_item_id')->nullable()->constrained('stock_requisition_items')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('inventory_lot_id')->nullable()->constrained('inventory_lots')->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_cost', 18, 4)->nullable();
            $table->timestamps();
        });

        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->restrictOnDelete();
            $table->string('reference_no', 64);
            $table->foreignId('from_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('to_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->string('status', 16)->default('draft');
            $table->foreignId('dispatched_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('dispatched_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('received_at')->nullable();
            $table->timestamps();
            $table->unique(['agency_id', 'reference_no']);
        });

        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained('stock_transfers')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('inventory_lot_id')->nullable()->constrained('inventory_lots')->restrictOnDelete();
            $table->decimal('quantity_sent', 18, 4);
            $table->decimal('quantity_received', 18, 4)->nullable();
            $table->timestamps();
        });

        Schema::create('physical_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->string('reference_no', 64);
            $table->date('count_date');
            $table->string('status', 16)->default('planned');
            $table->foreignId('counted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['agency_id', 'reference_no']);
        });

        Schema::create('physical_count_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('physical_count_id')->constrained('physical_counts')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('inventory_lot_id')->nullable()->constrained('inventory_lots')->restrictOnDelete();
            $table->decimal('system_quantity', 18, 4);
            $table->decimal('counted_quantity', 18, 4)->nullable();
            $table->text('variance_reason')->nullable();
            $table->foreignId('adjustment_movement_id')->nullable()->constrained('stock_movements')->restrictOnDelete();
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_check_1 CHECK (quantity <> 0)');
            DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_check_2 CHECK (movement_type <> 'accepted_receipt' OR (inspection_item_id IS NOT NULL AND quantity > 0))");
            DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_check_3 CHECK (movement_type <> 'reversal' OR reverses_movement_id IS NOT NULL)");
            DB::statement('CREATE TRIGGER stock_movements_immutable BEFORE UPDATE OR DELETE ON stock_movements FOR EACH ROW EXECUTE FUNCTION forbid_mutation()');
            DB::statement('CREATE TRIGGER stock_movements_no_truncate BEFORE TRUNCATE ON stock_movements FOR EACH STATEMENT EXECUTE FUNCTION forbid_mutation()');
            DB::statement('ALTER TABLE stock_balances ADD CONSTRAINT stock_balances_check_1 CHECK (quantity_on_hand >= 0)');
            DB::statement('ALTER TABLE stock_balances ADD CONSTRAINT stock_balances_check_2 CHECK (quantity_reserved >= 0)');
            DB::statement('ALTER TABLE stock_balances ADD CONSTRAINT stock_balances_check_3 CHECK (quantity_reserved <= quantity_on_hand)');
            DB::statement('CREATE UNIQUE INDEX stock_balances_unique ON stock_balances (warehouse_id, item_id, inventory_lot_id) NULLS NOT DISTINCT');
            DB::statement('ALTER TABLE stock_requisition_items ADD CONSTRAINT stock_requisition_items_check_1 CHECK (quantity_requested > 0)');
            DB::statement('ALTER TABLE stock_reservations ADD CONSTRAINT stock_reservations_check_1 CHECK (quantity > 0)');
            DB::statement('ALTER TABLE stock_issuance_items ADD CONSTRAINT stock_issuance_items_check_1 CHECK (quantity > 0)');
            DB::statement('ALTER TABLE stock_transfers ADD CONSTRAINT stock_transfers_check_1 CHECK (from_warehouse_id <> to_warehouse_id)');
            DB::statement('ALTER TABLE stock_transfer_items ADD CONSTRAINT stock_transfer_items_check_1 CHECK (quantity_sent > 0)');
            DB::statement('ALTER TABLE stock_transfer_items ADD CONSTRAINT stock_transfer_items_check_2 CHECK (quantity_received IS NULL OR quantity_received >= 0)');
            DB::statement('ALTER TABLE physical_count_items ADD CONSTRAINT physical_count_items_check_1 CHECK (system_quantity >= 0)');
            DB::statement('ALTER TABLE physical_count_items ADD CONSTRAINT physical_count_items_check_2 CHECK (counted_quantity IS NULL OR counted_quantity >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('physical_count_items');
        Schema::dropIfExists('physical_counts');
        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');
        Schema::dropIfExists('stock_issuance_items');
        Schema::dropIfExists('stock_issuances');
        Schema::dropIfExists('stock_reservations');
        Schema::dropIfExists('stock_requisition_items');
        Schema::dropIfExists('stock_requisitions');
        Schema::dropIfExists('stock_balances');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('inventory_lots');
    }
};
