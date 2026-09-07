@props([
    'icon' => 'search',
    'title' => 'Tidak ada data',
    'subtitle' => 'Data yang Anda cari tidak ditemukan atau belum ada.',
    'actionLabel' => null,
    'actionUrl' => null
])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center py-12 px-4 text-center bg-white rounded-2xl border border-slate-100 border-dashed']) }}>
    <div class="w-16 h-16 mb-4 rounded-full bg-slate-50 flex items-center justify-center text-slate-400 shadow-sm border border-slate-100">
        @if($icon === 'search')
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
        @elseif($icon === 'inbox')
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
        @elseif($icon === 'document')
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
        @else
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        @endif
    </div>

    <h3 class="text-lg font-bold text-slate-900 mb-2">{{ $title }}</h3>
    
    @if($subtitle)
        <p class="text-sm text-slate-500 max-w-xs mx-auto leading-relaxed">{{ $subtitle }}</p>
    @endif

    @if($actionLabel && $actionUrl)
        <div class="mt-6">
            <x-button variant="secondary" size="md" href="{{ $actionUrl }}">
                {{ $actionLabel }}
            </x-button>
        </div>
    @endif
</div>
