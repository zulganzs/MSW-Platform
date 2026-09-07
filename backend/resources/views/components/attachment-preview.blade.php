@props(['attachment' => null])

@if($attachment)
<div x-data="{ open: false }" data-testid="attachment-preview">
    <div class="relative w-[120px] h-[120px] rounded-lg overflow-hidden border border-slate-200 cursor-pointer group" @click="open = true">
        <img src="{{ $attachment['url'] ?? '' }}" alt="Attachment" class="w-full h-full object-cover transition-transform group-hover:scale-105">
        @if(isset($attachment['type']))
            <div class="absolute top-0 left-0 right-0 bg-black/40 text-white text-xs px-2 py-1">
                {{ $attachment['type'] === 'submission' ? 'Foto Laporan' : 'Bukti Selesai' }}
            </div>
        @endif
        <div class="absolute inset-0 bg-black/0 group-hover:bg-black/10 transition-colors"></div>
    </div>

    <!-- Lightbox Modal -->
    <template x-teleport="body">
        <div x-show="open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4" x-transition.opacity @keydown.escape.window="open = false">
            <div class="relative max-w-4xl max-h-full" @click.away="open = false">
                <button @click="open = false" class="absolute -top-10 right-0 text-white hover:text-slate-300 focus:outline-none">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
                <img src="{{ $attachment['url'] ?? '' }}" alt="Attachment Preview" class="max-w-full max-h-[85vh] object-contain rounded">
            </div>
        </div>
    </template>
</div>
@endif
