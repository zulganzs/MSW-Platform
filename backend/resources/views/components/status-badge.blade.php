@props(['status' => 'submitted'])

@php
    $config = [
        'submitted' => [
            'label' => 'Dikirim',
            'bg' => 'bg-slate-100',
            'text' => 'text-slate-600',
            'dot' => 'bg-slate-400'
        ],
        'assigned' => [
            'label' => 'Ditugaskan',
            'bg' => 'bg-blue-50',
            'text' => 'text-blue-600',
            'dot' => 'bg-blue-500'
        ],
        'in_progress' => [
            'label' => 'Dikerjakan',
            'bg' => 'bg-yellow-50',
            'text' => 'text-yellow-600',
            'dot' => 'bg-yellow-500'
        ],
        'completed' => [
            'label' => 'Selesai',
            'bg' => 'bg-green-50',
            'text' => 'text-green-600',
            'dot' => 'bg-green-500'
        ],
    ];

    $current = $config[$status] ?? $config['submitted'];
@endphp

<span data-testid="status-badge" {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold {$current['bg']} {$current['text']} border border-transparent shadow-sm"]) }}>
    <span class="w-1.5 h-1.5 rounded-full {{ $current['dot'] }}"></span>
    {{ $current['label'] }}
</span>
