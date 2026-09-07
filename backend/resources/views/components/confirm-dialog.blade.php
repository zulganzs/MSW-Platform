@props([
    'title' => '',
    'message' => '',
    'confirmLabel' => 'Konfirmasi',
    'cancelLabel' => 'Batal',
    'variant' => 'default',
    'action' => '',
    'method' => 'POST',
])

<div x-data="{ open: false }" {{ $attributes }}>
    {{-- Trigger: wrapper click opens the dialog --}}
    <div @click="open = true" class="inline-flex">
        {{ $slot }}
    </div>

    {{-- Dialog --}}
    <div x-show="open" x-cloak style="display: none;"
         @keydown.escape.window="open = false"
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         role="dialog"
         aria-modal="true">

        <!-- Overlay -->
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" @click="open = false"></div>

        <!-- Panel -->
        <div class="relative w-full max-w-sm bg-white rounded-2xl shadow-xl overflow-hidden z-10">
            <div class="p-6">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full sm:mx-0 sm:h-10 sm:w-10 {{ $variant === 'danger' ? 'bg-red-100' : 'bg-blue-100' }}">
                        <svg class="h-6 w-6 {{ $variant === 'danger' ? 'text-red-600' : 'text-blue-600' }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                        <h3 class="text-base font-semibold text-slate-900">{{ $title }}</h3>
                        <p class="mt-2 text-sm text-slate-500">{{ $message }}</p>
                    </div>
                </div>
                <form action="{{ $action }}" method="POST" class="mt-5 sm:flex sm:flex-row-reverse">
                    @method($method)
                    @csrf
                    <button type="submit"
                            class="inline-flex w-full justify-center rounded-md px-3 py-2 text-sm font-semibold text-white shadow-sm sm:ml-3 sm:w-auto {{ $variant === 'danger' ? 'bg-red-600 hover:bg-red-500' : 'bg-blue-600 hover:bg-blue-500' }}">
                        {{ $confirmLabel }}
                    </button>
                    <button type="button" @click="open = false"
                            class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50 sm:mt-0 sm:w-auto">
                        {{ $cancelLabel }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
