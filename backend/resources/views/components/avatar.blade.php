@props(['name' => '', 'size' => 'md', 'src' => null])

@php
    $sizeClasses = [
        'sm' => 'w-8 h-8 text-[12px]',
        'md' => 'w-10 h-10 text-[14px]',
        'lg' => 'w-14 h-14 text-[18px]',
    ][$size] ?? 'w-10 h-10 text-[14px]';

    $initials = '';
    if ($name) {
        $parts = explode(' ', trim($name));
        $initials = strtoupper(substr($parts[0], 0, 1));
        if (count($parts) > 1) {
            $initials .= strtoupper(substr(end($parts), 0, 1));
        }
    }
@endphp

<div data-testid="avatar" class="{{ $sizeClasses }} rounded-full flex items-center justify-center shrink-0 overflow-hidden bg-blue-100 text-blue-700 font-semibold">
    @if($src)
        <img src="{{ $src }}" alt="{{ $name }}" class="w-full h-full object-cover">
    @else
        {{ $initials }}
    @endif
</div>
