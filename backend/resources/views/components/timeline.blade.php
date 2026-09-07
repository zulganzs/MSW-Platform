@props(['items' => []])

<div data-testid="timeline" class="relative pl-6 border-l border-slate-200 space-y-6">
    @foreach($items as $item)
        <div class="relative">
            <div class="absolute -left-[30px] top-1 w-3 h-3 rounded-full {{ $item['color'] ?? 'bg-slate-300' }}"></div>
            <div class="text-sm font-medium text-slate-900">{{ $item['label'] ?? '' }}</div>
            <div class="text-xs text-slate-500">{{ $item['date'] ?? '' }}</div>
            @if(isset($item['actor']))
                <div class="text-xs text-slate-500">oleh: {{ $item['actor'] }}</div>
            @endif
        </div>
    @endforeach
</div>
