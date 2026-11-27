<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Delivery, receipt, inspection and acceptance. See docs/05-DATABASE-SCHEMA.md.
 * Check constraints, partial uniqueness and append-only triggers are PostgreSQL-specific.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->restrictOnDelete();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->restrictOnDelete();
            $table->string('reference_no', 64);
            $table->timestampTz('delivered_at');
            $table->foreignId('received_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('received_at')->nullable();
            $table->string('status', 40)->default('scheduled');
            $table->timestamps();
            $table->unique(['supplier_id', 'reference_no']);
        });

        Schema::create('delivery_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained('deliveries')->restrictOnDelete();
            $table->foreignId('purchase_order_item_id')->constrained('purchase_order_items')->restrictOnDelete();
            $table->decimal('quantity_delivered', 18, 4);
            $table->string('lot_number', 64)->nullable();
            $table->date('expiry_date')->nullable();
            $table->jsonb('serial_numbers')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained('deliveries')->restrictOnDelete();
            $table->foreignId('inspected_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('inspected_at')->nullable();
            $table->string('status', 16)->default('pending');
            $table->string('result', 40)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('inspection_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_id')->constrained('inspections')->restrictOnDelete();
            $table->foreignId('delivery_item_id')->constrained('delivery_items')->restrictOnDelete();
            $table->decimal('quantity_accepted', 18, 4);
            $table->decimal('quantity_rejected', 18, 4)->default(0);
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->unique(['inspection_id', 'delivery_item_id']);
        });

        Schema::create('delivery_discrepancies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained('deliveries')->restrictOnDelete();
            $table->foreignId('delivery_item_id')->nullable()->constrained('delivery_items')->restrictOnDelete();
            $table->string('discrepancy_type', 32);
            $table->decimal('quantity', 18, 4)->nullable();
            $table->text('description');
            $table->string('status', 16)->default('open');
            $table->text('resolution')->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('acceptance_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->restrictOnDelete();
            $table->foreignId('inspection_id')->constrained('inspections')->restrictOnDelete();
            $table->string('reference_no', 64);
            $table->foreignId('accepted_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('accepted_at');
            $table->foreignId('document_id')->nullable()->constrained('documents')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['agency_id', 'reference_no']);
            $table->unique(['inspection_id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE delivery_items ADD CONSTRAINT delivery_items_check_1 CHECK (quantity_delivered > 0)');
            DB::statement('ALTER TABLE inspection_items ADD CONSTRAINT inspection_items_check_1 CHECK (quantity_accepted >= 0)');
            DB::statement('ALTER TABLE inspection_items ADD CONSTRAINT inspection_items_check_2 CHECK (quantity_rejected >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('acceptance_reports');
        Schema::dropIfExists('delivery_discrepancies');
        Schema::dropIfExists('inspection_items');
        Schema::dropIfExists('inspections');
        Schema::dropIfExists('delivery_items');
        Schema::dropIfExists('deliveries');
    }
};
