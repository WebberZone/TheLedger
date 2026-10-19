<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ImportTrackerTest extends TestCase
{
    private User $user;

    private string $dbPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dbPath = tempnam(sys_get_temp_dir(), 'ledger_import_test').'.sqlite';
        putenv('DB_DATABASE='.$this->dbPath);
        config(['database.connections.sqlite.database' => $this->dbPath]);

        Artisan::call('migrate', ['--force' => true]);
        $this->user = User::factory()->create();
    }

    protected function tearDown(): void
    {
        if (is_file($this->dbPath)) {
            File::delete($this->dbPath);
        }

        parent::tearDown();
    }

    public function test_import_requires_login(): void
    {
        $this->get('/import')->assertRedirect('/login');
    }

    public function test_import_page_shows_upload_form(): void
    {
        $this->actingAs($this->user)
            ->get('/import')
            ->assertOk()
            ->assertSee('Import Accounts Tracker workbook');
    }

    public function test_upload_rejects_non_xlsx_files(): void
    {
        $this->actingAs($this->user)
            ->post('/import', ['tracker' => $this->tempFile('tracker.csv', 'not xlsx')])
            ->assertSessionHasErrors('tracker');
    }

    public function test_import_runs_the_python_script_and_imports_transactions(): void
    {
        $xlsxPath = $this->buildTrackerXlsx();

        $file = new UploadedFile(
            $xlsxPath,
            'Accounts Tracker.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->actingAs($this->user)
            ->post('/import', ['tracker' => $file]);

        $response->assertRedirect('/import');
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('transactions', [
            'type' => 'expense',
            'counterparty' => 'CloudFlare',
            'amount_gbp_minor' => 676,
        ]);

        $this->assertDatabaseHas('transactions', [
            'type' => 'income',
            'counterparty' => 'Freemius',
            'amount_gbp_minor' => 10678,
        ]);
    }

    private function tempFile(string $name, string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'test').'_'.$name;
        file_put_contents($path, $contents);

        return new UploadedFile($path, $name, null, null, true);
    }

    private function buildTrackerXlsx(): string
    {
        $pythonBin = config('ledger.import.python_bin');
        $path = tempnam(sys_get_temp_dir(), 'import_test').'.xlsx';

        $script = <<<'PY'
import sys
import openpyxl
from datetime import date

path = sys.argv[1]
wb = openpyxl.Workbook()

wb.remove(wb.active)
for name in ['Income', 'Expense', 'CF']:
    ws = wb.create_sheet(name)
    ws['B21'] = 'Date'
    ws['C21'] = 'Category'
    ws['D21'] = 'Sub-Category'
    ws['E21'] = 'Vendor'
    ws['F21'] = 'Currency'
    ws['G21'] = 'FX'
    ws['H21'] = 'Amount'
    ws['I21'] = 'VAT'
    ws['J21'] = 'Total'
    ws['K21'] = 'Amount GBP'
    ws['L21'] = 'Description'
    ws['M21'] = 'Payment Method'

ws = wb['Income']
ws['B22'] = date(2024, 9, 10)
ws['C22'] = 'WordPress plugins'
ws['D22'] = 'WordPress plugins'
ws['E22'] = 'Freemius'
ws['F22'] = 'USD'
ws['G22'] = 0.843863850632492
ws['H22'] = 126.54
ws['I22'] = 0
ws['J22'] = 126.54
ws['K22'] = 106.78
ws['L22'] = 'Payout for July 2024'
ws['M22'] = 'PayPal'

ws = wb['Expense']
ws['B22'] = date(2020, 5, 10)
ws['C22'] = 'Advertising and PR'
ws['D22'] = 'Website Expenses'
ws['E22'] = 'CloudFlare'
ws['F22'] = 'USD'
ws['G22'] = 0.8418430884184309
ws['H22'] = 8.03
ws['I22'] = 0
ws['J22'] = 8.03
ws['K22'] = 6.76
ws['L22'] = 'webberzone.com renewal'
ws['M22'] = 'Revolut Personal'

ws = wb['CF']
ws['B22'] = date(2023, 12, 21)
ws['C22'] = 'Share capital'
ws['D22'] = 'Share capital'
ws['E22'] = "Ajay D'Souza"
ws['F22'] = 'GBP'
ws['G22'] = 1
ws['H22'] = 50
ws['I22'] = 0
ws['J22'] = 50
ws['K22'] = 50
ws['L22'] = 'Share capital'
ws['M22'] = 'Bank transfer'

wb.save(path)
PY;
        $scriptPath = tempnam(sys_get_temp_dir(), 'build_tracker').'.py';
        file_put_contents($scriptPath, $script);

        $output = shell_exec(escapeshellarg($pythonBin).' '.escapeshellarg($scriptPath).' '.escapeshellarg($path).' 2>&1');
        unlink($scriptPath);

        if (! is_file($path)) {
            $this->fail('Failed to build test xlsx: '.$output);
        }

        return $path;
    }
}
