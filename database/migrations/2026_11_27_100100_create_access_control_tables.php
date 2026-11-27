<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_read_only')->default(false);
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        // A grant of a role to a user, optionally scoped to one department.
        // Rows are never deleted: revocation and expiry preserve history.
        Schema::create('role_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('role_id')->constrained()->restrictOnDelete();
            $table->foreignId('agency_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('granted_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('granted_at');
            $table->timestampTz('expires_at')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('revoked_at')->nullable();
            $table->text('revocation_reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'revoked_at']);
        });

        // Time-boxed delegation of a single permission. ends_at is mandatory.
        Schema::create('delegations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delegator_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('delegate_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('permission_id')->constrained()->restrictOnDelete();
            $table->foreignId('agency_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->text('reason');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('revoked_at')->nullable();
            $table->text('revocation_reason')->nullable();
            $table->timestamps();

            $table->index(['delegate_id', 'revoked_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE delegations ADD CONSTRAINT delegations_window CHECK (ends_at > starts_at)');
            DB::statement('ALTER TABLE delegations ADD CONSTRAINT delegations_not_self CHECK (delegator_id <> delegate_id)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('delegations');
        Schema::dropIfExists('role_assignments');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
