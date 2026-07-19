<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_positions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->string('code', 20);
            $table->string('name', 100);
            $table->integer('structural_level')->nullable();
            $table->decimal('min_salary', 15, 2)->nullable();
            $table->decimal('max_salary', 15, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('organization_units', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->string('code', 20);
            $table->string('name', 100);
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedBigInteger('head_employee_id')->nullable();
            $table->unsignedBigInteger('zone_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('employee_grades', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->string('code', 10);
            $table->string('name', 50);
            $table->decimal('min_salary', 15, 2);
            $table->decimal('max_salary', 15, 2);
            $table->timestamps();
        });

        Schema::create('hr_employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('zone_id')->nullable();
            $table->unsignedBigInteger('position_id')->nullable();
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->unsignedBigInteger('grade_id')->nullable();
            $table->string('nip', 20)->nullable();
            $table->string('nik', 20)->nullable();
            $table->string('npwp', 20)->nullable();
            $table->string('no_bpjs_kesehatan', 20)->nullable();
            $table->string('no_bpjs_tk', 20)->nullable();
            $table->string('name', 100);
            $table->string('gender', 10)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email', 100)->nullable();
            $table->text('address')->nullable();
            $table->string('education', 50)->nullable();
            $table->string('employment_status', 30)->default('kontrak');
            $table->string('tax_status', 5)->default('TK');
            $table->integer('dependents')->default(0);
            $table->string('bank_name', 50)->nullable();
            $table->string('bank_account', 30)->nullable();
            $table->date('join_date');
            $table->date('resign_date')->nullable();
            $table->string('status', 20)->default('active');
            $table->string('photo_url')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_position_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('old_position_id')->nullable();
            $table->unsignedBigInteger('new_position_id');
            $table->unsignedBigInteger('old_unit_id')->nullable();
            $table->unsignedBigInteger('new_unit_id')->nullable();
            $table->date('effective_date');
            $table->string('type', 20)->default('mutation');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->string('name', 50);
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_overnight')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('shift_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('shift_id');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->timestamps();
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->unsignedBigInteger('employee_id');
            $table->date('date');
            $table->time('check_in')->nullable();
            $table->time('check_out')->nullable();
            $table->decimal('check_in_lat', 10, 7)->nullable();
            $table->decimal('check_in_lng', 10, 7)->nullable();
            $table->string('status', 20)->default('present');
            $table->string('source', 20)->default('machine');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->unsignedBigInteger('employee_id');
            $table->date('date');
            $table->decimal('hours', 4, 2);
            $table->string('reason');
            $table->string('status', 20)->default('pending');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->string('code', 20);
            $table->string('name', 50);
            $table->integer('default_quota')->default(12);
            $table->boolean('is_paid')->default(true);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('leaves', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('leave_type_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('duration_days');
            $table->decimal('balance_remaining', 5, 1)->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('attachment_url')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('trainings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->string('title', 200);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('provider', 100)->nullable();
            $table->decimal('cost', 15, 2)->nullable();
            $table->string('status', 20)->default('planned');
            $table->timestamps();
        });

        Schema::create('training_participants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('training_id');
            $table->unsignedBigInteger('employee_id');
            $table->string('attendance_status', 20)->default('registered');
            $table->timestamps();
        });

        Schema::create('certifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->unsignedBigInteger('employee_id');
            $table->string('name', 200);
            $table->string('issuing_body', 100)->nullable();
            $table->string('certificate_number', 100)->nullable();
            $table->date('issue_date');
            $table->date('expiry_date')->nullable();
            $table->string('attachment_url')->nullable();
            $table->timestamps();
        });

        Schema::create('performance_appraisals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->unsignedBigInteger('employee_id');
            $table->string('period', 7);
            $table->decimal('score', 5, 2)->nullable();
            $table->json('kpi_data')->nullable();
            $table->unsignedBigInteger('evaluator_id')->nullable();
            $table->text('comments')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamps();
        });

        Schema::create('employee_contracts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->unsignedBigInteger('employee_id');
            $table->string('contract_number', 30)->unique();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('type', 20)->default('pkwt');
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('employment_terminations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->unsignedBigInteger('employee_id');
            $table->date('termination_date');
            $table->string('reason_type', 30);
            $table->text('reason')->nullable();
            $table->decimal('severance_amount', 15, 2)->nullable();
            $table->decimal('other_compensation', 15, 2)->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamps();
        });

        Schema::create('payroll_components', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdam_org_id');
            $table->string('code', 20);
            $table->string('name', 100);
            $table->string('type', 10);
            $table->boolean('is_default')->default(false);
            $table->decimal('default_amount', 15, 2)->nullable();
            $table->decimal('default_percent', 8, 4)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_components');
        Schema::dropIfExists('employment_terminations');
        Schema::dropIfExists('employee_contracts');
        Schema::dropIfExists('performance_appraisals');
        Schema::dropIfExists('certifications');
        Schema::dropIfExists('training_participants');
        Schema::dropIfExists('trainings');
        Schema::dropIfExists('leaves');
        Schema::dropIfExists('leave_types');
        Schema::dropIfExists('overtime_requests');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('shift_schedules');
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('employee_position_history');
        Schema::dropIfExists('hr_employees');
        Schema::dropIfExists('employee_grades');
        Schema::dropIfExists('organization_units');
        Schema::dropIfExists('job_positions');
    }
};
