<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Invoices, payments, protests, disputes and compliance. See docs/05-DATABASE-SCHEMA.md.
 * Check constraints, partial uniqueness and append-only triggers are PostgreSQL-specific.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->restrictOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained('contracts')->restrictOnDelete();
            $table->string('invoice_number', 64);
            $table->date('invoice_date');
            $table->timestampTz('received_at')->nullable();
            $table->decimal('total_amount', 18, 2);
            $table->string('status', 40)->default('received');
            $table->foreignId('matched_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('matched_at')->nullable();
            $table->timestamps();
            $table->unique(['supplier_id', 'invoice_number']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->foreignId('purchase_order_item_id')->nullable()->constrained('purchase_order_items')->restrictOnDelete();
            $table->string('description', 255);
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_price', 18, 4);
            $table->decimal('total', 18, 2);
            $table->decimal('matched_quantity', 18, 4)->nullable();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->restrictOnDelete();
            $table->string('reference_no', 64);
            $table->string('ors_burs_reference', 64)->nullable();
            $table->foreignId('payee_supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->date('paid_on');
            $table->string('payment_method', 32);
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->string('idempotency_key', 128);
            $table->timestamps();
            $table->unique(['agency_id', 'reference_no']);
            $table->unique(['idempotency_key']);
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->timestamps();
            $table->unique(['payment_id', 'invoice_id']);
        });

        Schema::create('protests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_project_id')->constrained('procurement_projects')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->timestampTz('filed_at');
            $table->text('grounds');
            $table->decimal('protest_fee', 18, 2)->nullable();
            $table->string('status', 16)->default('filed');
            $table->boolean('blocks_award')->default(true);
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('decided_at')->nullable();
            $table->text('decision')->nullable();
            $table->timestamps();
        });

        Schema::create('reconsideration_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_project_id')->constrained('procurement_projects')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('bac_resolution_id')->nullable()->constrained('bac_resolutions')->restrictOnDelete();
            $table->timestampTz('filed_at');
            $table->text('grounds');
            $table->string('status', 16)->default('filed');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('decided_at')->nullable();
            $table->text('decision')->nullable();
            $table->timestamps();
        });

        Schema::create('disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->restrictOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained('contracts')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->string('dispute_type', 32);
            $table->text('description');
            $table->timestampTz('filed_at');
            $table->string('status', 16)->default('open');
            $table->text('resolution')->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('compliance_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->restrictOnDelete();
            $table->morphs('subject');
            $table->string('flag_type', 64);
            $table->string('severity', 16)->default('warning');
            $table->text('description');
            $table->foreignId('raised_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('raised_at');
            $table->string('status', 16)->default('open');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();
            $table->index(['status', 'severity']);
        });

        Schema::create('conflict_declarations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('procurement_project_id')->nullable()->constrained('procurement_projects')->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->restrictOnDelete();
            $table->timestampTz('declared_at');
            $table->boolean('has_conflict')->default(false);
            $table->text('nature')->nullable();
            $table->string('action_taken', 32)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE invoices ADD CONSTRAINT invoices_check_1 CHECK (total_amount >= 0)');
            DB::statement('ALTER TABLE invoice_items ADD CONSTRAINT invoice_items_check_1 CHECK (quantity > 0)');
            DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_check_1 CHECK (amount > 0)');
            DB::statement('ALTER TABLE payment_allocations ADD CONSTRAINT payment_allocations_check_1 CHECK (amount > 0)');
            DB::statement("ALTER TABLE compliance_flags ADD CONSTRAINT compliance_flags_check_1 CHECK (severity IN ('info','warning','blocking'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('conflict_declarations');
        Schema::dropIfExists('compliance_flags');
        Schema::dropIfExists('disputes');
        Schema::dropIfExists('reconsideration_requests');
        Schema::dropIfExists('protests');
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
