@props(['value' => '', 'placeholder' => 'Cari...', 'name' => 'search'])

<div x-data="{ value: '{{ $value }}' }" data-testid="search-bar" class="relative flex items-center bg-white border border-slate-200 rounded-md h-11 px-3 focus-within:ring-2 focus-within:ring-blue-500 focus-within:border-blue-500">
    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-slate-400 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="11" cy="11" r="8"></circle>
        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
    </svg>
    <input 
        type="search" 
        name="{{ $name }}"
        x-model="value" 
        placeholder="{{ $placeholder }}"
        class="flex-1 w-full bg-transparent border-none focus:ring-0 px-2 text-sm text-slate-900 placeholder:text-slate-400"
    >
    <button 
        type="button" 
        x-show="value.length > 0" 
        @click="value = ''"
        class="shrink-0 w-5 h-5 flex items-center justify-center text-slate-400 hover:text-slate-600 focus:outline-none"
    >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
    </button>
</div>
