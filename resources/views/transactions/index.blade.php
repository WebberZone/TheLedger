<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ $type->pluralLabel() }}</h2>
            <a href="{{ route("$routePrefix.create") }}"
                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                Add
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- Filters --}}
            <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-4">
                <form method="GET" action="{{ route("$routePrefix.index") }}"
                    class="flex flex-wrap items-end gap-4">
                    <div class="w-full sm:w-auto sm:grow sm:max-w-xs">
                        <x-input-label for="q" value="Search" />
                        <x-text-input id="q" name="q" type="search" class="mt-1 block w-full"
                            placeholder="{{ $type->counterpartyLabel() }}, description, notes…"
                            :value="$filters['q'] ?? ''" />
                    </div>
                    <div>
                        <x-input-label for="from" value="From" />
                        <x-text-input id="from" name="from" type="date" class="mt-1 block"
                            :value="$filters['from'] ?? ''" />
                    </div>
                    <div>
                        <x-input-label for="to" value="To" />
                        <x-text-input id="to" name="to" type="date" class="mt-1 block"
                            :value="$filters['to'] ?? ''" />
                    </div>
                    <div>
                        <x-input-label for="filter_category" value="Category" />
                        <select id="filter_category" name="category_id"
                            class="mt-1 block border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm">
                            <option value="">All</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected(($filters['category_id'] ?? '') == $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="filter_unit" value="Business unit" />
                        <select id="filter_unit" name="business_unit_id"
                            class="mt-1 block border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm">
                            <option value="">All</option>
                            @foreach ($businessUnits as $unit)
                                <option value="{{ $unit->id }}" @selected(($filters['business_unit_id'] ?? '') == $unit->id)>{{ $unit->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-center gap-3">
                        <x-primary-button>Filter</x-primary-button>
                        <a href="{{ route("$routePrefix.index") }}" class="text-sm text-slate-600 hover:text-slate-900">Clear</a>
                        <a href="{{ route("$routePrefix.export", request()->query()) }}"
                            class="text-sm text-indigo-600 hover:text-indigo-900 font-medium">Export CSV</a>
                    </div>
                </form>
            </div>

            {{-- Mobile: stacked cards instead of a table. --}}
            <div class="space-y-3 sm:hidden">
                @forelse ($transactions as $transaction)
                    <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <a href="{{ route("$routePrefix.show", $transaction) }}" class="font-semibold text-slate-900">
                                    {{ $transaction->displayName() }}
                                </a>
                                <div class="text-xs text-slate-500 truncate">{{ $transaction->counterparty }}</div>
                            </div>
                            <div class="text-right shrink-0">
                                <div class="font-semibold tabular-nums">£{{ number_format($transaction->amount_gbp_minor / 100, 2) }}</div>
                                @if ($transaction->currency !== config('ledger.base_currency'))
                                    <div class="text-xs text-slate-500 tabular-nums">{{ $transaction->currency }} {{ $transaction->total_major }}</div>
                                @endif
                            </div>
                        </div>
                        <div class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-slate-500">
                            <span>{{ $transaction->occurred_on->format('d M Y') }}</span>
                            @if ($transaction->category)
                                <span aria-hidden="true">·</span><span>{{ $transaction->category->name }}</span>
                            @endif
                            @if ($transaction->paymentMethod)
                                <span aria-hidden="true">·</span><span>{{ $transaction->paymentMethod->name }}</span>
                            @endif
                            @if ($transaction->receipts_count)
                                <span aria-hidden="true">·</span><span>{{ $transaction->receipts_count }} receipt{{ $transaction->receipts_count > 1 ? 's' : '' }}</span>
                            @endif
                        </div>
                        <div class="mt-3 flex gap-4 text-sm border-t border-slate-100 pt-2">
                            <a href="{{ route("$routePrefix.edit", $transaction) }}" class="font-medium text-indigo-600">Edit</a>
                            <form method="POST" action="{{ route("$routePrefix.destroy", $transaction) }}"
                                onsubmit="return confirm('Delete this entry? This is permanent and cannot be undone.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="font-medium text-red-600">Delete</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-8 text-center text-slate-500">
                        Nothing here yet. <a href="{{ route("$routePrefix.create") }}" class="text-indigo-600 underline">Add the first one</a>.
                    </div>
                @endforelse

                @if ($transactions->isNotEmpty())
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 flex justify-between text-sm font-semibold">
                        <span>Total (filtered)</span>
                        <span class="tabular-nums">£{{ number_format($totalGbpMinor / 100, 2) }}</span>
                    </div>
                @endif
            </div>

            {{-- Desktop: table. --}}
            <div class="hidden sm:block bg-white border border-slate-200 shadow-sm rounded-xl overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                        <tr>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">{{ $type->counterpartyLabel() }}</th>
                            <th class="px-4 py-3">Category</th>
                            <th class="px-4 py-3">Unit</th>
                            <th class="px-4 py-3">Paid via</th>
                            <th class="px-4 py-3 text-right">Amount (GBP)</th>
                            <th class="px-4 py-3 text-center">Receipts</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($transactions as $transaction)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 whitespace-nowrap">{{ $transaction->occurred_on->format('d M Y') }}</td>
                                <td class="px-4 py-3">
                                    <a href="{{ route("$routePrefix.show", $transaction) }}" class="font-medium text-slate-900 hover:text-indigo-600">
                                        {{ $transaction->displayName() }}
                                    </a>
                                    <div class="text-xs text-slate-500">{{ $transaction->counterparty }}</div>
                                </td>
                                <td class="px-4 py-3">{{ $transaction->category?->name ?? '—' }}</td>
                                <td class="px-4 py-3">{{ $transaction->businessUnit?->name ?? '—' }}</td>
                                <td class="px-4 py-3">{{ $transaction->paymentMethod?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    £{{ number_format($transaction->amount_gbp_minor / 100, 2) }}
                                    @if ($transaction->currency !== config('ledger.base_currency'))
                                        <div class="text-xs text-slate-500">{{ $transaction->currency }} {{ $transaction->total_major }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center text-slate-500">
                                    {{ $transaction->receipts_count ?: '—' }}
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <a href="{{ route("$routePrefix.edit", $transaction) }}" class="text-indigo-600 hover:text-indigo-900">Edit</a>
                                    <form method="POST" action="{{ route("$routePrefix.destroy", $transaction) }}" class="inline ms-2"
                                        onsubmit="return confirm('Delete this entry? This is permanent and cannot be undone.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-slate-500">
                                    Nothing here yet. <a href="{{ route("$routePrefix.create") }}" class="text-indigo-600 hover:underline">Add the first one</a>.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($transactions->isNotEmpty())
                        <tfoot class="bg-slate-50 font-semibold">
                            <tr>
                                <td colspan="5" class="px-4 py-3 text-right">Total (filtered):</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">£{{ number_format($totalGbpMinor / 100, 2) }}</td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>

            {{ $transactions->links() }}
        </div>
    </div>
</x-app-layout>
