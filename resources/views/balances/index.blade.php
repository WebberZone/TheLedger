<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">Account balances</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- Pivot: accounts x snapshot dates --}}
            <div class="bg-white border border-slate-200 shadow-sm rounded-xl overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs font-medium text-slate-500 uppercase tracking-wider">
                        <tr>
                            <th class="px-4 py-3 text-left">Account</th>
                            <th class="px-4 py-3 text-left">Currency</th>
                            @foreach ($dates as $date)
                                <th class="px-4 py-3 text-right">{{ $date->format('d M Y') }}</th>
                            @endforeach
                            <th class="px-4 py-3 text-left">Notes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($rows as $row)
                            <tr>
                                <td class="px-4 py-3 font-medium">{{ $row['account'] }}</td>
                                <td class="px-4 py-3">{{ $row['currency'] }}</td>
                                @foreach ($dates as $date)
                                    @php $cell = $row['cells'][$date->toDateString()] ?? null; @endphp
                                    <td class="px-4 py-3 text-right {{ $cell && $cell->balance_minor < 0 ? 'text-red-600' : '' }}">
                                        {{ $cell ? number_format($cell->balance_minor / 100, 2) : '—' }}
                                    </td>
                                @endforeach
                                <td class="px-4 py-3 text-slate-500">{{ $row['notes'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ 3 + $dates->count() }}" class="px-4 py-8 text-center text-slate-500">
                                    No balances recorded yet — add the first snapshot below.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Add / replace a snapshot --}}
            <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-4">
                <h3 class="font-medium text-slate-900 mb-4 text-sm">Record a balance</h3>
                <form method="POST" action="{{ route('balances.store') }}" class="flex flex-wrap items-end gap-4">
                    @csrf
                    <div>
                        <x-input-label for="account" value="Account" />
                        <x-text-input id="account" name="account" type="text" class="mt-1 block"
                            :value="old('account')" list="account-names" required />
                        <datalist id="account-names">
                            @foreach ($rows->pluck('account')->unique() as $name)
                                <option value="{{ $name }}"></option>
                            @endforeach
                        </datalist>
                        <x-input-error class="mt-2" :messages="$errors->get('account')" />
                    </div>
                    <div>
                        <x-input-label for="bal_currency" value="Currency" />
                        <select id="bal_currency" name="currency"
                            class="mt-1 block border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm">
                            @foreach (settings('currencies') as $code)
                                <option value="{{ $code }}" @selected(old('currency') === $code)>{{ $code }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="balance_on" value="As at" />
                        <x-text-input id="balance_on" name="balance_on" type="date" class="mt-1 block"
                            :value="old('balance_on', now()->toDateString())" required />
                        <x-input-error class="mt-2" :messages="$errors->get('balance_on')" />
                    </div>
                    <div>
                        <x-input-label for="balance" value="Balance" />
                        <x-text-input id="balance" name="balance" type="number" step="0.01" class="mt-1 block"
                            :value="old('balance')" required />
                        <x-input-error class="mt-2" :messages="$errors->get('balance')" />
                    </div>
                    <div class="grow">
                        <x-input-label for="bal_notes" value="Notes" />
                        <x-text-input id="bal_notes" name="notes" type="text" class="mt-1 block w-full"
                            :value="old('notes')" />
                    </div>
                    <x-primary-button>Save</x-primary-button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
