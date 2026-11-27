<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Market scoping, PPMP and APP with revision lineage. See docs/05-DATABASE-SCHEMA.md.
 * Check constraints, partial uniqueness and append-only triggers are PostgreSQL-specific.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_scopings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->restrictOnDelete();
            $table->foreignId('department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignId('fiscal_year_id')->constrained('fiscal_years')->restrictOnDelete();
            $table->string('reference_no', 64);
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->foreignId('conducted_by')->constrained('users')->restrictOnDelete();
            $table->date('conducted_on');
            $table->string('status', 40)->default('draft');
            $table->timestamps();
            $table->unique(['agency_id', 'reference_no']);
        });

        Schema::create('market_scoping_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('market_scoping_id')->constrained('market_scopings')->restrictOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('items')->restrictOnDelete();
            $table->string('description', 255);
            $table->foreignId('unit_of_measure_id')->nullable()->constrained('units_of_measure')->restrictOnDelete();
            $table->decimal('estimated_unit_cost', 18, 4);
            $table->string('price_source', 128)->nullable();
            $table->smallInteger('sources_count')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('ppmps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->restrictOnDelete();
            $table->foreignId('department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignId('fiscal_year_id')->constrained('fiscal_years')->restrictOnDelete();
            $table->string('reference_no', 64);
            $table->smallInteger('version')->default(1);
            $table->foreignId('parent_ppmp_id')->nullable()->constrained('ppmps')->restrictOnDelete();
            $table->string('status', 40)->default('draft');
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->unique(['agency_id', 'reference_no', 'version']);
        });

        Schema::create('ppmp_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ppmp_id')->constrained('ppmps')->restrictOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('items')->restrictOnDelete();
            $table->foreignId('market_scoping_item_id')->nullable()->constrained('market_scoping_items')->restrictOnDelete();
            $table->string('description', 255);
            $table->foreignId('unit_of_measure_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->decimal('estimated_unit_cost', 18, 4);
            $table->decimal('estimated_budget', 18, 2);
            $table->string('proposed_mode', 40)->nullable();
            $table->date('schedule_start')->nullable();
            $table->date('schedule_end')->nullable();
            $table->foreignId('account_code_id')->nullable()->constrained('account_codes')->restrictOnDelete();
            $table->foreignId('fund_source_id')->nullable()->constrained('fund_sources')->restrictOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('apps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->restrictOnDelete();
            $table->foreignId('fiscal_year_id')->constrained('fiscal_years')->restrictOnDelete();
            $table->string('reference_no', 64);
            $table->string('title', 255);
            $table->string('status', 40)->default('draft');
            $table->timestamps();
            $table->unique(['agency_id', 'fiscal_year_id']);
            $table->unique(['agency_id', 'reference_no']);
        });

        Schema::create('app_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_id')->constrained('apps')->restrictOnDelete();
            $table->smallInteger('version');
            $table->string('status', 40)->default('draft');
            $table->boolean('is_indicative')->default(false);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->text('revision_reason')->nullable();
            $table->foreignId('supersedes_id')->nullable()->constrained('app_versions')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['app_id', 'version']);
        });

        Schema::create('app_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_version_id')->constrained('app_versions')->restrictOnDelete();
            $table->string('code', 64);
            $table->string('description', 255);
            $table->foreignId('item_category_id')->nullable()->constrained('item_categories')->restrictOnDelete();
            $table->string('procurement_mode', 40)->default('small_value_procurement');
            $table->decimal('estimated_budget', 18, 2);
            $table->foreignId('fund_source_id')->nullable()->constrained('fund_sources')->restrictOnDelete();
            $table->date('advertisement_on')->nullable();
            $table->date('opening_on')->nullable();
            $table->date('award_on')->nullable();
            $table->date('contract_signing_on')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->unique(['app_version_id', 'code']);
        });

        Schema::create('app_item_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_item_id')->constrained('app_items')->restrictOnDelete();
            $table->foreignId('ppmp_item_id')->constrained('ppmp_items')->restrictOnDelete();
            $table->decimal('quantity', 18, 4)->nullable();
            $table->decimal('amount', 18, 2);
            $table->timestamps();
            $table->unique(['app_item_id', 'ppmp_item_id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE market_scoping_items ADD CONSTRAINT market_scoping_items_check_1 CHECK (estimated_unit_cost >= 0)');
            DB::statement('ALTER TABLE ppmp_items ADD CONSTRAINT ppmp_items_check_1 CHECK (quantity > 0)');
            DB::statement('ALTER TABLE ppmp_items ADD CONSTRAINT ppmp_items_check_2 CHECK (estimated_budget >= 0)');
            DB::statement('ALTER TABLE app_items ADD CONSTRAINT app_items_check_1 CHECK (estimated_budget >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('app_item_sources');
        Schema::dropIfExists('app_items');
        Schema::dropIfExists('app_versions');
        Schema::dropIfExists('apps');
        Schema::dropIfExists('ppmp_items');
        Schema::dropIfExists('ppmps');
        Schema::dropIfExists('market_scoping_items');
        Schema::dropIfExists('market_scopings');
    }
};
