<?php

namespace Database\Seeders;

use App\Models\PdamOrganization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * DmsGisIntegrationSeeder — modul DMS, GIS, Integrasi, Call Center. Hanya tenant Canada (full).
 *
 * Mengisi: documents(+approvals), call_logs, gis_features, gis_network_edges,
 * integrations(+logs), api_keys. Idempotent: dilewati bila documents sudah ada.
 */
class DmsGisIntegrationSeeder extends Seeder
{
    private const FULL_TENANTS = ['pdam-canada'];

    public function run(): void
    {
        if (DB::table('documents')->exists()) {
            return;
        }

        foreach (self::FULL_TENANTS as $code) {
            $org = PdamOrganization::where('code', $code)->first();
            if (! $org) {
                continue;
            }
            $this->seedForTenant($org->id);
        }
    }

    private function seedForTenant(int $orgId): void
    {
        $user = DB::table('users')->where('pdam_org_id', $orgId)->first();
        $customer = DB::table('customers')->where('pdam_org_id', $orgId)->first();
        $zone = DB::table('zones')->where('pdam_org_id', $orgId)->first();
        $complaint = DB::table('complaints')->where('pdam_org_id', $orgId)->first();

        // ── Dokumen + approval ──
        $docId = DB::table('documents')->insertGetId([
            'pdam_org_id' => $orgId, 'doc_number' => 'DOC-' . $orgId . '-0001',
            'title' => 'SOP Penanganan Kebocoran Pipa', 'category' => 'sop', 'version' => 1,
            'file_path' => 'documents/sop-kebocoran.pdf', 'file_type' => 'pdf', 'file_size' => 524288,
            'tags' => json_encode(['sop', 'teknik', 'kebocoran']), 'status' => 'approved',
            'uploaded_by' => $user?->id ?? 0, 'retention_until' => now()->addYears(5)->toDateString(),
            'created_at' => now()->subDays(30), 'updated_at' => now()->subDays(25),
        ]);
        DB::table('document_approvals')->insert([
            'document_id' => $docId, 'approver_id' => $user?->id ?? 0, 'approval_order' => 1,
            'status' => 'approved', 'approved_at' => now()->subDays(25),
            'comments' => 'Disetujui untuk diberlakukan.',
            'created_at' => now()->subDays(28), 'updated_at' => now()->subDays(25),
        ]);

        // ── Call log ──
        DB::table('call_logs')->insert([
            'pdam_org_id' => $orgId, 'call_id' => 'CALL-0001', 'direction' => 'inbound',
            'caller_number' => '081234567890', 'callee_number' => '150XXX',
            'start_time' => now()->subHours(3), 'end_time' => now()->subHours(3)->addMinutes(5),
            'duration_seconds' => 300, 'agent_id' => $user?->id, 'customer_id' => $customer?->id,
            'complaint_id' => $complaint?->id, 'disposition' => 'resolved',
            'notes' => 'Pelanggan melaporkan air keruh, dibuatkan tiket.', 'status' => 'completed',
            'created_at' => now()->subHours(3), 'updated_at' => now()->subHours(3),
        ]);

        // ── GIS features (pipa + node) ──
        $pipeId = DB::table('gis_features')->insertGetId([
            'pdam_org_id' => $orgId, 'feature_type' => 'pipe', 'name' => 'Pipa Distribusi Utama JGM',
            'geometry' => json_encode(['type' => 'LineString', 'coordinates' => [[109.3425, -0.0263], [109.3450, -0.0280]]]),
            'properties' => json_encode(['diameter_mm' => 200, 'material' => 'HDPE']),
            'zone_id' => $zone?->id, 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $node1 = DB::table('gis_features')->insertGetId([
            'pdam_org_id' => $orgId, 'feature_type' => 'valve', 'name' => 'Valve JGM-01',
            'geometry' => json_encode(['type' => 'Point', 'coordinates' => [109.3425, -0.0263]]),
            'zone_id' => $zone?->id, 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $node2 = DB::table('gis_features')->insertGetId([
            'pdam_org_id' => $orgId, 'feature_type' => 'junction', 'name' => 'Junction JGM-02',
            'geometry' => json_encode(['type' => 'Point', 'coordinates' => [109.3450, -0.0280]]),
            'zone_id' => $zone?->id, 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);

        // ── Edge jaringan (topologi) ──
        DB::table('gis_network_edges')->insert([
            'pdam_org_id' => $orgId, 'pipe_feature_id' => $pipeId,
            'from_node_id' => $node1, 'from_node_type' => 'valve',
            'to_node_id' => $node2, 'to_node_type' => 'junction',
            'length_meters' => 320.50, 'created_at' => now(), 'updated_at' => now(),
        ]);

        // ── Integrasi + log ──
        $integrationId = DB::table('integrations')->insertGetId([
            'pdam_org_id' => $orgId, 'name' => 'Payment Gateway', 'provider' => 'midtrans',
            'credentials' => json_encode(['server_key' => 'SB-Mid-server-xxxx']),
            'config' => json_encode(['env' => 'sandbox']), 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('integration_logs')->insert([
            'pdam_org_id' => $orgId, 'integration_id' => $integrationId, 'action' => 'charge',
            'status' => 'success', 'http_status' => 200,
            'request_payload' => json_encode(['order_id' => 'INV-001', 'amount' => 150000]),
            'response_payload' => json_encode(['transaction_status' => 'settlement']),
            'retry_count' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        // ── API key ──
        DB::table('api_keys')->insert([
            'pdam_org_id' => $orgId, 'name' => 'Mobile App Key', 'key' => hash('sha256', Str::random(40)),
            'scopes' => json_encode(['read:bills', 'read:usage']), 'rate_limit' => 1000,
            'expires_at' => now()->addYear(), 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
