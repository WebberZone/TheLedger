<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">Reading receipt</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-md mx-auto px-4 space-y-4">

            <img src="{{ route('capture.photo', $token) }}" alt="Receipt"
                class="w-full max-h-64 object-contain rounded-lg border border-slate-200 bg-white" />

            <div id="ocr-status"
                class="flex items-center gap-2 text-sm text-slate-600 bg-white border border-slate-200 rounded-md p-3">
                <svg class="animate-spin h-4 w-4 text-indigo-600" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                Reading the receipt&hellip;
            </div>

            <p class="text-center">
                <a href="{{ route('expenses.create', ['capture_token' => $token]) }}"
                    class="text-sm text-slate-600 hover:text-slate-900 underline">
                    Continue without waiting
                </a>
            </p>
        </div>
    </div>

    <script>
        (function () {
            const statusUrl = @json(route('capture.status', $token));
            const nextUrl = @json(route('expenses.create', ['capture_token' => $token]));
            let attempts = 0;

            const timer = setInterval(async () => {
                attempts++;
                if (attempts > 40) { // give up after ~60s
                    clearInterval(timer);
                    window.location.href = nextUrl;
                    return;
                }

                try {
                    const res = await fetch(statusUrl, { headers: { Accept: 'application/json' } });
                    const body = await res.json();

                    if (body.status === 'ready' || body.status === 'failed' || body.status === 'expired') {
                        clearInterval(timer);
                        window.location.href = nextUrl;
                    }
                } catch (e) {
                    // transient network error; keep polling
                }
            }, 1500);
        })();
    </script>
</x-app-layout>
