<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        // End-of-period balance snapshots per account+currency — the
        // tracker's Balances sheet. Manual entries, pivoted in the view.
        Schema::create(
            'account_balances', function (Blueprint $table) {
                $table->id();
                $table->string('account');            // 'Wise', 'PayPal', ...
                $table->string('currency', 3);
                $table->date('balance_on');
                // Signed: an account can be overdrawn.
                $table->bigInteger('balance_minor');
                $table->string('notes')->nullable();
                $table->timestamps();

                $table->unique(['account', 'currency', 'balance_on']);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('account_balances');
    }
};
