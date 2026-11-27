<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Budget reservations, obligations and disbursements. See docs/05-DATABASE-SCHEMA.md.
 * Check constraints, partial uniqueness and append-only triggers are PostgreSQL-specific.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_allocation_id')->constrained('budget_allocations')->restrictOnDelete();
            $table->foreignId('purchase_request_id')->nullable()->constrained('purchase_requests')->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->string('status', 16)->default('active');
            $table->foreignId('reserved_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('reserved_at');
            $table->timestampTz('released_at')->nullable();
            $table->string('idempotency_key', 128);
            $table->timestamps();
            $table->unique(['idempotency_key']);
        });

        Schema::create('budget_obligations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_allocation_id')->constrained('budget_allocations')->restrictOnDelete();
            $table->foreignId('budget_reservation_id')->nullable()->constrained('budget_reservations')->restrictOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained('contracts')->restrictOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->restrictOnDelete();
            $table->string('ors_burs_reference', 64)->nullable();
            $table->decimal('amount', 18, 2);
            $table->foreignId('obligated_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('obligated_at');
            $table->string('status', 16)->default('active');
            $table->string('idempotency_key', 128);
            $table->timestamps();
            $table->unique(['idempotency_key']);
        });

        Schema::create('budget_disbursements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_obligation_id')->constrained('budget_obligations')->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->timestampTz('disbursed_at');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->string('idempotency_key', 128);
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['idempotency_key']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE budget_reservations ADD CONSTRAINT budget_reservations_check_1 CHECK (amount > 0)');
            DB::statement("ALTER TABLE budget_reservations ADD CONSTRAINT budget_reservations_check_2 CHECK (status IN ('active','converted','released'))");
            DB::statement('ALTER TABLE budget_obligations ADD CONSTRAINT budget_obligations_check_1 CHECK (amount > 0)');
            DB::statement("ALTER TABLE budget_obligations ADD CONSTRAINT budget_obligations_check_2 CHECK (status IN ('active','cancelled'))");
            DB::statement('ALTER TABLE budget_disbursements ADD CONSTRAINT budget_disbursements_check_1 CHECK (amount > 0)');
            DB::statement('CREATE TRIGGER budget_disbursements_immutable BEFORE UPDATE OR DELETE ON budget_disbursements FOR EACH ROW EXECUTE FUNCTION forbid_mutation()');
            DB::statement('CREATE TRIGGER budget_disbursements_no_truncate BEFORE TRUNCATE ON budget_disbursements FOR EACH STATEMENT EXECUTE FUNCTION forbid_mutation()');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_disbursements');
        Schema::dropIfExists('budget_obligations');
        Schema::dropIfExists('budget_reservations');
    }
};
