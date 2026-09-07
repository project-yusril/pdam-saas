<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PdamOrganization;
use App\Models\ReadingPeriod;
use App\Models\TariffCategory;
use App\Models\TariffTier;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class E2ECustomerJourneyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $org = PdamOrganization::create(['name' => 'PDAM Test', 'code' => 'PDAMTEST']);

        $this->admin = User::create([
            'pdam_org_id' => $org->id,
            'name' => 'Admin Tenant',
            'email' => 'admin@test.go.id',
            'password' => bcrypt('password'),
            'is_tenant_admin' => true,
        ]);

        // Chart of Accounts wajib untuk auto-jurnal saat generate tagihan & pelunasan.
        foreach ([
            ['1-001', 'Kas/Bank', 'ASSET', 'DEBIT'],
            ['1-002', 'Piutang Usaha', 'ASSET', 'DEBIT'],
            ['4-001', 'Pendapatan Air', 'REVENUE', 'KREDIT'],
        ] as [$code, $name, $type, $normal]) {
            ChartOfAccount::create([
                'pdam_org_id' => $org->id,
                'code' => $code,
                'name' => $name,
                'type' => $type,
                'normal_balance' => $normal,
            ]);
        }
    }

    public function test_full_journey_prospect_to_paid_customer(): void
    {
        // 1. Create tariff first
        $tariff = TariffCategory::create([
            'pdam_org_id' => $this->admin->pdam_org_id,
            'code' => 'R1',
            'name' => 'Rumah Tangga A1',
            'group_type' => 'rumah_tangga',
            'abonemen' => 5000,
            'meter_maintenance_fee' => 2500,
            'admin_fee' => 2000,
            'minimum_usage_m3' => 10,
        ]);

        TariffTier::create([
            'pdam_org_id' => $this->admin->pdam_org_id,
            'tariff_category_id' => $tariff->id,
            'tier_order' => 1,
            'min_usage' => 0,
            'max_usage' => 10,
            'price_per_m3' => 1500,
        ]);

        $zone = Zone::create([
            'pdam_org_id' => $this->admin->pdam_org_id,
            'code' => 'Z1',
            'name' => 'Zona Utama',
            'is_main' => true,
        ]);

        // 2. Create customer
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/customers', [
                'full_name' => 'Budi Santoso',
                'phone' => '08123456789',
                'zone_id' => $zone->id,
                'tariff_category_id' => $tariff->id,
            ]);

        $response->assertCreated();
        $customer = Customer::first();
        $this->assertNotNull($customer->customer_number);
        $this->assertEquals('active', $customer->status);

        // 3. Verify customer is isolated to correct tenant
        $this->assertEquals($this->admin->pdam_org_id, $customer->pdam_org_id);

        // 4a. Periode baca harus DITUTUP dulu (aturan Fase 4.4) sebelum generate tagihan.
        ReadingPeriod::create([
            'pdam_org_id' => $this->admin->pdam_org_id,
            'period' => '2026-07',
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        // 4b. Generate a bill (single-customer contract: butuh reading awal/akhir)
        $response = $this->actingAs($this->admin, 'sanctum')

            ->postJson('/api/v1/bills/generate', [
                'customer_id' => $customer->id,
                'period' => '2026-07',
                'previous_reading' => 0,
                'current_reading' => 10,
            ]);

        $response->assertCreated();

        // 5. Verify bill was created
        $bill = Bill::where('customer_id', $customer->id)->first();
        $this->assertNotNull($bill);
        $this->assertGreaterThan(0, $bill->amount_due);
        $this->assertEquals('unpaid', $bill->status);

        // 6. Create payment for bill
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/payments', [
                'type' => 'monthly_bill',
                'bill_id' => $bill->id,
                'channel' => 'cash',
            ]);

        $response->assertCreated();

        // 7. Mark as paid
        $payment = Payment::first();
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/payments/cash', ['payment_id' => $payment->id]);

        $response->assertOk();

        // 8. Verify bill status updated.
        // Pembayaran TUNAI langsung memanggil markPaid → bill jadi 'paid'
        // seketika (beda dengan gateway yang menunggu webhook Midtrans).
        $bill->refresh();
        $this->assertEquals('paid', $bill->status);
        $this->assertEquals('success', $payment->fresh()->status);

    }
}
