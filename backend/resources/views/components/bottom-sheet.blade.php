@props(['show' => false, 'title' => null])

<div x-data="{ open: @js($show) }"
     x-show="open"
     class="md:hidden fixed inset-0 z-50 pointer-events-none"
     data-testid="bottom-sheet"
     style="display: none;">
     
    <!-- Overlay -->
    <div x-show="open"
         x-transition:enter="transition-opacity ease-linear duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-linear duration-300"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-black/50 pointer-events-auto"
         @click="open = false"></div>

    <!-- Sheet -->
    <div class="fixed bottom-0 left-0 right-0 w-full bg-white rounded-t-2xl shadow-xl transform transition-transform pointer-events-auto flex flex-col max-h-[90vh]"
         x-show="open"
         x-transition:enter="transition ease-out duration-300 transform"
         x-transition:enter-start="translate-y-full"
         x-transition:enter-end="translate-y-0"
         x-transition:leave="transition ease-in duration-200 transform"
         x-transition:leave-start="translate-y-0"
         x-transition:leave-end="translate-y-full">
        
        <!-- Drag Handle / Header -->
        <div class="flex flex-col items-center pt-3 pb-2 border-b border-gray-100 flex-shrink-0"
             @click="open = false">
            <div class="w-10 h-1 bg-gray-300 rounded-full mb-3"></div>
            @if($title)
                <h3 class="text-lg font-semibold text-gray-900 w-full px-4 text-center">{{ $title }}</h3>
            @endif
        </div>

        <!-- Content -->
        <div class="p-4 overflow-y-auto">
            {{ $slot }}
        </div>
    </div>
</div>
