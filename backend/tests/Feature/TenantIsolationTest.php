<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\Customer;
use App\Models\CustomerProspect;
use App\Models\PdamOrganization;
use App\Models\Module;
use App\Models\SubscriptionModule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $user1;
    private User $user2;

    protected function setUp(): void
    {
        parent::setUp();

        $org1 = PdamOrganization::create(['name' => 'PDAM A', 'code' => 'PDAMA']);
        $org2 = PdamOrganization::create(['name' => 'PDAM B', 'code' => 'PDAMB']);

        $this->user1 = User::create([
            'pdam_org_id' => $org1->id,
            'name' => 'Admin A',
            'email' => 'admin_a@pdam.go.id',
            'password' => bcrypt('password'),
            'is_tenant_admin' => true,
        ]);

        $this->user2 = User::create([
            'pdam_org_id' => $org2->id,
            'name' => 'Admin B',
            'email' => 'admin_b@pdam.go.id',
            'password' => bcrypt('password'),
            'is_tenant_admin' => true,
        ]);

        Module::create([
            'code' => 'CRM',
            'name' => 'CRM',
            'is_active' => true,
        ]);
        SubscriptionModule::create([
            'pdam_org_id' => $org2->id,
            'module_code' => 'CRM',
            'status' => 'active',
            'activated_at' => now(),
        ]);
    }

    public function test_user_cannot_see_another_tenant_customers(): void

    {
        Customer::create([
            'pdam_org_id' => $this->user1->pdam_org_id,
            'customer_number' => 'C001',
            'full_name' => 'Budi A',
            'zone_id' => null,
            'tariff_category_id' => null,
            'status' => 'active',
        ]);

        Customer::create([
            'pdam_org_id' => $this->user2->pdam_org_id,
            'customer_number' => 'C002',
            'full_name' => 'Budi B',
            'zone_id' => null,
            'tariff_category_id' => null,
            'status' => 'active',
        ]);


        $response = $this->actingAs($this->user1, 'sanctum')
            ->getJson('/api/v1/customers');

        $response->assertOk();
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertEquals('Budi A', $data[0]['full_name']);
    }

    public function test_user_cannot_access_another_tenant_customer_directly(): void

    {
        $otherCustomer = Customer::create([
            'pdam_org_id' => $this->user2->pdam_org_id,
            'customer_number' => 'C003',
            'full_name' => 'Should Not See',
            'zone_id' => null,
            'tariff_category_id' => null,
            'status' => 'active',
        ]);


        $response = $this->actingAs($this->user1, 'sanctum')
            ->getJson("/api/v1/customers/{$otherCustomer->id}");

        $response->assertNotFound();
    }

    public function test_user_cannot_create_data_in_another_tenant(): void

    {
        $response = $this->actingAs($this->user2, 'sanctum')
            ->postJson('/api/v1/complaints', [
                'category' => 'kebocoran',
                'subject' => 'Test leak',
                'description' => 'Water leaking',
            ]);

        $response->assertCreated(); // created successfully in user2's tenant

        $complaint = Complaint::first();

        $this->assertEquals($this->user2->pdam_org_id, $complaint->pdam_org_id);
        $this->assertNotEquals($this->user1->pdam_org_id, $complaint->pdam_org_id);
    }

    public function test_idor_attack_prevented_when_accessing_other_tenant_resource(): void

    {
        $prospect = CustomerProspect::create([
            'pdam_org_id' => $this->user2->pdam_org_id,
            'registration_number' => 'REG-B-001',
            'full_name' => 'Target Prospect',
            'installation_address' => 'Jl. Target No. 1',
            'email' => 'target@test.com',
            'phone' => '08123456789',
            'zone_id' => null,
            'status' => 'pending_review',
        ]);


        $response = $this->actingAs($this->user1, 'sanctum')
            ->getJson("/api/v1/prospects/{$prospect->id}");

        $response->assertNotFound(); // GlobalScope blocks it
    }
}
