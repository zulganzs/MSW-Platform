@props(['show' => false, 'title' => null, 'size' => 'md'])

@php
    $sizeClass = match($size) {
        'sm' => 'max-w-sm',
        'md' => 'max-w-md',
        'lg' => 'max-w-lg',
        'xl' => 'max-w-xl',
        default => 'max-w-md',
    };
@endphp

<div x-data="{ open: @js($show) }" 
     x-show="open" 
     @keydown.escape.window="open = false" 
     class="fixed inset-0 z-50 flex items-center justify-center p-4"
     data-testid="modal"
     role="dialog"
     aria-modal="true"
     style="display: none;">
    
    <!-- Overlay -->
    <div x-show="open" 
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-black/50 backdrop-blur-sm" 
         @click="open = false"></div>
         
    <!-- Modal Panel -->
    <div x-show="open"
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
         class="relative w-full {{ $sizeClass }} bg-white rounded-2xl shadow-xl overflow-hidden z-10">
         
         @if($title)
         <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
             <h3 class="text-lg font-semibold text-gray-900">{{ $title }}</h3>
             <button @click="open = false" aria-label="Tutup" class="text-gray-400 hover:text-gray-500 transition-colors">
                 <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                 </svg>
             </button>
         </div>
         @endif
         
         <div class="p-6">
             {{ $slot }}
         </div>
    </div>
</div>
