{{-- Shared by create and edit for all three ledgers. $transaction is a
new or existing model; old() wins over model values so failed
validation keeps user input. --}}
<div x-data="{
        currency: '{{ old( 'currency', $transaction->currency ?? config( 'docket.base_currency' ) ) }}',
        amount: '{{ old( 'amount', $transaction->exists ? $transaction->amount_major : '' ) }}',
        vat: '{{ old( 'vat', $transaction->exists && $transaction->vat_minor ? $transaction->vat_major : '' ) }}',
        fxRate: '{{ old( 'fx_rate', $transaction->exists && $transaction->currency !== config( 'docket.base_currency' ) ? rtrim( rtrim( (string) $transaction->fx_rate, '0' ), '.' ) : '' ) }}',
        amountGbp: '{{ old( 'amount_gbp' ) }}',
        currencySymbols: {{ json_encode( config( 'docket.currency_symbols' ) ) }},
        get total() { return (parseFloat(this.amount) || 0) + (parseFloat(this.vat) || 0); },
        get currencySymbol() { return this.currencySymbols[this.currency] || '£'; },
        get gbpPreview() {
            if (this.amountGbp) return parseFloat(this.amountGbp) || 0;
            if (this.fxRate) return this.total * (parseFloat(this.fxRate) || 0);
            return this.total;
        },
    }"
    class="space-y-6">

    <input type="hidden" name="capture_token" value="{{ old( 'capture_token' ) }}" />

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
        <div>
            <x-input-label for="occurred_on" value="Date" />
            <x-text-input id="occurred_on" name="occurred_on" type="date" class="mt-1 block w-full"
                :value="old( 'occurred_on', $transaction->occurred_on?->toDateString() )" required />
            <x-input-error class="mt-2" :messages="$errors->get( 'occurred_on' )" />
        </div>

        <div>
            <x-input-label for="counterparty" :value="$type->counterpartyLabel()" />
            <x-text-input id="counterparty" name="counterparty" type="text" class="mt-1 block w-full"
                :value="old( 'counterparty', $transaction->counterparty )" required autofocus />
            <x-input-error class="mt-2" :messages="$errors->get( 'counterparty' )" />
        </div>
    </div>

    <div>
        <x-input-label for="description" value="Description" />
        <x-text-input id="description" name="description" type="text" class="mt-1 block w-full"
            :value="old( 'description', $transaction->description )" />
        <x-input-error class="mt-2" :messages="$errors->get( 'description' )" />
    </div>

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
        <div>
            <x-input-label for="amount" value="Amount (net)" />
            <x-text-input id="amount" name="amount" type="number" step="0.01" min="0.01" inputmode="decimal"
                x-model="amount" class="mt-1 block w-full" required />
            <p class="mt-1 text-sm text-slate-500">Before VAT — Total (Amount + VAT) is shown below.</p>
            <x-input-error class="mt-2" :messages="$errors->get( 'amount' )" />
        </div>

        <div>
            <x-input-label for="vat" value="VAT" />
            <x-text-input id="vat" name="vat" type="number" step="0.01" min="0" inputmode="decimal"
                x-model="vat" class="mt-1 block w-full" />
            <x-input-error class="mt-2" :messages="$errors->get( 'vat' )" />
        </div>

        <div>
            <x-input-label for="currency" value="Currency" />
            <select id="currency" name="currency" x-model="currency"
                class="mt-1 block w-full border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm">
                @foreach ( settings( 'currencies' ) as $code )
					<option value="{{ $code }}" @selected( old( 'currency', $transaction->currency ?? config( 'docket.base_currency' ) ) === $code )>
						{{ $code }}</option>
				@endforeach
            </select>
            <x-input-error class="mt-2" :messages="$errors->get( 'currency' )" />
        </div>
    </div>

    {{-- Live Net+VAT=Total, mirroring the tracker spreadsheet's Total
    column formula (=Amount+VAT). Read-only — the real total is always
    computed server-side from amount/vat in TransactionRequest. --}}
    <div class="flex items-center justify-between text-sm bg-slate-50 rounded-md p-3">
        <span class="text-slate-600">Total (Amount + VAT)</span>
        <span class="font-medium text-slate-900" x-text="currencySymbol + total.toFixed(2)"></span>
    </div>

    {{-- Non-GBP: enter the actual GBP charged (statement figure) OR a
    rate — whichever you have; the other is derived on save. --}}
    <div x-show="currency !== '{{ config( 'docket.base_currency' ) }}'" x-cloak class="grid grid-cols-1 gap-6 sm:grid-cols-2 bg-slate-50 rounded-md p-4">
        <div>
            <x-input-label for="amount_gbp" value="Actual GBP charged (from statement)" />
            <x-text-input id="amount_gbp" name="amount_gbp" type="number" step="0.01" min="0.01" inputmode="decimal"
                x-model="amountGbp" class="mt-1 block w-full" />
            <x-input-error class="mt-2" :messages="$errors->get( 'amount_gbp' )" />
        </div>
        <div>
            <x-input-label for="fx_rate" value="…or FX rate (GBP per 1 unit)" />
            <x-text-input id="fx_rate" name="fx_rate" type="number" step="0.000001" min="0" inputmode="decimal"
                x-model="fxRate" class="mt-1 block w-full" />
            <x-input-error class="mt-2" :messages="$errors->get( 'fx_rate' )" />
        </div>
        <div class="sm:col-span-2 flex items-center justify-between text-sm">
            <span class="text-slate-600">≈ Amount (GBP)</span>
            <span class="font-medium text-slate-900" x-text="'£' + gbpPreview.toFixed(2)"></span>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
        <div>
            <x-input-label for="category_id" value="Category" />
            <select id="category_id" name="category_id" required
                class="mt-1 block w-full border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm">
                <option value="">—</option>
                @foreach ( $categories as $category )
					<option value="{{ $category->id }}" @selected( old( 'category_id', $transaction->category_id ) == $category->id )>{{ $category->name }}</option>
				@endforeach
            </select>
            <x-input-error class="mt-2" :messages="$errors->get( 'category_id' )" />
        </div>

        <div>
            <x-input-label for="business_unit_id" value="Business unit" />
            <select id="business_unit_id" name="business_unit_id" required
                class="mt-1 block w-full border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm">
                <option value="">—</option>
                @foreach ( $businessUnits as $unit )
					<option value="{{ $unit->id }}" @selected( old( 'business_unit_id', $transaction->business_unit_id ) == $unit->id )>{{ $unit->name }}</option>
				@endforeach
            </select>
            <x-input-error class="mt-2" :messages="$errors->get( 'business_unit_id' )" />
        </div>

        <div>
            <x-input-label for="payment_method_id" value="Payment method" />
            <select id="payment_method_id" name="payment_method_id" required
                class="mt-1 block w-full border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm">
                <option value="">—</option>
                @foreach ( $paymentMethods as $method )
					<option value="{{ $method->id }}" @selected( old( 'payment_method_id', $transaction->payment_method_id ) == $method->id )>{{ $method->name }}</option>
				@endforeach
            </select>
            <x-input-error class="mt-2" :messages="$errors->get( 'payment_method_id' )" />
        </div>
    </div>

    @if ( $type === \App\Enums\TransactionType::Expense )
		<div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
			<label for="is_billable" class="inline-flex items-center mt-6">
				<input id="is_billable" name="is_billable" type="checkbox" value="1"
					class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
					@checked( old( 'is_billable', $transaction->is_billable ) ) />
				<span class="ms-2 text-sm text-slate-600">Billable (rechargeable to a client)</span>
			</label>

			<div>
				<x-input-label for="billed_ref" value="Billed ref (invoice no. once recharged)" />
				<x-text-input id="billed_ref" name="billed_ref" type="text" class="mt-1 block w-full"
					:value="old( 'billed_ref', $transaction->billed_ref )" />
				<x-input-error class="mt-2" :messages="$errors->get( 'billed_ref' )" />
			</div>
		</div>
	@endif

    <div>
        <x-input-label for="notes" value="Notes" />
        <textarea id="notes" name="notes" rows="3"
            class="mt-1 block w-full border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm">{{ old( 'notes', $transaction->notes ) }}</textarea>
        <x-input-error class="mt-2" :messages="$errors->get( 'notes' )" />
    </div>
</div>