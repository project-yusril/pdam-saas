<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RBAC granular per modul (PRD 16.2 & 4.D.3).
 * roles + permissions + role_permissions + user_roles + user_permissions + employees.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Role bawaan + custom per tenant. pdam_org_id null = template global bawaan.
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id')->nullable()->index();
            $table->string('code');                // super_admin, director, ...
            $table->string('name');
            $table->boolean('is_system_default')->default(false);
            $table->timestamps();

            $table->unique(['pdam_org_id', 'code']);
        });

        // Permission master (global) pola: modul.resource.aksi
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();      // wh.material.create
            $table->string('module_code');         // WH
            $table->string('resource');            // material
            $table->string('action');              // view|create|update|delete|approve|adjust|export
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->unique(['role_id', 'permission_id']);
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->unique(['user_id', 'role_id']);
        });

        // Override per user (allow/deny spesifik) — opsional
        Schema::create('user_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->boolean('granted')->default(true); // true=allow, false=deny
            $table->unique(['user_id', 'permission_id']);
        });

        // Data kepegawaian (PRD 16.2)
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id')->nullable()->index();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('employee_number')->nullable();
            $table->string('position')->nullable();
            $table->unsignedBigInteger('zone_id')->nullable(); // FK ke zones (dibuat di Fase 1)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
        Schema::dropIfExists('user_permissions');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
