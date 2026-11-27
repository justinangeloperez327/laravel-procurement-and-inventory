<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catalog and warehouse master data. See docs/05-DATABASE-SCHEMA.md.
 * Check constraints, partial uniqueness and append-only triggers are PostgreSQL-specific.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units_of_measure', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16);
            $table->string('name', 255);
            $table->foreignId('base_unit_id')->nullable()->constrained('units_of_measure')->restrictOnDelete();
            $table->decimal('conversion_factor', 18, 6)->default(1);
            $table->timestamps();
            $table->unique(['code']);
        });

        Schema::create('item_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('item_categories')->restrictOnDelete();
            $table->string('code', 32);
            $table->string('name', 255);
            $table->string('default_asset_class', 40)->nullable();
            $table->foreignId('account_code_id')->nullable()->constrained('account_codes')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['code']);
        });

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->restrictOnDelete();
            $table->foreignId('item_category_id')->constrained('item_categories')->restrictOnDelete();
            $table->foreignId('unit_of_measure_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->string('code', 64);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->text('specifications')->nullable();
            $table->string('asset_class', 40)->default('consumable');
            $table->boolean('is_serialized')->default(false);
            $table->boolean('is_lot_tracked')->default(false);
            $table->boolean('tracks_expiry')->default(false);
            $table->boolean('is_cse')->default(false);
            $table->decimal('reorder_level', 18, 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['agency_id', 'code']);
        });

        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->restrictOnDelete();
            $table->string('code', 32);
            $table->string('name', 255);
            $table->text('address')->nullable();
            $table->foreignId('custodian_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['agency_id', 'code']);
        });

        Schema::create('warehouse_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->string('code', 32);
            $table->string('name', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['warehouse_id', 'code']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE units_of_measure ADD CONSTRAINT units_of_measure_check_1 CHECK (conversion_factor > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_locations');
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('items');
        Schema::dropIfExists('item_categories');
        Schema::dropIfExists('units_of_measure');
    }
};
