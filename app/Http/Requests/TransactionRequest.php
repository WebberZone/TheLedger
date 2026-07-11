<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared by store and update for all three ledgers (expense, income,
 * cash flow) — the rules are identical.
 */
class TransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Single-user app behind the auth middleware; no per-record ACL.
        return true;
    }

    public function rules(): array
    {
        return [
            'occurred_on' => ['required', 'date'],
            'counterparty' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:255'],
            // Major units from the form, e.g. "12.99"; converted to pence below.
            'amount' => ['required', 'numeric', 'gt:0', 'max:'.settings('max_amount')],
            'vat' => ['nullable', 'numeric', 'gte:0', 'max:'.settings('max_amount')],
            'currency' => ['required', 'string', 'size:3'],
            // For non-GBP, give EITHER the FX rate OR the actual GBP charged
            // (from the card statement) — the other is derived.
            'fx_rate' => [
                'nullable', 'numeric', 'gt:0',
                Rule::requiredIf(fn () => $this->input('currency') !== config('docket.base_currency') && ! $this->filled('amount_gbp')),
            ],
            'amount_gbp' => ['nullable', 'numeric', 'gt:0', 'max:'.settings('max_amount')],
            'category_id' => ['required', 'exists:categories,id'],
            'business_unit_id' => ['required', 'exists:business_units,id'],
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'is_billable' => ['boolean'],
            'billed_ref' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            // Round-tripped from the capture flow's hidden field; not part
            // of transactionData() — the controller uses it separately to
            // attach the pending receipt after the transaction is created.
            'capture_token' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'fx_rate.required' => 'Give either an FX rate or the actual GBP amount charged.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(
            [
                'currency' => strtoupper((string) $this->input('currency', config('docket.base_currency'))),
                // Unchecked checkboxes are absent from the request entirely.
                'is_billable' => $this->boolean('is_billable'),
            ]
        );
    }

    /**
     * Model-ready attributes: money converted to integer minor units here,
     * once, so floats never reach storage. Gross = net + VAT; the GBP
     * figure comes from the rate, or the rate from the GBP figure —
     * exactly how the tracker spreadsheet's FX column worked.
     */
    public function transactionData(): array
    {
        $validated = $this->validated();

        $amountMinor = (int) round((float) $validated['amount'] * 100);
        $vatMinor = (int) round((float) ($validated['vat'] ?? 0) * 100);
        $grossMinor = $amountMinor + $vatMinor;

        if ($validated['currency'] === config('docket.base_currency')) {
            $fxRate = 1.0;
            $gbpMinor = $grossMinor;
        } elseif (! empty($validated['amount_gbp'])) {
            // Actual GBP charged is known; derive the rate.
            $gbpMinor = (int) round((float) $validated['amount_gbp'] * 100);
            $fxRate = round($gbpMinor / $grossMinor, 6);
        } else {
            $fxRate = (float) $validated['fx_rate'];
            $gbpMinor = (int) round($grossMinor * $fxRate);
        }

        return [
            'occurred_on' => $validated['occurred_on'],
            'counterparty' => $validated['counterparty'],
            'description' => $validated['description'] ?? null,
            'amount_minor' => $amountMinor,
            'vat_minor' => $vatMinor,
            'currency' => $validated['currency'],
            'fx_rate' => $fxRate,
            'amount_gbp_minor' => $gbpMinor,
            'category_id' => $validated['category_id'] ?? null,
            'business_unit_id' => $validated['business_unit_id'] ?? null,
            'payment_method_id' => $validated['payment_method_id'] ?? null,
            'is_billable' => $validated['is_billable'] ?? false,
            'billed_ref' => $validated['billed_ref'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ];
    }
}
