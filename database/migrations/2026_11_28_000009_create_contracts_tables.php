<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Awards, contracts, securities, purchase orders and framework agreements. See docs/05-DATABASE-SCHEMA.md.
 * Check constraints, partial uniqueness and append-only triggers are PostgreSQL-specific.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_awards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_project_id')->constrained('procurement_projects')->restrictOnDelete();
            $table->foreignId('procurement_lot_id')->nullable()->constrained('procurement_lots')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('bac_resolution_id')->nullable()->constrained('bac_resolutions')->restrictOnDelete();
            $table->foreignId('awarded_bid_id')->nullable()->constrained('bids')->restrictOnDelete();
            $table->foreignId('awarded_quotation_id')->nullable()->constrained('quotations')->restrictOnDelete();
            $table->decimal('awarded_amount', 18, 2);
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->string('noa_reference', 64)->nullable();
            $table->timestampTz('notice_of_award_at')->nullable();
            $table->string('status', 32)->default('pending_approval');
            $table->timestamps();
        });

        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->restrictOnDelete();
            $table->foreignId('contract_award_id')->constrained('contract_awards')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->string('reference_no', 64);
            $table->string('title', 255);
            $table->decimal('contract_amount', 18, 2);
            $table->date('signed_on')->nullable();
            $table->date('effective_on')->nullable();
            $table->date('end_on')->nullable();
            $table->timestampTz('notice_to_proceed_at')->nullable();
            $table->string('status', 40)->default('draft');
            $table->timestamps();
            $table->unique(['agency_id', 'reference_no']);
        });

        Schema::create('contract_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->restrictOnDelete();
            $table->foreignId('procurement_project_item_id')->nullable()->constrained('procurement_project_items')->restrictOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('items')->restrictOnDelete();
            $table->string('description', 255);
            $table->foreignId('unit_of_measure_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_price', 18, 4);
            $table->decimal('total', 18, 2);
            $table->date('delivery_due_on')->nullable();
            $table->timestamps();
        });

        Schema::create('contract_amendments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->restrictOnDelete();
            $table->smallInteger('amendment_no');
            $table->string('amendment_type', 32);
            $table->text('description');
            $table->decimal('amount_change', 18, 2)->default(0);
            $table->integer('days_extension')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->string('legal_basis', 255)->nullable();
            $table->timestamps();
            $table->unique(['contract_id', 'amendment_no']);
        });

        Schema::create('contract_securities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->restrictOnDelete();
            $table->string('security_type', 32);
            $table->string('form', 32);
            $table->string('issuer', 255)->nullable();
            $table->string('reference_no', 64)->nullable();
            $table->decimal('amount', 18, 2);
            $table->date('valid_from');
            $table->date('valid_until');
            $table->string('status', 16)->default('submitted');
            $table->foreignId('document_id')->nullable()->constrained('documents')->restrictOnDelete();
            $table->timestamps();
            $table->index(['valid_until']);
        });

        Schema::create('framework_agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('procurement_project_id')->nullable()->constrained('procurement_projects')->restrictOnDelete();
            $table->string('reference_no', 64);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->decimal('ceiling_amount', 18, 2)->nullable();
            $table->string('status', 16)->default('active');
            $table->timestamps();
            $table->unique(['agency_id', 'reference_no']);
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->restrictOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained('contracts')->restrictOnDelete();
            $table->foreignId('contract_award_id')->nullable()->constrained('contract_awards')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->string('reference_no', 64);
            $table->date('issued_on')->nullable();
            $table->date('delivery_due_on')->nullable();
            $table->string('delivery_place', 255)->nullable();
            $table->decimal('total_amount', 18, 2);
            $table->string('status', 40)->default('draft');
            $table->timestampTz('acknowledged_at')->nullable();
            $table->timestamps();
            $table->unique(['agency_id', 'reference_no']);
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->restrictOnDelete();
            $table->foreignId('contract_item_id')->nullable()->constrained('contract_items')->restrictOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('items')->restrictOnDelete();
            $table->string('description', 255);
            $table->foreignId('unit_of_measure_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_price', 18, 4);
            $table->decimal('total', 18, 2);
            $table->timestamps();
        });

        Schema::create('call_off_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('framework_agreement_id')->constrained('framework_agreements')->restrictOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->restrictOnDelete();
            $table->string('reference_no', 64);
            $table->date('ordered_on');
            $table->decimal('amount', 18, 2);
            $table->string('status', 16)->default('draft');
            $table->timestamps();
            $table->unique(['framework_agreement_id', 'reference_no']);
        });

        Schema::create('supplier_performances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained('contracts')->restrictOnDelete();
            $table->foreignId('evaluated_by')->constrained('users')->restrictOnDelete();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->decimal('rating', 9, 4);
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE contract_awards ADD CONSTRAINT contract_awards_check_1 CHECK (awarded_amount > 0)');
            DB::statement('ALTER TABLE contracts ADD CONSTRAINT contracts_check_1 CHECK (contract_amount > 0)');
            DB::statement('ALTER TABLE contract_items ADD CONSTRAINT contract_items_check_1 CHECK (quantity > 0)');
            DB::statement('ALTER TABLE contract_items ADD CONSTRAINT contract_items_check_2 CHECK (unit_price >= 0)');
            DB::statement('ALTER TABLE contract_securities ADD CONSTRAINT contract_securities_check_1 CHECK (amount > 0)');
            DB::statement('ALTER TABLE contract_securities ADD CONSTRAINT contract_securities_check_2 CHECK (valid_until >= valid_from)');
            DB::statement('ALTER TABLE framework_agreements ADD CONSTRAINT framework_agreements_check_1 CHECK (ends_on > starts_on)');
            DB::statement('ALTER TABLE purchase_order_items ADD CONSTRAINT purchase_order_items_check_1 CHECK (quantity > 0)');
            DB::statement('ALTER TABLE purchase_order_items ADD CONSTRAINT purchase_order_items_check_2 CHECK (unit_price >= 0)');
            DB::statement('ALTER TABLE call_off_orders ADD CONSTRAINT call_off_orders_check_1 CHECK (amount > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_performances');
        Schema::dropIfExists('call_off_orders');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('framework_agreements');
        Schema::dropIfExists('contract_securities');
        Schema::dropIfExists('contract_amendments');
        Schema::dropIfExists('contract_items');
        Schema::dropIfExists('contracts');
        Schema::dropIfExists('contract_awards');
    }
};
