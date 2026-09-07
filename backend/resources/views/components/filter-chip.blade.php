@props(['label' => '', 'active' => false, 'count' => null, 'href' => null])

@php
    $baseClasses = 'inline-flex items-center rounded-full px-3 py-1 text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2';
    $activeClasses = $active ? 'bg-blue-500 text-white hover:bg-blue-600' : 'bg-slate-100 text-slate-500 hover:bg-slate-200';
    $tag = $href ? 'a' : 'button';
@endphp

<{{ $tag }} 
    data-testid="filter-chip"
    {{ $href ? 'href='.$href : 'type=button' }}
    {{ $attributes->merge(['class' => $baseClasses . ' ' . $activeClasses]) }}
>
    {{ $label }}
    @if($count !== null && $count > 0)
        <span class="ml-1.5 flex h-4 w-4 items-center justify-center rounded-full text-[10px] font-bold {{ $active ? 'bg-white text-blue-600' : 'bg-slate-200 text-slate-600' }}">
            {{ $count }}
        </span>
    @endif
</{{ $tag }}>
