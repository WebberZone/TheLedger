<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use Illuminate\Http\Request;

/**
 * The tracker's PayPal USD/EUR sheets, generalised: every transaction
 * on one payment method in one currency, oldest first, with a running
 * balance in the original currency. Income and cash flows add,
 * expenses subtract.
 */
class LedgerController extends Controller
{
    public function index(Request $request)
    {
        $paymentMethods = PaymentMethod::active()->get();

        $methodId = $request->input('payment_method_id');
        $currency = strtoupper($request->input('currency', config('docket.base_currency')));

        $entries = collect();
        $balance = 0;

        if ($methodId) {
            $transactions = Transaction::query()
                ->where('is_draft', false)
                ->where('payment_method_id', $methodId)
                ->where('currency', $currency)
                ->with('category')
                ->orderBy('occurred_on')
                ->orderBy('id')
                ->get();

            $entries = $transactions->map(
                function (Transaction $t) use (&$balance) {
                    // Gross in the original currency; direction by ledger.
                    $signed = $t->type === TransactionType::Expense
                    ? -$t->total_minor
                    : $t->total_minor;
                    $balance += $signed;

                    return [
                        'transaction' => $t,
                        'signed_minor' => $signed,
                        'balance_minor' => $balance,
                    ];
                }
            );
        }

        return view(
            'ledger.index', [
                'paymentMethods' => $paymentMethods,
                'methodId' => $methodId,
                'currency' => $currency,
                'entries' => $entries,
                'closingMinor' => $balance,
            ]
        );
    }
}
