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
}
