@props([
    'label' => null,
    'type' => 'text',
    'name',
    'value' => null,
    'placeholder' => null,
    'error' => null,
    'multiline' => false,
    'disabled' => false
])

@php
    $baseClasses = 'w-full rounded-xl border border-slate-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 px-4 transition-all shadow-sm';
    $inputClasses = $baseClasses . ' ' . ($multiline ? 'py-3 min-h-[100px]' : 'h-12');
    $inputClasses .= $disabled ? ' bg-slate-50 text-slate-500 cursor-not-allowed' : ' bg-white text-slate-900';
    $inputClasses .= ($error || $errors->has($name)) ? ' border-red-300 focus:border-red-500 focus:ring-red-500/20' : '';
@endphp

<div class="space-y-1.5">
    @if($label)
        <label for="{{ $name }}" class="block text-sm font-medium text-slate-700">
            {{ $label }}
        </label>
    @endif

    @if($multiline)
        <textarea
            id="{{ $name }}"
            name="{{ $name }}"
            placeholder="{{ $placeholder }}"
            @if($disabled) disabled @endif
            {{ $attributes->merge(['class' => $inputClasses]) }}
        >{{ old($name, $value) }}</textarea>
    @else
        <input
            id="{{ $name }}"
            name="{{ $name }}"
            type="{{ $type }}"
            value="{{ old($name, $value) }}"
            placeholder="{{ $placeholder }}"
            @if($disabled) disabled @endif
            {{ $attributes->merge(['class' => $inputClasses]) }}
        />
    @endif

    @if($error || $errors->has($name))
        <p class="text-sm text-red-500" role="alert">
            {{ $error ?? $errors->first($name) }}
        </p>
    @endif
</div>
