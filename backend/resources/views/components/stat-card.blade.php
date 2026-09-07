@props(['label' => '', 'value' => '', 'icon' => null, 'trend' => null])

<div data-testid="stat-card" class="bg-white border border-slate-200 rounded-md p-4 flex flex-col gap-2">
    <div class="flex items-center gap-2">
        @if($icon)
            <div class="text-slate-500 w-5 h-5 flex items-center justify-center">{!! $icon !!}</div>
        @endif
        <div class="text-xs text-slate-500">{{ $label }}</div>
    </div>
    <div class="text-2xl font-bold text-blue-700">{{ $value }}</div>
    @if($trend)
        @php
            $isPositive = $trend['value'] > 0;
            $trendColor = $isPositive ? 'text-green-600' : 'text-red-600';
            $trendIcon = $isPositive ? '↑' : '↓';
        @endphp
        <div class="text-xs {{ $trendColor }}">
            {{ $trendIcon }} {{ abs($trend['value']) }}% {{ $trend['label'] ?? '' }}
        </div>
    @endif
</div>
