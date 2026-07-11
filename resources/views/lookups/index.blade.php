<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">Lookups</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

                {{-- Categories --}}
                <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-4">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold text-slate-800">Categories</h3>
                        <a href="{{ route('lookups.create', 'categories') }}"
                            class="inline-flex items-center px-3 py-1.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-500">
                            Add
                        </a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                                <tr>
                                    <th class="py-2">Name</th>
                                    <th class="py-2">Ledger</th>
                                    <th class="py-2 text-right"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($categories as $category)
                                    <tr>
                                        <td class="py-2">
                                            {{ $category->name }}
                                            @if (! $category->is_active)
                                                <span class="ml-2 text-xs text-slate-400">(inactive)</span>
                                            @endif
                                        </td>
                                        <td class="py-2 text-slate-600">{{ $category->type }}</td>
                                        <td class="py-2 text-right">
                                            <a href="{{ route('lookups.edit', ['categories', $category]) }}"
                                                class="text-indigo-600 hover:text-indigo-900">Edit</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="py-4 text-slate-500 text-sm">No categories yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Payment methods --}}
                <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-4">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold text-slate-800">Payment methods</h3>
                        <a href="{{ route('lookups.create', 'payment-methods') }}"
                            class="inline-flex items-center px-3 py-1.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-500">
                            Add
                        </a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                                <tr>
                                    <th class="py-2">Name</th>
                                    <th class="py-2 text-right"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($paymentMethods as $method)
                                    <tr>
                                        <td class="py-2">
                                            {{ $method->name }}
                                            @if ($method->is_personal)
                                                <span class="ml-2 text-xs text-slate-500">Personal</span>
                                            @endif
                                            @if (! $method->is_active)
                                                <span class="ml-2 text-xs text-slate-400">(inactive)</span>
                                            @endif
                                        </td>
                                        <td class="py-2 text-right">
                                            <a href="{{ route('lookups.edit', ['payment-methods', $method]) }}"
                                                class="text-indigo-600 hover:text-indigo-900">Edit</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="2" class="py-4 text-slate-500 text-sm">No payment methods yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Business units --}}
                <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-4">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold text-slate-800">Business units</h3>
                        <a href="{{ route('lookups.create', 'business-units') }}"
                            class="inline-flex items-center px-3 py-1.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-500">
                            Add
                        </a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                                <tr>
                                    <th class="py-2">Name</th>
                                    <th class="py-2 text-right"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($businessUnits as $unit)
                                    <tr>
                                        <td class="py-2">
                                            {{ $unit->name }}
                                            @if (! $unit->is_active)
                                                <span class="ml-2 text-xs text-slate-400">(inactive)</span>
                                            @endif
                                        </td>
                                        <td class="py-2 text-right">
                                            <a href="{{ route('lookups.edit', ['business-units', $unit]) }}"
                                                class="text-indigo-600 hover:text-indigo-900">Edit</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="2" class="py-4 text-slate-500 text-sm">No business units yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>
</x-app-layout>