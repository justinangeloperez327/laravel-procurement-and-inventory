<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Procurement projects, lots, mode screening, events, deadlines and publications. See docs/05-DATABASE-SCHEMA.md.
 * Check constraints, partial uniqueness and append-only triggers are PostgreSQL-specific.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_modes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40);
            $table->string('name', 255);
            $table->string('legal_reference', 255)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['code']);
        });

        Schema::create('procurement_mode_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_mode_id')->constrained('procurement_modes')->restrictOnDelete();
            $table->foreignId('regulatory_rule_version_id')->constrained('regulatory_rule_versions')->restrictOnDelete();
            $table->string('rule_type', 32);
            $table->boolean('is_blocking')->default(true);
            $table->timestamps();
            $table->unique(['procurement_mode_id', 'regulatory_rule_version_id']);
        });

        Schema::create('procurement_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->restrictOnDelete();
            $table->foreignId('fiscal_year_id')->constrained('fiscal_years')->restrictOnDelete();
            $table->foreignId('app_item_id')->nullable()->constrained('app_items')->restrictOnDelete();
            $table->string('reference_no', 64);
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('category', 40)->default('goods');
            $table->foreignId('procurement_mode_id')->nullable()->constrained('procurement_modes')->restrictOnDelete();
            $table->decimal('approved_budget', 18, 2);
            $table->string('status', 40)->default('proposed');
            $table->foreignId('end_user_department_id')->nullable()->constrained('departments')->restrictOnDelete();
            $table->foreignId('procurement_officer_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('legal_regime', 32)->default('RA12009');
            $table->timestamps();
            $table->unique(['agency_id', 'reference_no']);
            $table->index(['status']);
        });

        Schema::create('procurement_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_project_id')->constrained('procurement_projects')->restrictOnDelete();
            $table->smallInteger('lot_no');
            $table->string('title', 255);
            $table->decimal('approved_budget', 18, 2);
            $table->string('status', 16)->default('open');
            $table->timestamps();
            $table->unique(['procurement_project_id', 'lot_no']);
        });

        Schema::create('procurement_project_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_project_id')->constrained('procurement_projects')->restrictOnDelete();
            $table->foreignId('procurement_lot_id')->nullable()->constrained('procurement_lots')->restrictOnDelete();
            $table->foreignId('purchase_request_item_id')->constrained('purchase_request_items')->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->decimal('approved_budget', 18, 2);
            $table->timestamps();
            $table->unique(['procurement_project_id', 'purchase_request_item_id']);
        });

        Schema::create('procurement_justifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_project_id')->constrained('procurement_projects')->restrictOnDelete();
            $table->foreignId('procurement_mode_id')->constrained('procurement_modes')->restrictOnDelete();
            $table->foreignId('regulatory_rule_version_id')->nullable()->constrained('regulatory_rule_versions')->restrictOnDelete();
            $table->text('justification');
            $table->jsonb('evidence')->nullable();
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->string('decision', 40)->nullable();
            $table->text('decision_remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('procurement_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_project_id')->constrained('procurement_projects')->restrictOnDelete();
            $table->string('event_type', 64);
            $table->timestampTz('scheduled_at')->nullable();
            $table->timestampTz('held_at')->nullable();
            $table->string('venue', 255)->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('procurement_deadlines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_project_id')->constrained('procurement_projects')->restrictOnDelete();
            $table->string('deadline_type', 64);
            $table->timestampTz('due_at');
            $table->string('legal_basis', 255)->nullable();
            $table->foreignId('regulatory_rule_version_id')->nullable()->constrained('regulatory_rule_versions')->restrictOnDelete();
            $table->foreignId('extended_from_id')->nullable()->constrained('procurement_deadlines')->restrictOnDelete();
            $table->text('extension_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('procurement_publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_project_id')->constrained('procurement_projects')->restrictOnDelete();
            $table->string('channel', 32);
            $table->string('reference_no', 64)->nullable();
            $table->timestampTz('posted_at');
            $table->timestampTz('removed_at')->nullable();
            $table->foreignId('posted_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('document_id')->nullable()->constrained('documents')->restrictOnDelete();
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE procurement_projects ADD CONSTRAINT procurement_projects_check_1 CHECK (approved_budget >= 0)');
            DB::statement('ALTER TABLE procurement_project_items ADD CONSTRAINT procurement_project_items_check_1 CHECK (quantity > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_publications');
        Schema::dropIfExists('procurement_deadlines');
        Schema::dropIfExists('procurement_events');
        Schema::dropIfExists('procurement_justifications');
        Schema::dropIfExists('procurement_project_items');
        Schema::dropIfExists('procurement_lots');
        Schema::dropIfExists('procurement_projects');
        Schema::dropIfExists('procurement_mode_rules');
        Schema::dropIfExists('procurement_modes');
    }
};
