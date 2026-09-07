<x-layouts.citizen>
<div class="mb-6">
    <a href="{{ route('citizen.reports.index') }}" class="inline-flex items-center text-sm font-medium text-slate-500 hover:text-slate-700 mb-4 transition-colors">
        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
        Kembali
    </a>
    
    <div class="flex justify-between items-start gap-4">
        <div>
            <h2 class="text-2xl font-bold text-slate-900 mb-2">{{ $report->category->name }}</h2>
            <div class="flex items-center gap-2">
                <x-status-badge :status="$report->status" />
                <span class="inline-flex items-center gap-1 bg-slate-100 text-slate-600 text-xs font-semibold px-2.5 py-1 rounded-md">
                    @if($report->visibility === 'public')
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Publik
                    @elseif($report->visibility === 'private')
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                        Privat
                    @else
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg>
                        Anonim
                    @endif
                </span>
            </div>
        </div>
        
        @if($report->status === 'submitted' && $report->user_id === auth()->id())
            <x-confirm-dialog 
                action="{{ route('citizen.reports.destroy', $report) }}" 
                method="DELETE"
                title="Hapus Laporan?"
                message="Apakah Anda yakin ingin menghapus laporan ini? Tindakan ini tidak dapat dibatalkan.">
                <x-button variant="danger" icon="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                    Hapus
                </x-button>
            </x-confirm-dialog>
        @endif
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100">
            <h3 class="font-semibold text-slate-900 mb-3 text-lg">Deskripsi</h3>
            <p class="text-slate-700 leading-relaxed whitespace-pre-wrap">{{ $report->description }}</p>
            
            @if($report->user_id)
                <div class="mt-6 pt-6 border-t border-slate-100 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-500">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                    </div>
                    <div>
                        <div class="text-sm font-medium text-slate-900">{{ $report->user->name ?? 'Anonim' }}</div>
                        <div class="text-xs text-slate-500">Pelapor</div>
                    </div>
                </div>
            @endif
        </div>

        @if($report->attachments->count() > 0)
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100">
            <h3 class="font-semibold text-slate-900 mb-4 text-lg">Foto & Bukti</h3>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                @foreach($report->attachments->where('type', 'submission') as $attachment)
                    <x-attachment-preview :attachment="$attachment" />
                @endforeach
            </div>
            
            @if($report->attachments->where('type', 'closure')->count() > 0)
                <h4 class="font-medium text-slate-800 mt-6 mb-3 text-sm">Bukti Penyelesaian</h4>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                    @foreach($report->attachments->where('type', 'closure') as $attachment)
                        <x-attachment-preview :attachment="$attachment" />
                    @endforeach
                </div>
            @endif
        </div>
        @endif
        
        <x-timeline :report="$report" />
    </div>
    
    <div class="space-y-6">
        <div class="bg-white rounded-2xl p-4 shadow-sm border border-slate-100 h-64 overflow-hidden relative">
            <x-map-widget mode="view" :latitude="$report->latitude" :longitude="$report->longitude" />
        </div>
        
        @if($report->crews->count() > 0)
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100">
            <h3 class="font-semibold text-slate-900 mb-4 text-lg">Petugas Menangani</h3>
            <div class="space-y-4">
                @foreach($report->crews as $crew)
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-blue-50 flex items-center justify-center text-blue-600">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-slate-900">{{ $crew->name }}</div>
                            <div class="text-xs text-slate-500">Petugas Lapangan</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</div>
</x-layouts.citizen>