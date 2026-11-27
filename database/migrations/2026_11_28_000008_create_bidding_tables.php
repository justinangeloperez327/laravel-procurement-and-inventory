<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BAC, TWG, meetings, resolutions, RFQs, bidding and evaluation. See docs/05-DATABASE-SCHEMA.md.
 * Check constraints, partial uniqueness and append-only triggers are PostgreSQL-specific.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bac_committees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->restrictOnDelete();
            $table->string('name', 255);
            $table->string('committee_type', 32);
            $table->date('constituted_on');
            $table->string('office_order_reference', 128);
            $table->date('dissolved_on')->nullable();
            $table->timestamps();
        });

        Schema::create('bac_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bac_committee_id')->constrained('bac_committees')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('member_role', 32);
            $table->string('designation_reference', 128)->nullable();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->timestamps();
        });

        Schema::create('twg_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bac_committee_id')->constrained('bac_committees')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('expertise', 255)->nullable();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->timestamps();
        });

        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bac_committee_id')->constrained('bac_committees')->restrictOnDelete();
            $table->foreignId('procurement_project_id')->nullable()->constrained('procurement_projects')->restrictOnDelete();
            $table->string('meeting_type', 64);
            $table->timestampTz('scheduled_at');
            $table->timestampTz('held_at')->nullable();
            $table->string('venue', 255)->nullable();
            $table->boolean('quorum_met')->nullable();
            $table->foreignId('minutes_document_id')->nullable()->constrained('documents')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('meeting_attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained('meetings')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('name', 255)->nullable();
            $table->string('attendee_role', 32);
            $table->boolean('present')->default(true);
            $table->timestamps();
            $table->unique(['meeting_id', 'user_id']);
        });

        Schema::create('bac_resolutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bac_committee_id')->constrained('bac_committees')->restrictOnDelete();
            $table->foreignId('procurement_project_id')->nullable()->constrained('procurement_projects')->restrictOnDelete();
            $table->foreignId('meeting_id')->nullable()->constrained('meetings')->restrictOnDelete();
            $table->string('resolution_no', 64);
            $table->string('title', 255);
            $table->string('resolution_type', 64);
            $table->text('body')->nullable();
            $table->date('adopted_on');
            $table->foreignId('document_id')->nullable()->constrained('documents')->restrictOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['bac_committee_id', 'resolution_no']);
        });

        // Declared here because bac_resolutions is created after procurement_justifications.
        Schema::table('procurement_justifications', function (Blueprint $table) {
            $table->foreignId('bac_resolution_id')->nullable()->constrained('bac_resolutions')->restrictOnDelete();
        });

        Schema::create('rfqs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_project_id')->constrained('procurement_projects')->restrictOnDelete();
            $table->foreignId('procurement_lot_id')->nullable()->constrained('procurement_lots')->restrictOnDelete();
            $table->string('reference_no', 64);
            $table->timestampTz('issued_at')->nullable();
            $table->timestampTz('submission_deadline');
            $table->string('status', 16)->default('draft');
            $table->smallInteger('minimum_suppliers')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('closed_at')->nullable();
            $table->timestamps();
            $table->unique(['procurement_project_id', 'reference_no']);
        });

        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfq_id')->constrained('rfqs')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->timestampTz('received_at');
            $table->decimal('total_amount', 18, 2);
            $table->date('validity_until')->nullable();
            $table->string('status', 32)->default('received');
            $table->boolean('is_late')->default(false);
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['rfq_id', 'supplier_id']);
        });

        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained('quotations')->restrictOnDelete();
            $table->foreignId('procurement_project_item_id')->constrained('procurement_project_items')->restrictOnDelete();
            $table->decimal('unit_price', 18, 4);
            $table->decimal('quantity', 18, 4);
            $table->decimal('total', 18, 2)->storedAs('round(quantity * unit_price, 2)');
            $table->string('brand_model', 255)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('biddings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_project_id')->constrained('procurement_projects')->restrictOnDelete();
            $table->foreignId('procurement_lot_id')->nullable()->constrained('procurement_lots')->restrictOnDelete();
            $table->string('reference_no', 64);
            $table->timestampTz('bid_submission_deadline');
            $table->timestampTz('bid_opening_at');
            $table->string('status', 16)->default('draft');
            $table->decimal('bidding_documents_fee', 18, 2)->nullable();
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('opened_at')->nullable();
            $table->foreignId('opening_meeting_id')->nullable()->constrained('meetings')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['procurement_project_id', 'reference_no']);
        });

        Schema::create('bids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bidding_id')->constrained('biddings')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('procurement_lot_id')->nullable()->constrained('procurement_lots')->restrictOnDelete();
            $table->timestampTz('submitted_at');
            $table->string('receipt_reference', 64);
            $table->decimal('bid_amount', 18, 2)->nullable();
            $table->boolean('is_late')->default(false);
            $table->timestampTz('opened_at')->nullable();
            $table->foreignId('opened_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('status', 32)->default('submitted');
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('withdrawn_at')->nullable();
            $table->timestamps();
            $table->unique(['receipt_reference']);
        });

        Schema::create('bid_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bid_id')->constrained('bids')->restrictOnDelete();
            $table->foreignId('document_id')->constrained('documents')->restrictOnDelete();
            $table->string('envelope', 32);
            $table->boolean('is_sealed')->default(true);
            $table->timestamps();
        });

        Schema::create('evaluation_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_project_id')->constrained('procurement_projects')->restrictOnDelete();
            $table->foreignId('procurement_lot_id')->nullable()->constrained('procurement_lots')->restrictOnDelete();
            $table->smallInteger('sequence');
            $table->string('criterion', 255);
            $table->text('description')->nullable();
            $table->string('criterion_type', 16);
            $table->decimal('weight', 9, 4)->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('locked_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('bid_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bid_id')->nullable()->constrained('bids')->restrictOnDelete();
            $table->foreignId('quotation_id')->nullable()->constrained('quotations')->restrictOnDelete();
            $table->foreignId('evaluation_criterion_id')->constrained('evaluation_criteria')->restrictOnDelete();
            $table->foreignId('evaluator_id')->constrained('users')->restrictOnDelete();
            $table->string('result', 16);
            $table->decimal('score', 9, 4)->nullable();
            $table->text('remarks')->nullable();
            $table->timestampTz('evaluated_at');
            $table->timestamps();
        });

        Schema::create('post_qualifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_project_id')->constrained('procurement_projects')->restrictOnDelete();
            $table->foreignId('procurement_lot_id')->nullable()->constrained('procurement_lots')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('bid_id')->nullable()->constrained('bids')->restrictOnDelete();
            $table->foreignId('quotation_id')->nullable()->constrained('quotations')->restrictOnDelete();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->string('result', 16)->default('pending');
            $table->text('findings')->nullable();
            $table->foreignId('report_document_id')->nullable()->constrained('documents')->restrictOnDelete();
            $table->foreignId('performed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE bac_members ADD CONSTRAINT bac_members_check_1 CHECK (member_role IN ('chair','vice_chair','member','provisional_member','secretariat'))");
            DB::statement('ALTER TABLE meeting_attendees ADD CONSTRAINT meeting_attendees_check_1 CHECK (user_id IS NOT NULL OR name IS NOT NULL)');
            DB::statement('ALTER TABLE quotation_items ADD CONSTRAINT quotation_items_check_1 CHECK (quantity > 0)');
            DB::statement('ALTER TABLE quotation_items ADD CONSTRAINT quotation_items_check_2 CHECK (unit_price >= 0)');
            DB::statement('ALTER TABLE biddings ADD CONSTRAINT biddings_check_1 CHECK (bid_opening_at >= bid_submission_deadline)');
            DB::statement('CREATE UNIQUE INDEX bids_one_per_supplier_lot ON bids (bidding_id, supplier_id, procurement_lot_id) NULLS NOT DISTINCT WHERE withdrawn_at IS NULL');
            DB::statement('ALTER TABLE bid_evaluations ADD CONSTRAINT bid_evaluations_check_1 CHECK ((bid_id IS NULL) <> (quotation_id IS NULL))');
            DB::statement('CREATE UNIQUE INDEX bid_evaluations_unique ON bid_evaluations (bid_id, quotation_id, evaluation_criterion_id, evaluator_id) NULLS NOT DISTINCT');
        }
    }

    public function down(): void
    {
        Schema::table('procurement_justifications', fn (Blueprint $table) => $table->dropConstrainedForeignId('bac_resolution_id'));
        Schema::dropIfExists('post_qualifications');
        Schema::dropIfExists('bid_evaluations');
        Schema::dropIfExists('evaluation_criteria');
        Schema::dropIfExists('bid_documents');
        Schema::dropIfExists('bids');
        Schema::dropIfExists('biddings');
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
        Schema::dropIfExists('rfqs');
        Schema::dropIfExists('bac_resolutions');
        Schema::dropIfExists('meeting_attendees');
        Schema::dropIfExists('meetings');
        Schema::dropIfExists('twg_members');
        Schema::dropIfExists('bac_members');
        Schema::dropIfExists('bac_committees');
    }
};
