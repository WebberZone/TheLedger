<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">Dashboard</h2>
            <a href="{{ route('capture.create') }}"
                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                Capture receipt
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

            {{-- All-time totals: the same as the Reports Total line. --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-5">
                    <div class="text-xs font-medium text-slate-500 uppercase tracking-wider">All-time income</div>
                    <div class="mt-1 text-2xl font-semibold text-slate-900">
                        £{{ number_format($allTimeIncome / 100, 2) }}</div>
                </div>
                <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-5">
                    <div class="text-xs font-medium text-slate-500 uppercase tracking-wider">All-time expenses</div>
                    <div class="mt-1 text-2xl font-semibold text-slate-900">
                        £{{ number_format($allTimeExpense / 100, 2) }}</div>
                </div>
                <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-5">
                    <div class="text-xs font-medium text-slate-500 uppercase tracking-wider">All-time profit</div>
                    <div class="mt-1 text-2xl font-semibold {{ $allTimeProfit < 0 ? 'text-red-600' : 'text-green-700' }}">
                        {{ $allTimeProfit < 0 ? '-' : '' }}£{{ number_format(abs($allTimeProfit) / 100, 2) }}
                    </div>
                </div>
                <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-5">
                    <div class="text-xs font-medium text-slate-500 uppercase tracking-wider">All-time cash flows</div>
                    <div class="mt-1 text-2xl font-semibold text-slate-900">
                        £{{ number_format($allTimeCashFlow / 100, 2) }}</div>
                </div>
            </div>

            {{-- Headline numbers: this year, with this month underneath. --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-5">
                    <div class="text-xs font-medium text-slate-500 uppercase tracking-wider">Income this year</div>
                    <div class="mt-1 text-2xl font-semibold text-slate-900">£{{ number_format($yearIncome / 100, 2) }}</div>
                    <div class="mt-1 text-sm text-slate-500">£{{ number_format($monthIncome / 100, 2) }} this month</div>
                </div>
                <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-5">
                    <div class="text-xs font-medium text-slate-500 uppercase tracking-wider">Expenses this year</div>
                    <div class="mt-1 text-2xl font-semibold text-slate-900">£{{ number_format($yearExpense / 100, 2) }}</div>
                    <div class="mt-1 text-sm text-slate-500">£{{ number_format($monthExpense / 100, 2) }} this month</div>
                </div>
                <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-5">
                    <div class="text-xs font-medium text-slate-500 uppercase tracking-wider">Profit this year</div>
                    <div class="mt-1 text-2xl font-semibold {{ $yearProfit < 0 ? 'text-red-600' : 'text-green-700' }}">
                        {{ $yearProfit < 0 ? '-' : '' }}£{{ number_format(abs($yearProfit) / 100, 2) }}
                    </div>
                    <div class="mt-1 text-sm text-slate-500">income minus expenses</div>
                </div>
                <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-5">
                    <div class="text-xs font-medium text-slate-500 uppercase tracking-wider">To reimburse</div>
                    <div class="mt-1 text-2xl font-semibold text-slate-900">£{{ number_format($toReimburse / 100, 2) }}</div>
                    <div class="mt-1 text-sm text-slate-500">personal cards this year</div>
                </div>
            </div>

            <div class="grid lg:grid-cols-3 gap-8 items-start">
                {{-- Recent activity across all three ledgers. --}}
                <div class="lg:col-span-2 bg-white border border-slate-200 shadow-sm rounded-xl overflow-x-auto">
                    <div class="px-4 pt-4 text-sm font-semibold text-slate-700">Recent activity</div>
                    @if ($recent->isEmpty())
                        <div class="p-6 text-sm text-slate-500">
                            Nothing yet. <a href="{{ route('expenses.create') }}"
                                class="text-indigo-600 underline">Add the first transaction</a>.
                        </div>
                    @else
                        <table class="min-w-full divide-y divide-slate-200 text-sm mt-2">
                            <thead class="bg-slate-50 text-xs font-medium text-slate-500 uppercase tracking-wider">
                                <tr>
                                    <th class="px-4 py-3 text-left">Date</th>
                                    <th class="px-4 py-3 text-left">Ledger</th>
                                    <th class="px-4 py-3 text-left">Description</th>
                                    <th class="px-4 py-3 text-left">Category</th>
                                    <th class="px-4 py-3 text-right">Amount (GBP)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($recent as $transaction)
                                    <tr>
                                        <td class="px-4 py-3 whitespace-nowrap text-slate-600">
                                            {{ $transaction->occurred_on->format('d M Y') }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <span @class([
                                                'inline-flex px-2 py-0.5 rounded-full text-xs font-medium',
                                                'bg-rose-50 text-rose-700' => $transaction->type === \App\Enums\TransactionType::Expense,
                                                'bg-green-50 text-green-700' => $transaction->type === \App\Enums\TransactionType::Income,
                                                'bg-sky-50 text-sky-700' => $transaction->type === \App\Enums\TransactionType::CashFlow,
                                            ])>
                                                {{ $transaction->type->pluralLabel() }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <a href="{{ route($transaction->type->routePrefix().'.show', $transaction) }}"
                                                class="font-medium text-slate-900 hover:text-indigo-600">
                                                {{ $transaction->displayName() }}
                                            </a>
                                        </td>
                                        <td class="px-4 py-3 text-slate-600">{{ $transaction->category?->name ?? '—' }}</td>
                                        <td class="px-4 py-3 text-right text-slate-700">£{{ number_format($transaction->amount_gbp_minor / 100, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>

                <div class="space-y-8">
                    {{-- Where this year's spend went. --}}
                    <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-4">
                        <div class="text-sm font-semibold text-slate-700">Top expense categories this year</div>
                        @if ($topCategories->isEmpty())
                            <div class="mt-3 text-sm text-slate-500">No expenses recorded this year.</div>
                        @else
                            <ul class="mt-3 space-y-3">
                                @foreach ($topCategories as $row)
                                    <li>
                                        <div class="flex justify-between text-sm">
                                            <span class="text-slate-700">{{ $row->category?->name ?? '(uncategorised)' }}</span>
                                            <span class="font-medium text-slate-900">£{{ number_format($row->total / 100, 2) }}</span>
                                        </div>
                                        <div class="mt-1 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                                            <div class="h-full bg-indigo-500 rounded-full"
                                                style="width: {{ round($row->total / $topCategories->max('total') * 100) }}%"></div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    {{-- Where this year's income came from. --}}
                    <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-4">
                        <div class="text-sm font-semibold text-slate-700">Top income categories this year</div>
                        @if ($topIncomeCategories->isEmpty())
                            <div class="mt-3 text-sm text-slate-500">No income recorded this year.</div>
                        @else
                            <ul class="mt-3 space-y-3">
                                @foreach ($topIncomeCategories as $row)
                                    <li>
                                        <div class="flex justify-between text-sm">
                                            <span class="text-slate-700">{{ $row->category?->name ?? '(uncategorised)' }}</span>
                                            <span class="font-medium text-slate-900">£{{ number_format($row->total / 100, 2) }}</span>
                                        </div>
                                        <div class="mt-1 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                                            <div class="h-full bg-green-500 rounded-full"
                                                style="width: {{ round($row->total / $topIncomeCategories->max('total') * 100) }}%"></div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    {{-- Quick entry links. --}}
                    <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-4">
                        <div class="text-sm font-semibold text-slate-700">Quick actions</div>
                        <div class="mt-3 grid grid-cols-2 gap-2 text-sm">
                            <a href="{{ route('expenses.create') }}"
                                class="px-3 py-2 rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-50 text-center">Add expense</a>
                            <a href="{{ route('income.create') }}"
                                class="px-3 py-2 rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-50 text-center">Add income</a>
                            <a href="{{ route('cashflows.create') }}"
                                class="px-3 py-2 rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-50 text-center">Add cash flow</a>
                            <a href="{{ route('reports.index') }}"
                                class="px-3 py-2 rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-50 text-center">View reports</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
