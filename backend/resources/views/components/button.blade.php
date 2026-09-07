@props([
    'variant' => 'primary',
    'size' => 'md',
    'type' => 'button',
    'disabled' => false
])

@php
    $baseClasses = 'inline-flex items-center justify-center font-medium rounded-xl transition-all focus:outline-none focus:ring-2 focus:ring-offset-2';
    
    $variants = [
        'primary' => 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white hover:from-blue-700 hover:to-indigo-700 focus:ring-blue-500 shadow-sm border border-transparent',
        'secondary' => 'bg-slate-100 text-slate-700 hover:bg-slate-200 focus:ring-slate-500 border border-transparent',
        'danger' => 'bg-red-50 text-red-600 hover:bg-red-100 focus:ring-red-500 border border-red-200',
        'ghost' => 'text-slate-600 hover:text-slate-900 hover:bg-slate-50 focus:ring-slate-500 border border-transparent',
    ];
    
    $sizes = [
        'sm' => 'h-9 px-4 text-sm',
        'md' => 'h-12 px-6 text-base', // lms-sma-fe standard: h-12
        'lg' => 'h-14 px-8 text-lg',
    ];

    $classes = $baseClasses . ' ' . 
        ($disabled ? 'bg-slate-100 text-slate-400 cursor-not-allowed border border-transparent' : $variants[$variant] ?? $variants['primary']) . ' ' . 
        ($sizes[$size] ?? $sizes['md']);
@endphp

<button {{ $attributes->merge(['type' => $type, 'class' => $classes]) }} @if($disabled) disabled @endif>
    {{ $slot }}
</button>
