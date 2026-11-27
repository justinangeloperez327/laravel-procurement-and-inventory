<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Semi-expendable property and PPE. See docs/05-DATABASE-SCHEMA.md.
 * Check constraints, partial uniqueness and append-only triggers are PostgreSQL-specific.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->restrictOnDelete();
            $table->string('property_number', 64);
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->string('asset_class', 40)->default('ppe');
            $table->foreignId('inspection_item_id')->nullable()->constrained('inspection_items')->restrictOnDelete();
            $table->foreignId('purchase_order_item_id')->nullable()->constrained('purchase_order_items')->restrictOnDelete();
            $table->date('acquisition_date');
            $table->decimal('acquisition_cost', 18, 2);
            $table->string('serial_number', 128)->nullable();
            $table->string('brand_model', 255)->nullable();
            $table->text('description')->nullable();
            $table->string('condition', 32)->default('serviceable');
            $table->string('status', 32)->default('in_stock');
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->restrictOnDelete();
            $table->foreignId('custodian_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->date('warranty_until')->nullable();
            $table->smallInteger('estimated_useful_life_years')->nullable();
            $table->timestamps();
            $table->unique(['agency_id', 'property_number']);
            $table->unique(['item_id', 'serial_number']);
        });

        Schema::create('asset_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->restrictOnDelete();
            $table->foreignId('custodian_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->restrictOnDelete();
            $table->string('document_type', 8);
            $table->string('document_reference', 64);
            $table->timestampTz('assigned_at');
            $table->timestampTz('acknowledged_at')->nullable();
            $table->timestampTz('returned_at')->nullable();
            $table->string('return_condition', 32)->nullable();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('asset_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->restrictOnDelete();
            $table->foreignId('from_custodian_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('to_custodian_id')->constrained('users')->restrictOnDelete();
            $table->string('ptr_reference', 64);
            $table->text('reason')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('transferred_at')->nullable();
            $table->timestamps();
        });

        Schema::create('asset_maintenance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->restrictOnDelete();
            $table->string('maintenance_type', 32);
            $table->date('performed_on');
            $table->string('performed_by', 255)->nullable();
            $table->decimal('cost', 18, 2)->nullable();
            $table->text('description');
            $table->date('next_due_on')->nullable();
            $table->timestamps();
        });

        Schema::create('asset_disposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->restrictOnDelete();
            $table->string('disposal_method', 32);
            $table->string('iirup_reference', 64)->nullable();
            $table->text('reason');
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->date('disposed_on')->nullable();
            $table->decimal('proceeds', 18, 2)->nullable();
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE assets ADD CONSTRAINT assets_check_1 CHECK (acquisition_cost >= 0)');
            DB::statement("ALTER TABLE asset_assignments ADD CONSTRAINT asset_assignments_check_1 CHECK (document_type IN ('ICS','PAR'))");
            DB::statement('ALTER TABLE asset_transfers ADD CONSTRAINT asset_transfers_check_1 CHECK (from_custodian_id <> to_custodian_id)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_disposals');
        Schema::dropIfExists('asset_maintenance');
        Schema::dropIfExists('asset_transfers');
        Schema::dropIfExists('asset_assignments');
        Schema::dropIfExists('assets');
    }
};
