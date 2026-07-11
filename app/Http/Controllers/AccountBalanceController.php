<?php

namespace App\Http\Controllers;

use App\Models\AccountBalance;
use Illuminate\Http\Request;

/**
 * The tracker's Balances sheet: snapshot balances per account+currency,
 * pivoted so accounts are rows and snapshot dates are columns.
 */
class AccountBalanceController extends Controller
{
    public function index()
    {
        $balances = AccountBalance::orderBy('account')->orderBy('currency')->get();

        // Column per distinct snapshot date, oldest first.
        $dates = $balances->pluck('balance_on')
            ->unique(fn ($d) => $d->toDateString())
            ->sort()
            ->values();

        // Row per account+currency; cells keyed by date string.
        $rows = $balances
            ->groupBy(fn ($b) => $b->account.'|'.$b->currency)
            ->map(
                function ($group) {
                    return [
                        'account' => $group->first()->account,
                        'currency' => $group->first()->currency,
                        'cells' => $group->keyBy(fn ($b) => $b->balance_on->toDateString()),
                        'notes' => $group->pluck('notes')->filter()->unique()->implode('; '),
                    ];
                }
            )
            ->sortKeys()
            ->values();

        return view(
            'balances.index', [
                'rows' => $rows,
                'dates' => $dates,
            ]
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate(
            [
                'account' => ['required', 'string', 'max:255'],
                'currency' => ['required', 'string', 'size:3'],
                'balance_on' => ['required', 'date'],
                'balance' => ['required', 'numeric'],
                'notes' => ['nullable', 'string', 'max:255'],
            ]
        );

        // One snapshot per account+currency+date; re-entering replaces it.
        // whereDate, not updateOrCreate: the date cast stores midnight
        // timestamps, so a raw string match would silently miss.
        $snapshot = AccountBalance::where('account', $data['account'])
            ->where('currency', strtoupper($data['currency']))
            ->whereDate('balance_on', $data['balance_on'])
            ->first();

        $values = [
            'account' => $data['account'],
            'currency' => strtoupper($data['currency']),
            'balance_on' => $data['balance_on'],
            'balance_minor' => (int) round((float) $data['balance'] * 100),
            'notes' => $data['notes'] ?? null,
        ];

        $snapshot ? $snapshot->update($values) : AccountBalance::create($values);

        return redirect()->route('balances.index')->with('status', 'Balance saved successfully.');
    }
}
