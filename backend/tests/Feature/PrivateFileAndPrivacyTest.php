<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\PdamOrganization;
use App\Models\PrivacyAuditEvent;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrivateFileAndPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_url_is_resolved_from_tenant_scoped_resource_not_raw_path(): void
    {
        Storage::fake('private');
        [$org, $owner] = $this->tenantUser(true);
        TenantContext::set($org->id);
        $path = UploadedFile::fake()->image('document.jpg')->store('documents', 'private');
        $document = Document::create([
            'pdam_org_id' => $org->id,
            'doc_number' => 'DOC-001',
            'title' => 'Dokumen Rahasia',
            'category' => 'legal',
            'file_path' => $path,
            'file_type' => 'jpg',
            'file_size' => 100,
            'uploaded_by' => $owner->id,
        ]);
        TenantContext::clear();

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/files/signed-url', [
                'resource_type' => 'document',
                'resource_id' => $document->id,
            ])
            ->assertOk()
            ->assertJsonStructure(['url', 'expires_in']);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/files/signed-url', ['path' => $path])
            ->assertUnprocessable();
    }

    public function test_user_cannot_sign_another_tenant_document(): void
    {
        Storage::fake('private');
        [$orgA, $userA] = $this->tenantUser(true);
        [$orgB, $userB] = $this->tenantUser(true);
        TenantContext::set($orgB->id);
        $path = UploadedFile::fake()->image('other.jpg')->store('documents', 'private');
        $document = Document::create([
            'pdam_org_id' => $orgB->id,
            'doc_number' => 'DOC-OTHER',
            'title' => 'Tenant B',
            'category' => 'legal',
            'file_path' => $path,
            'file_type' => 'jpg',
            'file_size' => 100,
            'uploaded_by' => $userB->id,
        ]);
        TenantContext::clear();

        $this->actingAs($userA, 'sanctum')
            ->postJson('/api/v1/files/signed-url', [
                'resource_type' => 'document',
                'resource_id' => $document->id,
            ])
            ->assertNotFound();
    }

    public function test_tenant_owned_resource_cannot_launder_another_tenant_file_path(): void
    {
        Storage::fake('private');
        [$org, $owner] = $this->tenantUser(true);
        TenantContext::set($org->id);
        $foreignPath = ($org->id + 1).'/document/secret.pdf';
        Storage::disk('private')->put($foreignPath, 'secret');
        $document = Document::create([
            'pdam_org_id' => $org->id,
            'doc_number' => 'DOC-LAUNDERED',
            'title' => 'Invalid reference',
            'category' => 'legal',
            'file_path' => $foreignPath,
            'file_type' => 'pdf',
            'file_size' => 100,
            'uploaded_by' => $owner->id,
        ]);
        TenantContext::clear();

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/files/signed-url', [
                'resource_type' => 'document',
                'resource_id' => $document->id,
            ])
            ->assertNotFound();
    }

    public function test_private_upload_uses_exact_tenant_and_purpose_prefix(): void
    {
        Storage::fake('private');
        [$org, $user] = $this->tenantUser(false);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/files/upload', [
                'purpose' => 'meter',
                'file' => UploadedFile::fake()->image('meter.jpg'),
            ])
            ->assertCreated();

        $path = $response->json('data.path');
        $this->assertStringStartsWith($org->id.'/meter/', $path);
        Storage::disk('private')->assertExists($path);
    }

    public function test_regular_user_cannot_purge_tenant_logs(): void
    {
        [, $user] = $this->tenantUser(false);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/privacy/purge-old-data', ['dry_run' => true])
            ->assertForbidden();
    }

    public function test_admin_purge_defaults_to_dry_run_and_preserves_financial_records(): void
    {
        [$org, $admin] = $this->tenantUser(true);
        $log = ActivityLog::create([
            'pdam_org_id' => $org->id,
            'user_id' => $admin->id,
            'actor_type' => 'user',
            'action' => 'old_action',
        ]);
        ActivityLog::whereKey($log->id)->update(['created_at' => now()->subYears(3)]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/privacy/purge-old-data', [])
            ->assertOk()
            ->assertJsonPath('data.dry_run', true)
            ->assertJsonPath('data.activity_logs_to_purge', 1)
            ->assertJsonPath('data.financial_records_affected', 0);

        $this->assertDatabaseHas('activity_logs', ['id' => $log->id]);
    }

    public function test_privacy_purge_requires_distinct_admin_approval_and_records_immutable_audit(): void
    {
        [$org, $requester] = $this->tenantUser(true);
        $approver = User::create([
            'pdam_org_id' => $org->id,
            'name' => 'Privacy Approver',
            'email' => uniqid().'@privacy.test',
            'password' => 'password',
            'is_tenant_admin' => true,
            'is_active' => true,
        ]);
        $log = ActivityLog::create([
            'pdam_org_id' => $org->id,
            'user_id' => $requester->id,
            'actor_type' => 'user',
            'action' => 'old_action',
        ]);
        ActivityLog::whereKey($log->id)->update(['created_at' => now()->subYears(3)]);

        $this->actingAs($requester, 'sanctum')
            ->postJson('/api/v1/privacy/purge-old-data', [
                'dry_run' => false,
                'confirmation' => 'WRONG',
                'reason' => 'Retensi rutin',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'CONFIRMATION_MISMATCH');

        $response = $this->actingAs($requester, 'sanctum')
            ->postJson('/api/v1/privacy/purge-old-data', [
                'dry_run' => false,
                'confirmation' => 'PURGE_OLD_ACTIVITY_LOGS',
                'reason' => 'Retensi rutin',
            ])
            ->assertStatus(202)
            ->assertJsonPath('data.dry_run', false)
            ->assertJsonPath('data.status', 'pending_approval')
            ->assertJsonPath('data.financial_records_affected', 0);

        $publicId = $response->json('data.purge_request_id');
        $confirmation = $response->json('data.approval_confirmation');
        $this->assertDatabaseHas('activity_logs', ['id' => $log->id]);

        $this->actingAs($requester, 'sanctum')
            ->postJson("/api/v1/privacy/purge-old-data/{$publicId}/approve", ['confirmation' => $confirmation])
            ->assertForbidden();

        $this->actingAs($approver, 'sanctum')
            ->postJson("/api/v1/privacy/purge-old-data/{$publicId}/approve", ['confirmation' => $confirmation])
            ->assertOk()
            ->assertJsonPath('data.status', 'executed')
            ->assertJsonPath('data.purged_activity_logs', 1);

        $this->assertDatabaseMissing('activity_logs', ['id' => $log->id]);
        $this->assertDatabaseHas('privacy_purge_requests', [
            'public_id' => $publicId,
            'status' => 'executed',
            'requested_by' => $requester->id,
            'approved_by' => $approver->id,
        ]);
        $this->assertDatabaseCount('privacy_audit_events', 2);
        $event = PrivacyAuditEvent::where('event_type', 'approved_executed')->firstOrFail();
        $this->expectException(\LogicException::class);
        $event->delete();
    }

    private function tenantUser(bool $admin): array
    {
        $org = PdamOrganization::create([
            'code' => 'privacy-' . uniqid(),
            'name' => 'PDAM Privacy',
            'subscription_status' => 'active',
        ]);
        $user = User::create([
            'pdam_org_id' => $org->id,
            'name' => 'Privacy User',
            'email' => uniqid() . '@privacy.test',
            'password' => 'password',
            'is_tenant_admin' => $admin,
            'is_active' => true,
        ]);

        return [$org, $user];
    }
}
