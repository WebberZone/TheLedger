<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">{{ $transaction->displayName() }}</h2>
            <div class="flex items-center gap-3">
                <a href="{{ route("$routePrefix.edit", $transaction) }}"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                    Edit
                </a>
                <form method="POST" action="{{ route("$routePrefix.destroy", $transaction) }}"
                    onsubmit="return confirm('Delete this entry? This is permanent and cannot be undone.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-white border border-red-300 rounded-lg text-sm font-semibold text-red-600 shadow-sm hover:bg-red-50">
                        Delete
                    </button>
                </form>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-6">
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-sm">
                    <div>
                        <dt class="font-medium text-slate-500">Date</dt>
                        <dd class="mt-1 text-slate-900">{{ $transaction->occurred_on->format('d M Y') }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-slate-500">Amount</dt>
                        <dd class="mt-1 text-slate-900">
                            £{{ number_format($transaction->amount_gbp_minor / 100, 2) }}
                            @if ($transaction->currency !== config('docket.base_currency'))
                                <span class="text-slate-500">({{ $transaction->currency }} {{ $transaction->total_major }} @
                                    {{ rtrim(rtrim((string) $transaction->fx_rate, '0'), '.') }})</span>
                            @endif
                            @if ($transaction->vat_minor > 0)
                                <span class="text-slate-500">— net {{ $transaction->currency }}
                                    {{ $transaction->amount_major }} + VAT {{ $transaction->vat_major }}</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="font-medium text-slate-500">Category</dt>
                        <dd class="mt-1 text-slate-900">
                            {{ $transaction->category?->name ?? '—' }}
                            @if ($transaction->category?->report_group && $transaction->category->report_group !== $transaction->category->name)
                                <span class="text-slate-500">({{ $transaction->category->report_group }})</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="font-medium text-slate-500">Business unit</dt>
                        <dd class="mt-1 text-slate-900">{{ $transaction->businessUnit?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-slate-500">Payment method</dt>
                        <dd class="mt-1 text-slate-900">{{ $transaction->paymentMethod?->name ?? '—' }}</dd>
                    </div>
                    @if ($type === \App\Enums\TransactionType::Expense)
                        <div>
                            <dt class="font-medium text-slate-500">Billable</dt>
                            <dd class="mt-1 text-slate-900">
                                {{ $transaction->is_billable ? 'Yes' : 'No' }}
                                @if ($transaction->billed_ref)
                                    <span class="text-slate-500">(ref: {{ $transaction->billed_ref }})</span>
                                @endif
                            </dd>
                        </div>
                    @endif
                    @if ($transaction->description)
                        <div class="sm:col-span-2">
                            <dt class="font-medium text-slate-500">Description</dt>
                            <dd class="mt-1 text-slate-900">{{ $transaction->description }}</dd>
                        </div>
                    @endif
                    @if ($transaction->notes)
                        <div class="sm:col-span-2">
                            <dt class="font-medium text-slate-500">Notes</dt>
                            <dd class="mt-1 text-slate-900 whitespace-pre-line">{{ $transaction->notes }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            @if ($type === \App\Enums\TransactionType::Expense)
                <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-6">
                    <h3 class="font-medium text-slate-900 mb-4">Receipts</h3>

                    @if ($transaction->receipts->isEmpty())
                        <p class="text-sm text-slate-500 mb-4">No receipts attached yet.</p>
                    @else
                        <ul class="space-y-2 text-sm mb-4">
                            @foreach ($transaction->receipts as $receipt)
                                <li>
                                    <a href="{{ route('receipts.show', $receipt) }}" target="_blank"
                                        class="text-indigo-600 hover:text-indigo-900 font-medium">
                                        {{ $receipt->original_filename ?? basename($receipt->path) }}
                                    </a>
                                    <span class="text-slate-500">({{ $receipt->content_type }},
                                        {{ number_format(($receipt->byte_size ?? 0) / 1024) }} KB)</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <form method="POST" action="{{ route('receipts.store', $transaction) }}" enctype="multipart/form-data"
                        class="flex flex-wrap items-center gap-3">
                        @csrf
                        <input type="file" name="receipts[]" multiple accept="image/*,.pdf" capture="environment"
                            class="text-sm text-slate-600 file:me-3 file:px-3 file:py-2 file:rounded-md file:border-0 file:bg-indigo-600 file:text-white file:text-xs file:font-semibold hover:file:bg-indigo-500" />
                        <x-primary-button>Upload</x-primary-button>
                    </form>
                    <x-input-error class="mt-2" :messages="$errors->get('receipts')" />
                    <x-input-error class="mt-2" :messages="$errors->get('receipts.0')" />
                </div>
            @endif
        </div>
    </div>
</x-app-layout>