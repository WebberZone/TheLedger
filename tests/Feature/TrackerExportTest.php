<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Expense;
use App\Models\Income;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackerExportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_export_produces_a_valid_xlsx_matching_the_original_structure(): void
    {
        $category = Category::factory()->create(['name' => 'Website Expenses']);
        $paypal = PaymentMethod::factory()->create(['name' => 'PayPal']);

        Expense::factory()->create(
            [
                'occurred_on' => '2026-06-01',
                'counterparty' => 'CloudFlare',
                'category_id' => $category->id,
                'amount_minor' => 2241,
                'vat_minor' => 0,
            ]
        );
        Income::factory()->create(
            [
                'occurred_on' => '2026-02-10',
                'counterparty' => 'Freemius',
                'payment_method_id' => $paypal->id,
                'currency' => 'USD',
                'amount_minor' => 30299,
                'vat_minor' => 0,
            ]
        );

        $response = $this->actingAs($this->user)->get('/export-tracker');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        // Save the streamed body and verify it's a real xlsx with the
        // expected sheets/tables — guards against the export silently
        // producing a corrupt or empty file.
        $tmpPath = tempnam(sys_get_temp_dir(), 'export_test').'.xlsx';
        file_put_contents($tmpPath, $response->streamedContent());

        $pythonBin = base_path('python/venv/bin/python');
        $checkScript = <<<'PY'
import sys
import openpyxl
wb = openpyxl.load_workbook(sys.argv[1])
assert wb.sheetnames == ['Summary', 'Income', 'Expense', 'CF', 'Balances', 'PayPal'], wb.sheetnames
assert 'Income_Table' in wb['Income'].tables
assert 'Expenses_Table' in wb['Expense'].tables
assert 'CF_Table' in wb['CF'].tables
print('OK')
PY;
        $scriptPath = tempnam(sys_get_temp_dir(), 'check').'.py';
        file_put_contents($scriptPath, $checkScript);

        $output = shell_exec(escapeshellarg($pythonBin).' '.escapeshellarg($scriptPath).' '.escapeshellarg($tmpPath).' 2>&1');

        unlink($tmpPath);
        unlink($scriptPath);

        $this->assertStringContainsString('OK', (string) $output, 'Export did not produce a valid xlsx: '.$output);
    }

    public function test_a_long_paypal_usd_ledger_pushes_the_eur_table_down_instead_of_overwriting_it(): void
    {
        $paypal = PaymentMethod::factory()->create(['name' => 'PayPal']);

        // More USD rows than the template leaves above the EUR table.
        for ($day = 1; $day <= 30; $day++) {
            Income::factory()->create(
                [
                    'occurred_on' => sprintf('2026-01-%02d', $day),
                    'payment_method_id' => $paypal->id,
                    'currency' => 'USD',
                    'amount_minor' => 1000,
                    'vat_minor' => 0,
                ]
            );
        }
        Income::factory()->create(
            [
                'occurred_on' => '2026-02-01',
                'description' => 'The only EUR payment',
                'payment_method_id' => $paypal->id,
                'currency' => 'EUR',
                'amount_minor' => 500,
                'vat_minor' => 0,
            ]
        );

        $response = $this->actingAs($this->user)->get('/export-tracker');
        $response->assertOk();

        $tmpPath = tempnam(sys_get_temp_dir(), 'export_test').'.xlsx';
        file_put_contents($tmpPath, $response->streamedContent());

        $checkScript = <<<'PY'
import sys
import openpyxl
from openpyxl.utils.cell import range_boundaries
ws = openpyxl.load_workbook(sys.argv[1])['PayPal']
_, usd_header, _, usd_last = range_boundaries(ws.tables['PayPal_USD'].ref)
_, eur_header, _, eur_last = range_boundaries(ws.tables['PayPal_EUR'].ref)
assert usd_last - usd_header == 30, (usd_header, usd_last)
assert eur_header > usd_last + 1, (usd_last, eur_header)
assert ws.cell(row=eur_header, column=2).value == 'Date', ws.cell(row=eur_header, column=2).value
assert ws.cell(row=eur_header + 1, column=3).value == 'The only EUR payment'
assert eur_last == eur_header + 1, (eur_header, eur_last)
print('OK')
PY;
        $scriptPath = tempnam(sys_get_temp_dir(), 'check').'.py';
        file_put_contents($scriptPath, $checkScript);

        $output = shell_exec(escapeshellarg(base_path('python/venv/bin/python')).' '.escapeshellarg($scriptPath).' '.escapeshellarg($tmpPath).' 2>&1');

        unlink($tmpPath);
        unlink($scriptPath);

        $this->assertStringContainsString('OK', (string) $output, 'PayPal sheet layout is wrong: '.$output);
    }
}
