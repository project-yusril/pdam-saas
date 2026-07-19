<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\PdamOrganization;
use App\Services\JournalService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Verifikasi mesin double-entry (PRD 3.4): jurnal WAJIB balance,
 * dan periode yang ditutup tidak boleh diposting.
 */
class JournalDoubleEntryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new PdamOrganization)->forceFill(['id' => 1, 'code' => 'test', 'name' => 'Test PDAM'])->save();
        TenantContext::set(1);

        foreach ([['1-001', 'Kas', 'ASSET', 'DEBIT'], ['4-001', 'Pendapatan', 'REVENUE', 'KREDIT']] as [$c, $n, $t, $b]) {
            ChartOfAccount::create(['pdam_org_id' => 1, 'code' => $c, 'name' => $n, 'type' => $t, 'normal_balance' => $b]);
        }
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_balanced_journal_is_saved(): void
    {
        $entry = app(JournalService::class)->record('Test balance', [
            ['account_code' => '1-001', 'type' => 'DEBIT', 'amount' => 100000],
            ['account_code' => '4-001', 'type' => 'KREDIT', 'amount' => 100000],
        ]);

        $this->assertInstanceOf(JournalEntry::class, $entry);
        $this->assertCount(2, $entry->lines);
    }

    public function test_unbalanced_journal_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);

        app(JournalService::class)->record('Tidak balance', [
            ['account_code' => '1-001', 'type' => 'DEBIT', 'amount' => 100000],
            ['account_code' => '4-001', 'type' => 'KREDIT', 'amount' => 90000],
        ]);
    }

    public function test_closed_period_rejects_posting(): void
    {
        AccountingPeriod::create(['pdam_org_id' => 1, 'period' => '2026-01', 'status' => 'closed']);

        $this->expectException(RuntimeException::class);

        app(JournalService::class)->record('Periode tutup', [
            ['account_code' => '1-001', 'type' => 'DEBIT', 'amount' => 5000],
            ['account_code' => '4-001', 'type' => 'KREDIT', 'amount' => 5000],
        ], entryDate: '2026-01-15');
    }
}
