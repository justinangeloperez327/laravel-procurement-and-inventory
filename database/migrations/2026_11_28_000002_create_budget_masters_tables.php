<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Budget master data: fund sources, account codes, allocations and adjustments. See docs/05-DATABASE-SCHEMA.md.
 * Check constraints, partial uniqueness and append-only triggers are PostgreSQL-specific.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fund_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->restrictOnDelete();
            $table->string('code', 32);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['agency_id', 'code']);
        });

        Schema::create('account_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32);
            $table->string('name', 255);
            $table->string('classification', 32)->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('account_codes')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['code']);
        });

        Schema::create('budget_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->restrictOnDelete();
            $table->foreignId('fiscal_year_id')->constrained('fiscal_years')->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->restrictOnDelete();
            $table->foreignId('fund_source_id')->constrained('fund_sources')->restrictOnDelete();
            $table->foreignId('account_code_id')->constrained('account_codes')->restrictOnDelete();
            $table->string('reference_no', 64);
            $table->decimal('amount', 18, 2);
            $table->string('authorization_reference', 128);
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->string('status', 16)->default('draft');
            $table->timestamps();
            $table->unique(['agency_id', 'reference_no']);
        });

        Schema::create('budget_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_allocation_id')->constrained('budget_allocations')->restrictOnDelete();
            $table->string('adjustment_type', 32);
            $table->decimal('amount', 18, 2);
            $table->string('authorization_reference', 128);
            $table->text('reason');
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('approved_at');
            $table->foreignId('related_adjustment_id')->nullable()->constrained('budget_adjustments')->restrictOnDelete();
            $table->string('idempotency_key', 128);
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['idempotency_key']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE budget_allocations ADD CONSTRAINT budget_allocations_check_1 CHECK (amount >= 0)');
            DB::statement('ALTER TABLE budget_adjustments ADD CONSTRAINT budget_adjustments_check_1 CHECK (amount > 0)');
            DB::statement("ALTER TABLE budget_adjustments ADD CONSTRAINT budget_adjustments_check_2 CHECK (adjustment_type IN ('augmentation','reduction','realignment_in','realignment_out'))");
            DB::statement('CREATE TRIGGER budget_adjustments_immutable BEFORE UPDATE OR DELETE ON budget_adjustments FOR EACH ROW EXECUTE FUNCTION forbid_mutation()');
            DB::statement('CREATE TRIGGER budget_adjustments_no_truncate BEFORE TRUNCATE ON budget_adjustments FOR EACH STATEMENT EXECUTE FUNCTION forbid_mutation()');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_adjustments');
        Schema::dropIfExists('budget_allocations');
        Schema::dropIfExists('account_codes');
        Schema::dropIfExists('fund_sources');
    }
};
