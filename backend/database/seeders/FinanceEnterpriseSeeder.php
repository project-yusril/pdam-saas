<?php

namespace Database\Seeders;

use App\Models\PdamOrganization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * FinanceEnterpriseSeeder — modul FIN+ (keuangan lanjutan). Hanya tenant Canada (full).
 *
 * Mengisi: sales_invoices(+items), purchase_invoices(+items), ar_ap_payments,
 * accounting_periods, tax_records, budgets(+lines), projects, cost_centers,
 * recurring_transactions, bank_accounts, bank_reconciliations, currencies, exchange_rates.
 *
 * Semua invoice/payment TIDAK posting GL (journal_entry_id null) agar neraca inti tetap balance.
 * Idempotent: dilewati bila sales_invoices sudah ada.
 */
class FinanceEnterpriseSeeder extends Seeder
{
    private const FIN_PLUS_TENANTS = ['pdam-canada'];

    public function run(): void
    {
        if (DB::table('sales_invoices')->exists()) {
            return;
        }

        foreach (self::FIN_PLUS_TENANTS as $code) {
            $org = PdamOrganization::where('code', $code)->first();
            if (! $org) {
                continue;
            }
            $this->seedForTenant($org->id);
        }
    }

    private function seedForTenant(int $orgId): void
    {
        $customer = DB::table('customers')->where('pdam_org_id', $orgId)->first();
        $supplier = DB::table('suppliers')->where('pdam_org_id', $orgId)->first();

        // ── Accounting periods (buka 3 bulan) ──
        foreach (['2026-04', '2026-05', '2026-06'] as $i => $period) {
            DB::table('accounting_periods')->insert([
                'pdam_org_id' => $orgId,
                'period' => $period,
                'status' => $i < 2 ? 'closed' : 'open',
                'closed_at' => $i < 2 ? now()->subMonths(2 - $i) : null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // ── Currencies + exchange rate ──
        $idrId = DB::table('currencies')->insertGetId([
            'pdam_org_id' => $orgId, 'code' => 'IDR', 'name' => 'Rupiah', 'symbol' => 'Rp',
            'decimal_places' => 2, 'is_default' => true, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('currencies')->insert([
            'pdam_org_id' => $orgId, 'code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$',
            'decimal_places' => 2, 'is_default' => false, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('exchange_rates')->insert([
            'pdam_org_id' => $orgId, 'from_currency' => 'USD', 'to_currency' => 'IDR',
            'rate' => 16250.000000, 'effective_date' => now()->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // ── Bank accounts + reconciliation ──
        $bankId = DB::table('bank_accounts')->insertGetId([
            'pdam_org_id' => $orgId, 'code' => 'BANK-01', 'account_name' => 'Rekening Operasional',
            'account_number' => '1234567890', 'bank_name' => 'Bank Kalbar', 'currency' => 'IDR',
            'opening_balance' => 500_000_000, 'current_balance' => 480_000_000,
            'coa_account_code' => '1-001', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('bank_reconciliations')->insert([
            'pdam_org_id' => $orgId, 'bank_account_id' => $bankId,
            'statement_date' => now()->subDays(5)->toDateString(),
            'reconciliation_date' => now()->subDays(3)->toDateString(),
            'statement_balance' => 480_500_000, 'book_balance' => 480_000_000,
            'difference' => 500_000, 'status' => 'draft',
            'notes' => 'Selisih biaya admin bank belum dibukukan.',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // ── Cost centers + projects ──
        $ccId = DB::table('cost_centers')->insertGetId([
            'pdam_org_id' => $orgId, 'code' => 'CC-OPS-' . $orgId, 'name' => 'Operasional Distribusi',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $projectId = DB::table('projects')->insertGetId([
            'pdam_org_id' => $orgId, 'code' => 'PRJ-' . $orgId . '-01', 'name' => 'Perluasan Jaringan Zona Utara',
            'status' => 'active', 'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addMonths(6)->toDateString(), 'budget' => 250_000_000,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // ── Budget + lines ──
        $budgetId = DB::table('budgets')->insertGetId([
            'pdam_org_id' => $orgId, 'name' => 'RKAP 2026', 'fiscal_year' => 2026,
            'total_amount' => 1_000_000_000, 'status' => 'approved',
            'approved_at' => now()->subMonths(3), 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([['5-001', 400_000_000, 120_000_000], ['5-002', 600_000_000, 200_000_000]] as [$acc, $amount, $realized]) {
            DB::table('budget_lines')->insert([
                'pdam_org_id' => $orgId, 'budget_id' => $budgetId, 'account_code' => $acc,
                'project_code' => 'PRJ-' . $orgId . '-01', 'cost_center_code' => 'CC-OPS-' . $orgId,
                'amount' => $amount, 'realized' => $realized,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // ── Sales invoice (non-air, mis. jasa sambungan) + item ──
        $siId = DB::table('sales_invoices')->insertGetId([
            'pdam_org_id' => $orgId, 'customer_id' => $customer?->id,
            'invoice_number' => 'SI-' . $orgId . '-0001',
            'invoice_date' => now()->subDays(20)->toDateString(),
            'due_date' => now()->subDays(5)->toDateString(),
            'subtotal' => 1_500_000, 'discount' => 0, 'tax_amount' => 165_000, 'total' => 1_665_000,
            'paid_amount' => 1_665_000, 'status' => 'paid', 'notes' => 'Biaya pemasangan sambungan baru.',
            'created_at' => now()->subDays(20), 'updated_at' => now()->subDays(5),
        ]);
        DB::table('sales_invoice_items')->insert([
            'pdam_org_id' => $orgId, 'sales_invoice_id' => $siId,
            'description' => 'Jasa pemasangan sambungan air', 'quantity' => 1,
            'unit_price' => 1_500_000, 'amount' => 1_500_000, 'account_code' => '4-002',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // ── Purchase invoice + item ──
        $piId = DB::table('purchase_invoices')->insertGetId([
            'pdam_org_id' => $orgId, 'supplier_id' => $supplier?->id,
            'invoice_number' => 'PI-' . $orgId . '-0001',
            'supplier_invoice_number' => 'INV-SUP-9981',
            'invoice_date' => now()->subDays(15)->toDateString(),
            'due_date' => now()->addDays(15)->toDateString(),
            'subtotal' => 25_000_000, 'tax_amount' => 2_750_000, 'total' => 27_750_000,
            'paid_amount' => 0, 'status' => 'unpaid', 'notes' => 'Pembelian pipa distribusi.',
            'created_at' => now()->subDays(15), 'updated_at' => now()->subDays(15),
        ]);
        DB::table('purchase_invoice_items')->insert([
            'pdam_org_id' => $orgId, 'purchase_invoice_id' => $piId,
            'description' => 'Pipa PVC 4 inci', 'quantity' => 200,
            'unit_price' => 125_000, 'amount' => 25_000_000, 'account_code' => '1-003',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // ── AR/AP payment (pelunasan sales invoice di atas) ──
        DB::table('ar_ap_payments')->insert([
            'pdam_org_id' => $orgId, 'payment_type' => 'in',
            'payable_type' => 'sales_invoice', 'payable_id' => $siId,
            'payment_number' => 'ARP-' . $orgId . '-0001', 'amount' => 1_665_000,
            'payment_method' => 'transfer', 'bank_account' => 'Bank Kalbar 1234567890',
            'payment_date' => now()->subDays(5)->toDateString(),
            'notes' => 'Pelunasan invoice pemasangan.',
            'created_at' => now()->subDays(5), 'updated_at' => now()->subDays(5),
        ]);

        // ── Tax record (PPN keluaran) ──
        DB::table('tax_records')->insert([
            'pdam_org_id' => $orgId, 'tax_type' => 'ppn', 'reference_type' => 'sales_invoice',
            'reference_id' => $siId, 'tax_number' => 'PPN-2026-0001',
            'tax_date' => now()->subDays(20)->toDateString(), 'dpp' => 1_500_000, 'tax_amount' => 165_000,
            'status' => 'paid', 'due_date' => now()->addDays(10)->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // ── Recurring transaction (langganan bulanan) ──
        DB::table('recurring_transactions')->insert([
            'pdam_org_id' => $orgId, 'name' => 'Beban Listrik Pompa Bulanan', 'frequency' => 'monthly',
            'amount' => 45_000_000,
            'journal_lines' => json_encode([
                ['account_code' => '5-002', 'type' => 'DEBIT', 'amount' => 45_000_000],
                ['account_code' => '1-001', 'type' => 'KREDIT', 'amount' => 45_000_000],
            ]),
            'next_run_date' => now()->addMonth()->startOfMonth()->toDateString(),
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
