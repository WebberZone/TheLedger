<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        // One table for expenses, income and cash flows — they share the
        // exact same shape (mirrors the three tables in the old tracker
        // spreadsheet). `type` discriminates; the models scope by it.
        Schema::create(
            'transactions',
            function (Blueprint $table) {
                $table->id();
                $table->string('type')->index(); // expense | income | cash_flow
                // Transaction date, not entry date. Indexed for year ranges.
                $table->date('occurred_on')->index();
                // Vendor for expenses, customer for income/cash flows.
                $table->string('counterparty');
                $table->string('description')->nullable();

                // Money is integer minor units (pence/cents) — never floats.
                // Net + VAT in the original currency; gross = amount + vat.
                $table->unsignedInteger('amount_minor');
                $table->unsignedInteger('vat_minor')->default(0);
                $table->string('currency', 3)->default('GBP');
                // GBP per 1 unit of `currency`; GBP -> 1. Often derived from
                // the actual GBP charged on the card statement.
                $table->decimal('fx_rate', 12, 6)->default(1);
                // round((amount+vat) * fx) — every total/report uses this.
                $table->unsignedInteger('amount_gbp_minor');

                $table->foreignId('category_id')->nullable()->constrained();
                $table->foreignId('business_unit_id')->nullable()->constrained();
                $table->foreignId('payment_method_id')->nullable()->constrained();

                // Rechargeable costs (e.g. Aqua-Air); billed_ref once invoiced.
                $table->boolean('is_billable')->default(false);
                $table->string('billed_ref')->nullable();

                // Capture flow: created as a draft the moment the photo lands,
                // hidden from lists/exports until the user confirms the form.
                $table->boolean('is_draft')->default(false);

                $table->text('notes')->nullable();
                // Raw vision-model output kept for audit.
                $table->json('ocr_json')->nullable();

                $table->timestamps();
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
