<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">Edit: {{ $transaction->displayName() }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-6">
                <form method="POST" action="{{ route("$routePrefix.update", $transaction) }}">
                    @csrf
                    @method('PATCH')
                    @include('transactions.partials.form')

                    <div class="mt-6 flex items-center gap-4">
                        <x-primary-button>Update</x-primary-button>
                        <a href="{{ route("$routePrefix.index") }}"
                            class="text-sm text-slate-600 hover:text-slate-900">Cancel</a>
                    </div>
                </form>

                <form method="POST" action="{{ route("$routePrefix.destroy", $transaction) }}" class="mt-6 pt-6 border-t border-slate-100"
                    onsubmit="return confirm('Delete this entry? This is permanent and cannot be undone.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-sm font-medium text-red-600 hover:text-red-900">Delete this entry</button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
