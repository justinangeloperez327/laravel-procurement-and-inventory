<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Supplier registry, eligibility documents, ownership and sanctions. See docs/05-DATABASE-SCHEMA.md.
 * Check constraints, partial uniqueness and append-only triggers are PostgreSQL-specific.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->restrictOnDelete();
            $table->string('name', 255);
            $table->string('trade_name', 255)->nullable();
            $table->string('philgeps_registration_no', 64)->nullable();
            $table->string('tin', 32)->nullable();
            $table->string('business_type', 32);
            $table->text('address')->nullable();
            $table->string('email', 255)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('status', 40)->default('pending');
            $table->date('registered_at')->nullable();
            $table->timestamps();
            $table->unique(['agency_id', 'tin']);
        });

        Schema::create('supplier_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->string('name', 255);
            $table->string('position', 255)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('phone', 32)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        Schema::create('supplier_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('document_id')->nullable()->constrained('documents')->restrictOnDelete();
            $table->string('document_type', 64);
            $table->string('reference_no', 64)->nullable();
            $table->date('issued_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('verified_at')->nullable();
            $table->timestamps();
            $table->index(['expires_on']);
        });

        Schema::create('supplier_beneficial_owners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->string('name', 255);
            $table->string('nationality', 64)->nullable();
            $table->decimal('ownership_percentage', 9, 4)->nullable();
            $table->string('control_basis', 255)->nullable();
            $table->date('declared_on');
            $table->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('supplier_sanctions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->string('sanction_type', 40)->default('suspension');
            $table->string('issuing_authority', 255);
            $table->string('order_reference', 128);
            $table->text('reason');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestampTz('lifted_at')->nullable();
            $table->text('lifted_reason')->nullable();
            $table->foreignId('lifted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE supplier_beneficial_owners ADD CONSTRAINT supplier_beneficial_owners_check_1 CHECK (ownership_percentage IS NULL OR (ownership_percentage >= 0 AND ownership_percentage <= 100))');
            DB::statement('ALTER TABLE supplier_sanctions ADD CONSTRAINT supplier_sanctions_check_1 CHECK (effective_to IS NULL OR effective_to >= effective_from)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_sanctions');
        Schema::dropIfExists('supplier_beneficial_owners');
        Schema::dropIfExists('supplier_documents');
        Schema::dropIfExists('supplier_contacts');
        Schema::dropIfExists('suppliers');
    }
};
