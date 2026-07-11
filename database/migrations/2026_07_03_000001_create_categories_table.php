<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create(
            'categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                // Which ledger this category belongs to.
                $table->string('type')->default('expense'); // expense | income | cash_flow
                // Coarser accountant-facing rollup (the tracker's "mapped
                // category"), e.g. 'Website Expenses' -> 'Advertising and PR'.
                $table->string('report_group')->nullable();
                // Accountant's nominal code, e.g. '7500'. Export mapping only.
                $table->string('account_code')->nullable();
                $table->string('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
