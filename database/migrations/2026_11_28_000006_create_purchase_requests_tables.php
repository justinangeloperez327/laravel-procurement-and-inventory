<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Purchase requests. See docs/05-DATABASE-SCHEMA.md.
 * Check constraints, partial uniqueness and append-only triggers are PostgreSQL-specific.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->restrictOnDelete();
            $table->foreignId('department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignId('fiscal_year_id')->constrained('fiscal_years')->restrictOnDelete();
            $table->string('reference_no', 64);
            $table->text('purpose');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 40)->default('draft');
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->foreignId('app_item_id')->nullable()->constrained('app_items')->restrictOnDelete();
            $table->foreignId('fund_source_id')->nullable()->constrained('fund_sources')->restrictOnDelete();
            $table->foreignId('account_code_id')->nullable()->constrained('account_codes')->restrictOnDelete();
            $table->timestampTz('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
            $table->unique(['agency_id', 'reference_no']);
            $table->index(['status']);
        });

        Schema::create('purchase_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained('purchase_requests')->restrictOnDelete();
            $table->foreignId('ppmp_item_id')->nullable()->constrained('ppmp_items')->restrictOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('items')->restrictOnDelete();
            $table->string('description', 255);
            $table->text('specifications')->nullable();
            $table->foreignId('unit_of_measure_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->decimal('estimated_unit_cost', 18, 4);
            $table->decimal('estimated_total', 18, 2);
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE purchase_request_items ADD CONSTRAINT purchase_request_items_check_1 CHECK (quantity > 0)');
            DB::statement('ALTER TABLE purchase_request_items ADD CONSTRAINT purchase_request_items_check_2 CHECK (estimated_unit_cost >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_request_items');
        Schema::dropIfExists('purchase_requests');
    }
};
