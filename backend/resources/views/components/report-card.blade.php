@props(['report' => null])

@if($report)
<a href="{{ route('citizen.reports.show', $report) }}" data-testid="report-card" class="block bg-white rounded-2xl p-5 shadow-sm border border-slate-100 hover:shadow-md hover:border-slate-200 transition-all group">
    <div class="flex justify-between items-start gap-4 mb-3">
        <h3 class="font-semibold text-slate-900 group-hover:text-blue-600 transition-colors line-clamp-1">
            {{ $report->category->name ?? 'Laporan' }}
        </h3>
        <x-status-badge :status="$report->status" />
    </div>
    
    <p class="text-sm text-slate-600 line-clamp-2 mb-4">
        {{ $report->description }}
    </p>
    
    <div class="flex items-center gap-3 text-xs text-slate-500">
        <span class="flex items-center gap-1">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
            {{ $report->created_at ? $report->created_at->format('d M Y') : '-' }}
        </span>
        @if($report->latitude && $report->longitude)
        <span class="flex items-center gap-1 truncate">
            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
            {{ $report->latitude }}, {{ $report->longitude }}
        </span>
        @endif
    </div>
</a>
@endif
