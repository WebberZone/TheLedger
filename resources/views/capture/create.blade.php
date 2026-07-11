<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">Capture receipt</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-md mx-auto px-4 space-y-6">

            {{-- Tap one: choosing/taking the photo submits immediately. --}}
            <form id="capture-form" method="POST" action="{{ route('capture.store') }}" enctype="multipart/form-data">
                @csrf
                <label for="photo"
                    class="flex flex-col items-center justify-center gap-4 h-72 rounded-2xl border-4 border-dashed border-slate-300 bg-white cursor-pointer hover:border-indigo-400 hover:bg-indigo-50 transition">
                    <svg class="w-16 h-16 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z" />
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z" />
                    </svg>
                    <span class="text-lg font-semibold text-slate-700">Snap a receipt</span>
                    <span class="text-sm text-slate-500">Tap to open the camera</span>
                </label>
                <input id="photo" name="photo" type="file" accept="image/*,.pdf" capture="environment" class="sr-only"
                    onchange="document.getElementById('capture-form').submit()" />
                <x-input-error class="mt-2" :messages="$errors->get('photo')" />
            </form>

            <p class="text-center">
                <a href="{{ route('expenses.index') }}" class="text-sm text-slate-600 hover:text-slate-900 underline">
                    View all expenses
                </a>
            </p>
        </div>
    </div>
</x-app-layout>