<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">Edit {{ strtolower($label) }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-6">
                @include('lookups.partials.form')
            </div>

            <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-6">
                <h3 class="text-sm font-semibold text-slate-700">Delete this {{ strtolower($label) }}</h3>
                <p class="mt-1 text-sm text-slate-600">
                    You can only delete lookup items that are not used by any transaction.
                </p>
                <form method="POST" action="{{ route('lookups.destroy', [$type, $model]) }}"
                    class="mt-4"
                    onsubmit="return confirm('Delete this {{ strtolower($label) }}? This cannot be undone.');">
                    @csrf
                    @method('DELETE')
                    <x-danger-button>Delete</x-danger-button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
