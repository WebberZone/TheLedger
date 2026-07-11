<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">Reports</h2>
            <a href="{{ route('tracker.export') }}"
                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                Export XLSX
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

            @if ($years->isEmpty())
                <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-8 text-center text-slate-500">
                    Nothing to report yet — add some transactions first.
                </div>
            @else
                {{-- Year summary: the tracker's Summary sheet. --}}
                <div class="bg-white border border-slate-200 shadow-sm rounded-xl overflow-x-auto">
                    <div class="px-4 pt-4 text-sm font-semibold text-slate-700">Summary by year
                        <span class="font-normal text-slate-500">(earliest year includes prior history)</span>
                    </div>
                    <table class="min-w-full divide-y divide-slate-200 text-sm mt-2">
                        <thead class="bg-slate-50 text-xs font-medium text-slate-500 uppercase tracking-wider">
                            <tr>
                                <th class="px-4 py-3 text-left">Year</th>
                                <th class="px-4 py-3 text-right">Income</th>
                                <th class="px-4 py-3 text-right">Expenses</th>
                                <th class="px-4 py-3 text-right">Profit</th>
                                <th class="px-4 py-3 text-right">Cash flows</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($summary as $row)
                                <tr>
                                    <td class="px-4 py-3 font-medium">{{ $row['year'] }}</td>
                                    <td class="px-4 py-3 text-right">£{{ number_format($row['income'] / 100, 2) }}</td>
                                    <td class="px-4 py-3 text-right">£{{ number_format($row['expense'] / 100, 2) }}</td>
                                    <td class="px-4 py-3 text-right font-medium {{ $row['profit'] < 0 ? 'text-red-600' : 'text-green-700' }}">
                                        {{ $row['profit'] < 0 ? '-' : '' }}£{{ number_format(abs($row['profit']) / 100, 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-right">£{{ number_format($row['cash_flow'] / 100, 2) }}</td>
                                </tr>
                            @endforeach
                            <tr class="bg-slate-50 font-semibold">
                                <td class="px-4 py-3">Total</td>
                                <td class="px-4 py-3 text-right">£{{ number_format($summary->sum('income') / 100, 2) }}</td>
                                <td class="px-4 py-3 text-right">£{{ number_format($summary->sum('expense') / 100, 2) }}</td>
                                <td class="px-4 py-3 text-right {{ $summary->sum('profit') < 0 ? 'text-red-600' : 'text-green-700' }}">
                                    {{ $summary->sum('profit') < 0 ? '-' : '' }}£{{ number_format(abs($summary->sum('profit')) / 100, 2) }}
                                </td>
                                <td class="px-4 py-3 text-right">£{{ number_format($summary->sum('cash_flow') / 100, 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                {{-- Reusable matrix table --}}
                @foreach ([
                    'Expenses by report group (accountant view)' => $expensesByGroup,
                    'Expenses by category' => $expensesByCategory,
                    'Income by category' => $incomeByCategory,
                    'Expenses by payment method' => $byPaymentMethod,
                ] as $title => $rows)
                    @if ($rows->isNotEmpty())
                        <div class="bg-white border border-slate-200 shadow-sm rounded-xl overflow-x-auto">
                            <div class="px-4 pt-4 text-sm font-semibold text-slate-700">{{ $title }}</div>
                            <table class="min-w-full divide-y divide-slate-200 text-sm mt-2">
                                <thead class="bg-slate-50 text-xs font-medium text-slate-500 uppercase tracking-wider">
                                    <tr>
                                        <th class="px-4 py-3 text-left"></th>
                                        @foreach ($years as $year)
                                            <th class="px-4 py-3 text-right">{{ $year }}</th>
                                        @endforeach
                                        <th class="px-4 py-3 text-right">Total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach ($rows as $label => $cells)
                                        <tr>
                                            <td class="px-4 py-3 font-medium">{{ $label }}</td>
                                            @foreach ($years as $year)
                                                <td class="px-4 py-3 text-right text-slate-700">
                                                    {{ $cells[$year] ? '£'.number_format($cells[$year] / 100, 2) : '—' }}
                                                </td>
                                            @endforeach
                                            <td class="px-4 py-3 text-right font-medium">£{{ number_format($cells->sum() / 100, 2) }}</td>
                                        </tr>
                                    @endforeach
                                    <tr class="bg-slate-50 font-semibold">
                                        <td class="px-4 py-3">Total</td>
                                        @foreach ($years as $year)
                                            <td class="px-4 py-3 text-right">£{{ number_format($rows->sum(fn ($cells) => $cells[$year]) / 100, 2) }}</td>
                                        @endforeach
                                        <td class="px-4 py-3 text-right">£{{ number_format($rows->sum(fn ($cells) => $cells->sum()) / 100, 2) }}</td>
                                    </tr>
                                    @if ($title === 'Expenses by payment method' && $personalTotals->sum() > 0)
                                        <tr class="text-slate-600">
                                            <td class="px-4 py-3 italic">of which personal cards (to reimburse)</td>
                                            @foreach ($years as $year)
                                                <td class="px-4 py-3 text-right italic">£{{ number_format($personalTotals[$year] / 100, 2) }}</td>
                                            @endforeach
                                            <td class="px-4 py-3 text-right italic">£{{ number_format($personalTotals->sum() / 100, 2) }}</td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    @endif
                @endforeach
            @endif
        </div>
    </div>
</x-app-layout>
