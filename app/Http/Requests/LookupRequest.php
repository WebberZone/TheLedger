<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for the three lookup forms. The rules change based on the
 * {type} route parameter so one request class covers categories, payment
 * methods, and business units.
 */
class LookupRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Single-user app behind the auth middleware.
        return true;
    }

    public function rules(): array
    {
        return match ($this->route('type')) {
            'categories' => $this->categoryRules(),
            'payment-methods' => $this->paymentMethodRules(),
            'business-units' => $this->businessUnitRules(),
            default => abort(404),
        };
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'is_personal' => $this->boolean('is_personal'),
        ]);
    }

    /**
     * Model-ready attributes, scoped to the lookup type being edited.
     *
     * @return array<string, mixed>
     */
    public function lookupData(string $type): array
    {
        $validated = $this->validated();

        return match ($type) {
            'categories' => [
                'name' => $validated['name'],
                'type' => $validated['type'],
                'report_group' => $validated['report_group'] ?? null,
                'account_code' => $validated['account_code'] ?? null,
                'description' => $validated['description'] ?? null,
                'is_active' => $validated['is_active'],
                'sort_order' => $validated['sort_order'],
            ],
            'payment-methods' => [
                'name' => $validated['name'],
                'is_personal' => $validated['is_personal'],
                'is_active' => $validated['is_active'],
            ],
            'business-units' => [
                'name' => $validated['name'],
                'is_active' => $validated['is_active'],
            ],
            default => abort(404),
        };
    }

    /**
     * @return array<string, array<int, string|Rule>>
     */
    private function categoryRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['expense', 'income', 'cash_flow'])],
            'report_group' => ['nullable', 'string', 'max:255'],
            'account_code' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, array<int, string|Rule>>
     */
    private function paymentMethodRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'is_personal' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, array<int, string|Rule>>
     */
    private function businessUnitRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }
}
