<x-app-layout>
	<x-slot name="header">
		<h2 class="font-semibold text-xl text-slate-800 leading-tight">Ledger</h2>
	</x-slot>

	<div class="py-12">
		<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

			<div class="bg-white border border-slate-200 shadow-sm rounded-xl p-4">
				<form method="GET" action="{{ route('ledger.index') }}" class="flex flex-wrap items-end gap-4">
					<div>
						<x-input-label for="payment_method_id" value="Payment method / account" />
						<select id="payment_method_id" name="payment_method_id"
							class="mt-1 block border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm">
							<option value="">Choose…</option>
							@foreach ($paymentMethods as $method)
								<option value="{{ $method->id }}" @selected($methodId == $method->id)>{{ $method->name }}
								</option>
							@endforeach
						</select>
					</div>
					<div>
						<x-input-label for="currency" value="Currency" />
						<select id="currency" name="currency"
							class="mt-1 block border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm">
							@foreach (settings('currencies') as $code)
								<option value="{{ $code }}" @selected($currency === $code)>{{ $code }}</option>
							@endforeach
						</select>
					</div>
					<x-primary-button>View</x-primary-button>
				</form>
			</div>

			@if ($methodId)
				<div class="bg-white border border-slate-200 shadow-sm rounded-xl overflow-x-auto">
					<table class="min-w-full divide-y divide-slate-200 text-sm">
						<thead class="bg-slate-50 text-xs font-medium text-slate-500 uppercase tracking-wider">
							<tr>
								<th class="px-4 py-3 text-left">Date</th>
								<th class="px-4 py-3 text-left">Description</th>
								<th class="px-4 py-3 text-left">Type</th>
								<th class="px-4 py-3 text-right">Amount ({{ $currency }})</th>
								<th class="px-4 py-3 text-right">Balance ({{ $currency }})</th>
							</tr>
						</thead>
						<tbody class="divide-y divide-slate-100">
							@forelse ($entries as $entry)
								@php $t = $entry['transaction']; @endphp
								<tr>
									<td class="px-4 py-3 whitespace-nowrap">{{ $t->occurred_on->format('d M Y') }}</td>
									<td class="px-4 py-3">
										<span class="font-medium text-slate-900">{{ $t->displayName() }}</span>
										@if ($t->description)
											<span class="text-slate-500">— {{ $t->counterparty }}</span>
										@endif
									</td>
									<td class="px-4 py-3 text-slate-500">{{ $t->type->pluralLabel() }}</td>
									<td
										class="px-4 py-3 text-right whitespace-nowrap {{ $entry['signed_minor'] < 0 ? 'text-red-600' : 'text-green-700' }}">
										{{ number_format($entry['signed_minor'] / 100, 2) }}
									</td>
									<td
										class="px-4 py-3 text-right whitespace-nowrap font-medium {{ $entry['balance_minor'] < 0 ? 'text-red-600' : '' }}">
										{{ number_format($entry['balance_minor'] / 100, 2) }}
									</td>
								</tr>
							@empty
								<tr>
									<td colspan="5" class="px-4 py-8 text-center text-slate-500">
										No {{ $currency }} transactions on this payment method.
									</td>
								</tr>
							@endforelse
						</tbody>
						@if ($entries->isNotEmpty())
							<tfoot class="bg-slate-50 font-semibold">
								<tr>
									<td colspan="4" class="px-4 py-3 text-right">Closing balance:</td>
									<td
										class="px-4 py-3 text-right whitespace-nowrap {{ $closingMinor < 0 ? 'text-red-600' : '' }}">
										{{ number_format($closingMinor / 100, 2) }}
									</td>
								</tr>
							</tfoot>
						@endif
					</table>
				</div>
			@endif
		</div>
	</div>
</x-app-layout>