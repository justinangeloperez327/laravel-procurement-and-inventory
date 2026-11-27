<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('departments')->restrictOnDelete();
            $table->string('code', 32);
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['agency_id', 'code']);
        });

        Schema::create('fiscal_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 16)->default('open');
            $table->timestamps();

            $table->unique(['agency_id', 'year']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('agency_id')->nullable()->after('id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->after('agency_id')->constrained()->restrictOnDelete();
            $table->string('employee_number', 64)->nullable()->after('email');
            $table->string('position')->nullable()->after('employee_number');
            $table->boolean('is_active')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropConstrainedForeignId('agency_id');
            $table->dropColumn(['employee_number', 'position', 'is_active']);
        });
        Schema::dropIfExists('fiscal_years');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('agencies');
    }
};
