<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">Add — {{ $type->pluralLabel() }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            @if (old('capture_token') && (old('counterparty') || old('amount')))
                <div class="text-sm text-indigo-800 bg-indigo-50 border border-indigo-200 rounded-md p-3">
                    Prefilled from the captured receipt — check the details and save.
                </div>
            @elseif (old('capture_token'))
                <div class="text-sm text-slate-600 bg-slate-50 border border-slate-200 rounded-md p-3">
                    Couldn't read the receipt automatically — the photo will still be attached when you save.
                </div>
            @endif
            <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-6">
                <form method="POST" action="{{ route("$routePrefix.store") }}">
                    @csrf
                    @include('transactions.partials.form')

                    <div class="mt-6 flex items-center gap-4">
                        <x-primary-button>Save</x-primary-button>
                        <a href="{{ route("$routePrefix.index") }}" class="text-sm text-slate-600 hover:text-slate-900">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
