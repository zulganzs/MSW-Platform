@props(['align' => 'right'])

@php
    $alignmentClasses = match ($align) {
        'left' => 'left-0 origin-top-left',
        'right' => 'right-0 origin-top-right',
        default => 'right-0 origin-top-right',
    };
@endphp

<div class="relative" x-data="{ open: false }" @click.away="open = false" @close.stop="open = false">
    <div @click="open = ! open">
        {{ $trigger }}
    </div>

    <div x-show="open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-75"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="absolute z-50 mt-2 rounded-md shadow-lg {{ $alignmentClasses }}"
         style="display: none;"
         @click="open = false">
        <div class="rounded-md ring-1 ring-black ring-opacity-5 py-1 bg-white">
            {{ $content ?? $slot }}
        </div>
    </div>
</div>
