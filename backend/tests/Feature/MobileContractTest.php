<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Customer;
use App\Models\Module;
use App\Models\PdamOrganization;
use App\Models\SubscriptionModule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MobileContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_upload_is_stored_beneath_tenant_directory(): void
    {
        Storage::fake('private');
        [$org, $user] = $this->fixture();

        $response = $this->actingAs($user, 'sanctum')->post('/api/v1/files/upload', [
            'purpose' => 'meter',
            'file' => UploadedFile::fake()->image('meter.jpg'),
        ]);

        $response->assertCreated();
        $path = $response->json('data.path');
        $this->assertStringStartsWith($org->id.'/meter/', $path);
        Storage::disk('private')->assertExists($path);
    }

    public function test_ktp_ocr_fails_closed_when_provider_is_not_configured(): void
    {
        Storage::fake('private');
        [, $user] = $this->fixture();
        config()->set('services.google_cloud.vision_api_key', null);

        $this->actingAs($user, 'sanctum')->post('/api/v1/prospects/upload-ktp', [
            'ktp_file' => UploadedFile::fake()->image('ktp.jpg'),
        ])->assertStatus(503)
            ->assertJsonPath('error.code', 'OCR_UNAVAILABLE');

        $this->assertSame([], Storage::disk('private')->allFiles());
    }

    public function test_ktp_multipart_contract_rejects_raw_text_instead_of_a_file(): void
    {
        [, $user] = $this->fixture();

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/prospects/upload-ktp', [
            'raw_text' => 'NIK: 1234567890123456',
        ])->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.details.ktp_file.0', 'The ktp file field is required.');
    }

    public function test_ktp_multipart_upload_returns_reviewable_ocr_data(): void
    {
        Storage::fake('private');
        [$org, $user] = $this->fixture();
        config()->set('services.google_cloud.vision_api_key', 'test-key');
        Http::fake([
            'vision.googleapis.com/*' => Http::response([
                'responses' => [[
                    'fullTextAnnotation' => [
                        'text' => "NIK: 1234567890123456\nNama: BUDI\nTempat/Tgl Lahir: PONTIANAK, 17-08-1990\nAlamat: JL MERDEKA",
                    ],
                ]],
            ]),
        ]);

        $response = $this->actingAs($user, 'sanctum')->post('/api/v1/prospects/upload-ktp', [
            'ktp_file' => UploadedFile::fake()->image('ktp.jpg'),
        ])->assertOk()
            ->assertJsonPath('data.parsed.nik', '1234567890123456')
            ->assertJsonPath('data.parsed.confidence', 1);

        $path = $response->json('data.ktp_photo_url');
        $this->assertStringStartsWith($org->id.'/ktp/', $path);
        Storage::disk('private')->assertExists($path);
    }

    public function test_account_profile_password_and_fcm_contracts_work(): void
    {
        [, $user] = $this->fixture();

        $this->actingAs($user, 'sanctum')->putJson('/api/v1/profile/update', [
            'name' => 'Nama Baru',
            'phone' => '0812000000',
        ])->assertOk()->assertJsonPath('data.name', 'Nama Baru');

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/fcm-token', [
            'fcm_token' => 'device-token',
        ])->assertOk();

        $this->actingAs($user, 'sanctum')->putJson('/api/v1/change-password', [
            'current_password' => 'password',
            'new_password' => 'new-password-123',
        ])->assertOk();

        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
        $this->assertSame('device-token', $user->fresh()->fcm_token);
    }

    public function test_portal_bill_detail_is_limited_to_logged_in_customer(): void
    {
        [$org, $user, $customer] = $this->fixture(withCustomer: true);
        [, $otherUser, $otherCustomer] = $this->fixture(withCustomer: true);
        $bill = Bill::create([
            'pdam_org_id' => $org->id,
            'customer_id' => $customer->id,
            'bill_number' => 'MOBILE-BILL-1',
            'period' => '2026-07',
            'amount_due' => 100000,
            'status' => 'unpaid',
        ]);

        $this->actingAs($user, 'sanctum')->getJson("/api/v1/portal/bills/{$bill->id}")
            ->assertOk()->assertJsonPath('data.bill_number', 'MOBILE-BILL-1');

        $this->actingAs($otherUser, 'sanctum')->getJson("/api/v1/portal/bills/{$bill->id}")
            ->assertNotFound();
    }

    private function fixture(bool $withCustomer = false): array
    {
        $org = PdamOrganization::create([
            'code' => 'mobile-'.uniqid(),
            'name' => 'PDAM Mobile',
            'subscription_status' => 'active',
        ]);
        $user = User::create([
            'pdam_org_id' => $org->id,
            'name' => 'Mobile User',
            'email' => uniqid().'@mobile.test',
            'password' => 'password',
            'is_tenant_admin' => true,
            'is_active' => true,
        ]);
        foreach (['APP', 'SRV'] as $code) {
            Module::firstOrCreate(['code' => $code], ['name' => $code, 'is_active' => true]);
            SubscriptionModule::create([
                'pdam_org_id' => $org->id,
                'module_code' => $code,
                'status' => 'active',
            ]);
        }
        $customer = $withCustomer ? Customer::create([
            'pdam_org_id' => $org->id,
            'user_id' => $user->id,
            'customer_number' => 'MC-'.uniqid(),
            'full_name' => 'Mobile Customer',
            'status' => 'active',
        ]) : null;

        return [$org, $user, $customer];
    }
}
