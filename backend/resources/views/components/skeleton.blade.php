@props([
    'type' => 'card',
    'count' => 1
])

@for ($i = 0; $i < $count; $i++)
    @if($type === 'card')
        <div {{ $attributes->merge(['class' => 'bg-white rounded-2xl p-5 border border-slate-100 shadow-sm min-h-[200px] w-full']) }}>
            <div class="flex justify-between items-start gap-4 mb-3">
                <div class="h-5 bg-slate-100 rounded-md w-1/3 animate-pulse"></div>
                <div class="h-6 bg-slate-100 rounded-full w-20 animate-pulse"></div>
            </div>
            <div class="space-y-2 mb-6">
                <div class="h-4 bg-slate-100 rounded w-full animate-pulse"></div>
                <div class="h-4 bg-slate-100 rounded w-4/5 animate-pulse"></div>
            </div>
            <div class="flex gap-4">
                <div class="h-4 bg-slate-100 rounded w-24 animate-pulse"></div>
                <div class="h-4 bg-slate-100 rounded w-32 animate-pulse"></div>
            </div>
        </div>
    @elseif($type === 'list')
        <div {{ $attributes->merge(['class' => 'bg-white rounded-xl p-4 border border-slate-100 shadow-sm flex items-center justify-between h-[80px] w-full']) }}>
            <div class="flex items-center gap-4">
                <div class="w-10 h-10 rounded-full bg-slate-100 animate-pulse shrink-0"></div>
                <div class="space-y-2">
                    <div class="h-4 bg-slate-100 rounded w-32 animate-pulse"></div>
                    <div class="h-3 bg-slate-100 rounded w-24 animate-pulse"></div>
                </div>
            </div>
            <div class="h-8 bg-slate-100 rounded-lg w-20 animate-pulse"></div>
        </div>
    @elseif($type === 'form')
        <div {{ $attributes->merge(['class' => 'space-y-1.5 w-full']) }}>
            <div class="h-4 bg-slate-100 rounded w-24 animate-pulse mb-1"></div>
            <div class="h-12 bg-slate-100 rounded-xl w-full animate-pulse border border-slate-50"></div>
        </div>
    @elseif($type === 'text')
        <div {{ $attributes->merge(['class' => 'space-y-2.5 w-full']) }}>
            <div class="h-4 bg-slate-100 rounded w-full animate-pulse"></div>
            <div class="h-4 bg-slate-100 rounded w-11/12 animate-pulse"></div>
            <div class="h-4 bg-slate-100 rounded w-4/5 animate-pulse"></div>
        </div>
    @endif
@endfor
