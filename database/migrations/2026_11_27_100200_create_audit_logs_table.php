<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('event', 64);
            $table->nullableMorphs('auditable');
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->jsonb('context')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['event', 'created_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            // Append-only: the database itself refuses UPDATE, DELETE and TRUNCATE.
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION audit_logs_immutable() RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'audit_logs is append-only';
                END;
                $$ LANGUAGE plpgsql;

                CREATE TRIGGER audit_logs_no_update_delete
                    BEFORE UPDATE OR DELETE ON audit_logs
                    FOR EACH ROW EXECUTE FUNCTION audit_logs_immutable();

                CREATE TRIGGER audit_logs_no_truncate
                    BEFORE TRUNCATE ON audit_logs
                    FOR EACH STATEMENT EXECUTE FUNCTION audit_logs_immutable();
            SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_truncate ON audit_logs');
            DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_update_delete ON audit_logs');
        }
        Schema::dropIfExists('audit_logs');
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP FUNCTION IF EXISTS audit_logs_immutable()');
        }
    }
};
