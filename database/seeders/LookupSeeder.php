<?php

namespace Database\Seeders;

use App\Models\BusinessUnit;
use App\Models\Category;
use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class LookupSeeder extends Seeder
{
    /**
     * Seeds the lookup tables with the taxonomy from the Accounts Tracker
     * spreadsheet. Uses firstOrCreate so re-running never duplicates rows.
     */
    public function run(): void
    {
        foreach (['Beyond Develop', 'WebberZone', 'Aqua-Air', 'Personal'] as $name) {
            BusinessUnit::firstOrCreate(['name' => $name]);
        }

        // Payment methods from the tracker; personal cards are flagged so
        // reports can subtotal what needs reimbursing.
        $paymentMethods = [
            'Amex BA' => true,
            'Amex Silver' => true,
            'Amex Silver Bus' => false,
            'Revolut Personal' => true,
            'Revolut Business' => false,
            'Wise' => false,
            'Wise CC' => false,
            'PayPal' => false,
            'Amazon' => false,
            'Bank transfer' => false,
        ];

        foreach ($paymentMethods as $name => $isPersonal) {
            PaymentMethod::firstOrCreate(['name' => $name], ['is_personal' => $isPersonal]);
        }

        // Expense sub-categories -> the tracker's "mapped category"
        // (report_group). account_code stays null until the accountant's
        // chart is mapped in.
        $expenseCategories = [
            'Advertising and Promotion' => 'Advertising and PR',
            'Website Expenses' => 'Advertising and PR',
            'Equipment' => 'Equipment',
            'Meals and Entertainment' => 'Meals and Entertainment',
            'Accomodation' => 'Travel and subsistence',
            'Travel' => 'Travel and subsistence',
            'Travel Meals' => 'Travel and subsistence',
            'Professional Services' => 'Professional Services',
            'Software' => 'Software',
            'Subscriptions' => 'Software',
            'Sundry Expenses' => 'Sundry Expenses',
        ];

        $sort = 10;
        foreach ($expenseCategories as $name => $group) {
            Category::firstOrCreate(
                ['name' => $name, 'type' => 'expense'],
                ['report_group' => $group, 'sort_order' => $sort]
            );
            $sort += 10;
        }

        $incomeCategories = ['WordPress plugins', 'Web development', 'Coffee/Donations'];
        $sort = 10;
        foreach ($incomeCategories as $name) {
            Category::firstOrCreate(
                ['name' => $name, 'type' => 'income'],
                ['report_group' => $name, 'sort_order' => $sort]
            );
            $sort += 10;
        }

        $cashFlowCategories = ['Share capital', 'Director Loan'];
        $sort = 10;
        foreach ($cashFlowCategories as $name) {
            Category::firstOrCreate(
                ['name' => $name, 'type' => 'cash_flow'],
                ['report_group' => $name, 'sort_order' => $sort]
            );
            $sort += 10;
        }
    }
}
