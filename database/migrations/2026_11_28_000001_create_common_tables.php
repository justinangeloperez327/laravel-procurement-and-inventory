<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shared infrastructure: regulatory rules, documents, approvals, status history, integrations. See docs/05-DATABASE-SCHEMA.md.
 * Check constraints, partial uniqueness and append-only triggers are PostgreSQL-specific.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Per-agency, per-type, per-year counters for human-readable reference numbers.
        Schema::create('reference_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->restrictOnDelete();
            $table->string('sequence_type', 32);
            $table->smallInteger('year');
            $table->unsignedInteger('last_value')->default(0);
            $table->timestamps();
            $table->unique(['agency_id', 'sequence_type', 'year']);
        });

        Schema::create('regulatory_sources', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64);
            $table->string('title', 255);
            $table->string('issuer', 128);
            $table->string('reference', 128);
            $table->string('url', 2048)->nullable();
            $table->date('issued_on')->nullable();
            $table->date('effective_from')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['code']);
        });

        Schema::create('regulatory_rule_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('regulatory_source_id')->constrained('regulatory_sources')->restrictOnDelete();
            $table->string('rule_code', 64);
            $table->smallInteger('version')->default(1);
            $table->string('provision', 255);
            $table->string('legal_reference', 255)->nullable();
            $table->string('entity_type', 64)->nullable();
            $table->string('category', 40)->nullable();
            $table->jsonb('conditions')->nullable();
            $table->jsonb('exceptions')->nullable();
            $table->text('approved_interpretation')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->string('test_reference', 255)->nullable();
            $table->string('status', 40)->default('draft');
            $table->timestamps();
            $table->unique(['rule_code', 'version']);
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->restrictOnDelete();
            $table->nullableMorphs('documentable');
            $table->string('document_type', 64);
            $table->string('title', 255);
            $table->string('classification', 40)->default('internal');
            $table->string('retention_policy', 128)->nullable();
            $table->timestampTz('unsealed_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['document_type']);
        });

        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->restrictOnDelete();
            $table->smallInteger('version');
            $table->string('storage_disk', 64);
            $table->string('storage_key', 1024);
            $table->string('original_name', 255);
            $table->string('mime_type', 128);
            $table->bigInteger('size_bytes');
            $table->string('sha256', 64);
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('signer_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('signed_at')->nullable();
            $table->string('transaction_reference', 128)->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['document_id', 'version']);
        });

        Schema::create('approval_workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->restrictOnDelete();
            $table->string('code', 64);
            $table->string('name', 255);
            $table->string('subject_type', 128);
            $table->boolean('is_active')->default(true);
            $table->date('effective_from')->nullable();
            $table->timestamps();
            $table->unique(['agency_id', 'code']);
        });

        Schema::create('approval_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_workflow_id')->constrained('approval_workflows')->restrictOnDelete();
            $table->smallInteger('sequence');
            $table->string('name', 255);
            $table->string('permission_code', 64);
            $table->boolean('department_scoped')->default(true);
            $table->boolean('is_required')->default(true);
            $table->timestamps();
            $table->unique(['approval_workflow_id', 'sequence']);
        });

        Schema::create('approval_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_step_id')->nullable()->constrained('approval_steps')->restrictOnDelete();
            $table->morphs('approvable');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('on_behalf_of_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('delegation_id')->nullable()->constrained('delegations')->restrictOnDelete();
            $table->string('decision', 40)->default('approved');
            $table->text('remarks')->nullable();
            $table->timestampTz('acted_at');
            $table->timestampTz('created_at')->useCurrent();
        });

        Schema::create('status_histories', function (Blueprint $table) {
            $table->id();
            $table->morphs('subject');
            $table->string('from_status', 64)->nullable();
            $table->string('to_status', 64);
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->timestampTz('changed_at');
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['subject_type', 'subject_id', 'changed_at']);
        });

        Schema::create('integration_logs', function (Blueprint $table) {
            $table->id();
            $table->string('system', 64);
            $table->string('direction', 16);
            $table->string('operation', 128);
            $table->nullableMorphs('subject');
            $table->string('idempotency_key', 128)->nullable();
            $table->jsonb('request_payload')->nullable();
            $table->jsonb('response_payload')->nullable();
            $table->string('status', 32);
            $table->smallInteger('http_status')->nullable();
            $table->text('error')->nullable();
            $table->timestampTz('occurred_at');
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['system', 'idempotency_key']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION forbid_mutation() RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION '% is append-only; post a compensating entry instead', TG_TABLE_NAME;
                END;
                $$ LANGUAGE plpgsql;
            SQL);
            DB::statement('ALTER TABLE regulatory_rule_versions ADD CONSTRAINT regulatory_rule_versions_check_1 CHECK (effective_to IS NULL OR effective_to > effective_from)');
            DB::statement('CREATE TRIGGER document_versions_immutable BEFORE UPDATE OR DELETE ON document_versions FOR EACH ROW EXECUTE FUNCTION forbid_mutation()');
            DB::statement('CREATE TRIGGER document_versions_no_truncate BEFORE TRUNCATE ON document_versions FOR EACH STATEMENT EXECUTE FUNCTION forbid_mutation()');
            DB::statement('CREATE TRIGGER approval_actions_immutable BEFORE UPDATE OR DELETE ON approval_actions FOR EACH ROW EXECUTE FUNCTION forbid_mutation()');
            DB::statement('CREATE TRIGGER approval_actions_no_truncate BEFORE TRUNCATE ON approval_actions FOR EACH STATEMENT EXECUTE FUNCTION forbid_mutation()');
            DB::statement('CREATE TRIGGER status_histories_immutable BEFORE UPDATE OR DELETE ON status_histories FOR EACH ROW EXECUTE FUNCTION forbid_mutation()');
            DB::statement('CREATE TRIGGER status_histories_no_truncate BEFORE TRUNCATE ON status_histories FOR EACH STATEMENT EXECUTE FUNCTION forbid_mutation()');
            DB::statement('CREATE TRIGGER integration_logs_immutable BEFORE UPDATE OR DELETE ON integration_logs FOR EACH ROW EXECUTE FUNCTION forbid_mutation()');
            DB::statement('CREATE TRIGGER integration_logs_no_truncate BEFORE TRUNCATE ON integration_logs FOR EACH STATEMENT EXECUTE FUNCTION forbid_mutation()');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_logs');
        Schema::dropIfExists('status_histories');
        Schema::dropIfExists('approval_actions');
        Schema::dropIfExists('approval_steps');
        Schema::dropIfExists('approval_workflows');
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('regulatory_rule_versions');
        Schema::dropIfExists('regulatory_sources');
        Schema::dropIfExists('reference_sequences');

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP FUNCTION IF EXISTS forbid_mutation()');
        }
    }
};
