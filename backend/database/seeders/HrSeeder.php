<?php

namespace Database\Seeders;

use App\Models\PdamOrganization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * HrSeeder — modul HR (Kepegawaian & Payroll). Hanya tenant Canada (full).
 *
 * Mengisi seluruh tabel HR: job_positions, organization_units, employee_grades,
 * hr_employees, employee_position_history, shifts, shift_schedules, attendances,
 * overtime_requests, leave_types, leaves, trainings, training_participants,
 * certifications, performance_appraisals, employee_contracts,
 * employment_terminations, payroll_components.
 *
 * Idempotent: dilewati bila hr_employees sudah ada.
 */
class HrSeeder extends Seeder
{
    private const HR_TENANTS = ['pdam-canada'];

    public function run(): void
    {
        if (DB::table('hr_employees')->exists()) {
            return;
        }

        foreach (self::HR_TENANTS as $code) {
            $org = PdamOrganization::where('code', $code)->first();
            if (! $org) {
                continue;
            }
            $this->seedForTenant($org->id);
        }
    }

    private function seedForTenant(int $orgId): void
    {
        $zone = DB::table('zones')->where('pdam_org_id', $orgId)->first();

        // ── Jabatan ──
        $positions = [
            ['DIR', 'Direktur', 1, 20_000_000, 35_000_000],
            ['KABAG', 'Kepala Bagian', 3, 10_000_000, 18_000_000],
            ['STAF', 'Staf', 5, 4_000_000, 8_000_000],
        ];
        $posIds = [];
        foreach ($positions as [$code, $name, $level, $min, $max]) {
            $posIds[$code] = DB::table('job_positions')->insertGetId([
                'pdam_org_id' => $orgId, 'code' => $code, 'name' => $name,
                'structural_level' => $level, 'min_salary' => $min, 'max_salary' => $max,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // ── Unit organisasi ──
        $unitId = DB::table('organization_units')->insertGetId([
            'pdam_org_id' => $orgId, 'code' => 'DIR-UT', 'name' => 'Direktorat Utama',
            'zone_id' => $zone?->id, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $unitOps = DB::table('organization_units')->insertGetId([
            'pdam_org_id' => $orgId, 'code' => 'BAG-OPS', 'name' => 'Bagian Operasional',
            'parent_id' => $unitId, 'zone_id' => $zone?->id, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // ── Golongan ──
        $gradeIds = [];
        foreach ([['IV', 'Golongan IV', 10_000_000, 18_000_000], ['II', 'Golongan II', 4_000_000, 8_000_000]] as [$c, $n, $min, $max]) {
            $gradeIds[$c] = DB::table('employee_grades')->insertGetId([
                'pdam_org_id' => $orgId, 'code' => $c, 'name' => $n,
                'min_salary' => $min, 'max_salary' => $max,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // ── Pegawai ──
        $employees = [
            ['Budi Santoso', 'DIR', $unitId, 'IV', 'L', 'S2', 'tetap', 'K2', 2],
            ['Siti Aminah', 'KABAG', $unitOps, 'IV', 'P', 'S1', 'tetap', 'K1', 1],
            ['Rudi Hartono', 'STAF', $unitOps, 'II', 'L', 'D3', 'kontrak', 'TK', 0],
        ];
        $empIds = [];
        foreach ($employees as $i => [$name, $pos, $unit, $grade, $gender, $edu, $empStatus, $taxStatus, $dependents]) {
            $empIds[$i] = DB::table('hr_employees')->insertGetId([
                'pdam_org_id' => $orgId, 'zone_id' => $zone?->id,
                'position_id' => $posIds[$pos], 'unit_id' => $unit, 'grade_id' => $gradeIds[$grade],
                'nip' => sprintf('NIP%d%03d', $orgId, $i + 1), 'name' => $name,
                'gender' => $gender, 'birth_date' => '198' . $i . '-05-15',
                'phone' => '0812900010' . $i, 'email' => strtolower(str_replace(' ', '.', $name)) . '@pdam.co.id',
                'education' => $edu, 'employment_status' => $empStatus, 'tax_status' => $taxStatus,
                'dependents' => $dependents, 'bank_name' => 'Bank Kalbar',
                'bank_account' => '900' . $i . '11122', 'join_date' => '2021-0' . ($i + 1) . '-01',
                'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // set head unit
        DB::table('organization_units')->where('id', $unitId)->update(['head_employee_id' => $empIds[0]]);
        DB::table('organization_units')->where('id', $unitOps)->update(['head_employee_id' => $empIds[1]]);

        // ── Riwayat jabatan (promosi staf) ──
        DB::table('employee_position_history')->insert([
            'employee_id' => $empIds[2], 'old_position_id' => null, 'new_position_id' => $posIds['STAF'],
            'new_unit_id' => $unitOps, 'effective_date' => '2021-03-01', 'type' => 'recruitment',
            'notes' => 'Penempatan awal.', 'created_at' => now(), 'updated_at' => now(),
        ]);

        // ── Shift + jadwal ──
        $shiftId = DB::table('shifts')->insertGetId([
            'pdam_org_id' => $orgId, 'name' => 'Shift Pagi', 'start_time' => '08:00:00',
            'end_time' => '16:00:00', 'is_overnight' => false, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('shift_schedules')->insert([
            'pdam_org_id' => $orgId, 'employee_id' => $empIds[2], 'shift_id' => $shiftId,
            'start_date' => now()->startOfMonth()->toDateString(), 'unit_id' => $unitOps,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // ── Absensi (3 hari terakhir) ──
        foreach ([2, 1, 0] as $d) {
            DB::table('attendances')->insert([
                'pdam_org_id' => $orgId, 'employee_id' => $empIds[2],
                'date' => now()->subDays($d)->toDateString(),
                'check_in' => '07:58:00', 'check_out' => '16:05:00',
                'status' => 'present', 'source' => 'machine',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // ── Lembur ──
        DB::table('overtime_requests')->insert([
            'pdam_org_id' => $orgId, 'employee_id' => $empIds[2],
            'date' => now()->subDays(2)->toDateString(), 'hours' => 3.0,
            'reason' => 'Perbaikan pipa darurat', 'status' => 'approved',
            'approved_by' => $empIds[1], 'approved_at' => now()->subDay(),
            'created_at' => now()->subDays(2), 'updated_at' => now()->subDay(),
        ]);

        // ── Jenis cuti + cuti ──
        $leaveTypeId = DB::table('leave_types')->insertGetId([
            'pdam_org_id' => $orgId, 'code' => 'TAHUNAN', 'name' => 'Cuti Tahunan',
            'default_quota' => 12, 'is_paid' => true, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('leaves')->insert([
            'pdam_org_id' => $orgId, 'employee_id' => $empIds[2], 'leave_type_id' => $leaveTypeId,
            'start_date' => now()->addDays(10)->toDateString(), 'end_date' => now()->addDays(12)->toDateString(),
            'duration_days' => 3, 'balance_remaining' => 9, 'status' => 'approved',
            'approved_by' => $empIds[1], 'approved_at' => now(), 'reason' => 'Acara keluarga',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // ── Training + peserta ──
        $trainingId = DB::table('trainings')->insertGetId([
            'pdam_org_id' => $orgId, 'title' => 'Pelatihan Manajemen NRW',
            'start_date' => now()->subDays(20)->toDateString(), 'end_date' => now()->subDays(18)->toDateString(),
            'provider' => 'PERPAMSI', 'cost' => 5_000_000, 'status' => 'completed',
            'created_at' => now()->subDays(25), 'updated_at' => now()->subDays(18),
        ]);
        DB::table('training_participants')->insert([
            'training_id' => $trainingId, 'employee_id' => $empIds[1],
            'attendance_status' => 'attended', 'created_at' => now(), 'updated_at' => now(),
        ]);

        // ── Sertifikasi ──
        DB::table('certifications')->insert([
            'pdam_org_id' => $orgId, 'employee_id' => $empIds[1], 'name' => 'Sertifikat Ahli Pengolahan Air',
            'issuing_body' => 'BNSP', 'certificate_number' => 'BNSP-2025-0091',
            'issue_date' => '2025-01-15', 'expiry_date' => '2028-01-15',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // ── Penilaian kinerja ──
        DB::table('performance_appraisals')->insert([
            'pdam_org_id' => $orgId, 'employee_id' => $empIds[2], 'period' => '2026-06',
            'score' => 85.5, 'kpi_data' => json_encode(['kehadiran' => 95, 'produktivitas' => 88]),
            'evaluator_id' => $empIds[1], 'comments' => 'Kinerja baik.', 'status' => 'finalized',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // ── Kontrak (pegawai kontrak) ──
        DB::table('employee_contracts')->insert([
            'pdam_org_id' => $orgId, 'employee_id' => $empIds[2], 'contract_number' => 'PKWT-' . $orgId . '-001',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'type' => 'pkwt', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // ── Komponen payroll ──
        $components = [
            ['GAPOK', 'Gaji Pokok', 'earning', true, null],
            ['TUNJTRANS', 'Tunjangan Transport', 'earning', true, 500_000],
            ['BPJSKES', 'Potongan BPJS Kesehatan', 'deduction', true, null],
        ];
        foreach ($components as [$code, $name, $type, $default, $amount]) {
            DB::table('payroll_components')->insert([
                'pdam_org_id' => $orgId, 'code' => $code, 'name' => $name, 'type' => $type,
                'is_default' => $default, 'default_amount' => $amount,
                'default_percent' => $code === 'BPJSKES' ? 1.0 : null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // ── Terminasi (contoh, status pending) ──
        DB::table('employment_terminations')->insert([
            'pdam_org_id' => $orgId, 'employee_id' => $empIds[2],
            'termination_date' => now()->addDays(30)->toDateString(), 'reason_type' => 'contract_end',
            'reason' => 'Kontrak PKWT berakhir', 'severance_amount' => 0, 'status' => 'pending',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
