@php
    $isEdit = $model->exists;
@endphp

<form method="POST" action="{{ $isEdit ? route('lookups.update', [$type, $model]) : route('lookups.store', $type) }}">
    @csrf
    @if ($isEdit)
        @method('PATCH')
    @endif

    <div class="space-y-6">
        <div>
            <x-input-label for="name" value="Name" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                :value="old('name', $model->name)" required autofocus />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        @if ($type === 'categories')
            <div>
                <x-input-label for="type" value="Ledger" />
                <select id="type" name="type"
                    class="mt-1 block w-full border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm">
                    @foreach (\App\Enums\TransactionType::cases() as $ledger)
                        <option value="{{ $ledger->value }}" @selected(old('type', $model->type ?? 'expense') === $ledger->value)>
                            {{ $ledger->pluralLabel() }}
                        </option>
                    @endforeach
                </select>
                <x-input-error class="mt-2" :messages="$errors->get('type')" />
            </div>

            <div>
                <x-input-label for="report_group" value="Report group" />
                <x-text-input id="report_group" name="report_group" type="text" class="mt-1 block w-full"
                    :value="old('report_group', $model->report_group)" />
                <p class="mt-1 text-sm text-slate-500">Accountant-facing rollup, e.g. "Advertising and PR".</p>
                <x-input-error class="mt-2" :messages="$errors->get('report_group')" />
            </div>

            <div>
                <x-input-label for="account_code" value="Account code" />
                <x-text-input id="account_code" name="account_code" type="text" class="mt-1 block w-full"
                    :value="old('account_code', $model->account_code)" />
                <p class="mt-1 text-sm text-slate-500">Nominal code for CSV/XLSX export, e.g. "7500".</p>
                <x-input-error class="mt-2" :messages="$errors->get('account_code')" />
            </div>

            <div>
                <x-input-label for="description" value="Description" />
                <x-text-input id="description" name="description" type="text" class="mt-1 block w-full"
                    :value="old('description', $model->description)" />
                <x-input-error class="mt-2" :messages="$errors->get('description')" />
            </div>

            <div>
                <x-input-label for="sort_order" value="Sort order" />
                <x-text-input id="sort_order" name="sort_order" type="number" min="0" step="1"
                    class="mt-1 block w-full" :value="old('sort_order', $model->sort_order ?? 0)" required />
                <x-input-error class="mt-2" :messages="$errors->get('sort_order')" />
            </div>
        @endif

        @if ($type === 'payment-methods')
            <div class="flex items-center gap-3">
                <input id="is_personal" name="is_personal" type="checkbox" value="1"
                    @checked(old('is_personal', $model->is_personal ?? false))
                    class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                <x-input-label for="is_personal" value="Personal card (needs reimbursing)" />
                <x-input-error class="mt-2" :messages="$errors->get('is_personal')" />
            </div>
        @endif

        <div class="flex items-center gap-3">
            <input id="is_active" name="is_active" type="checkbox" value="1"
                @checked(old('is_active', $model->is_active ?? true))
                class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
            <x-input-label for="is_active" value="Active" />
            <x-input-error class="mt-2" :messages="$errors->get('is_active')" />
        </div>
    </div>

    <div class="mt-6 flex items-center gap-4">
        <x-primary-button>{{ $isEdit ? 'Update' : 'Save' }}</x-primary-button>
        <a href="{{ route('lookups.index') }}" class="text-sm text-slate-600 hover:text-slate-900">Cancel</a>
    </div>
</form>
