<x-layouts.crew>
<div class="mb-6">
    <a href="{{ route('crew.dashboard') }}" class="inline-flex items-center text-sm font-medium text-slate-500 hover:text-slate-700 mb-4 transition-colors">
        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
        Kembali ke Dashboard
    </a>
    
    <div class="flex justify-between items-start gap-4">
        <div>
            <h2 class="text-2xl font-bold text-slate-900 mb-2">{{ $report->category->name }}</h2>
            <div class="flex items-center gap-2">
                <x-status-badge :status="$report->status" />
            </div>
        </div>
    </div>
</div>

@if (session('success'))
    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl mb-6">
        {{ session('success') }}
    </div>
@endif

@if (session('error'))
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6" role="alert">
        {{ session('error') }}
    </div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100">
            <h3 class="font-semibold text-slate-900 mb-3 text-lg">Deskripsi</h3>
            <p class="text-slate-700 leading-relaxed whitespace-pre-wrap">{{ $report->description }}</p>
        </div>

        @if($report->attachments->where('type', 'submission')->count() > 0)
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100">
            <h3 class="font-semibold text-slate-900 mb-4 text-lg">Foto Laporan Awal</h3>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                @foreach($report->attachments->where('type', 'submission') as $attachment)
                    <x-attachment-preview :attachment="$attachment" />
                @endforeach
            </div>
        </div>
        @endif

        @if($report->status === 'completed' && $report->attachments->where('type', 'closure')->count() > 0)
            <div class="bg-green-50 rounded-2xl p-6 shadow-sm border border-green-100">
                <h3 class="font-semibold text-green-900 mb-4 text-lg">Bukti Penyelesaian</h3>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                    @foreach($report->attachments->where('type', 'closure') as $attachment)
                        <x-attachment-preview :attachment="$attachment" />
                    @endforeach
                </div>
            </div>
        @endif
        
        <x-timeline :report="$report" />
    </div>
    
    <div class="space-y-6">
        <!-- Action Zone -->
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100" id="upload">
            <h3 class="font-semibold text-slate-900 mb-4 text-lg">Aksi Tugas</h3>
            
            @if ($report->status === 'assigned')
                <p class="text-sm text-slate-600 mb-4">Anda telah ditugaskan untuk menangani laporan ini. Silakan mulai pengerjaan ketika Anda siap menuju lokasi atau melakukan perbaikan.</p>
                <form method="POST" action="{{ route('crew.reports.status', $report) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="in_progress">
                    <button type="submit" class="w-full bg-blue-600 text-white font-medium py-3 rounded-xl hover:bg-blue-700 transition-colors shadow-sm">
                        Mulai Pengerjaan
                    </button>
                </form>
            @elseif ($report->status === 'in_progress')
                <div x-data="{ hasFile: false, previewUrl: null }">
                    <h4 class="font-medium text-slate-800 mb-2">Upload Bukti Penyelesaian</h4>
                    <p class="text-sm text-slate-600 mb-4">Unggah foto hasil perbaikan/penyelesaian untuk menutup laporan ini.</p>
                    
                    <form method="POST" action="{{ route('crew.reports.closure', $report) }}" enctype="multipart/form-data" class="space-y-4">
                        @csrf
                        
                        <div class="relative border-2 border-dashed border-slate-300 rounded-xl p-4 text-center hover:bg-slate-50 transition-colors" :class="{'border-blue-500 bg-blue-50': previewUrl}">
                            <input type="file" name="file" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" 
                                @change="
                                    hasFile = $event.target.files.length > 0;
                                    if(hasFile) {
                                        previewUrl = URL.createObjectURL($event.target.files[0]);
                                    } else {
                                        previewUrl = null;
                                    }
                                ">
                            
                            <div x-show="!previewUrl" class="space-y-2">
                                <svg class="w-8 h-8 text-slate-400 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                                <div class="text-sm text-slate-600 font-medium">Klik untuk memilih foto</div>
                                <div class="text-xs text-slate-500">Maks. 5MB (JPG, PNG)</div>
                            </div>
                            
                            <div x-show="previewUrl" style="display: none;">
                                <img :src="previewUrl" class="max-h-40 mx-auto rounded-lg object-contain">
                                <div class="text-sm text-blue-600 font-medium mt-2">Foto dipilih - siap diunggah</div>
                            </div>
                        </div>
                        @error('file')
                            <p class="text-red-500 text-sm">{{ $message }}</p>
                        @enderror
                        
                        <input type="hidden" name="status" value="completed">
                        
                        <button type="submit" class="w-full bg-green-600 text-white font-medium py-3 rounded-xl hover:bg-green-700 transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2" :disabled="!hasFile">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            Tandai Selesai
                        </button>
                    </form>
                </div>
            @elseif ($report->status === 'completed')
                <div class="flex items-center gap-3 text-green-700 bg-green-50 p-4 rounded-xl border border-green-100">
                    <svg class="w-6 h-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <div>
                        <div class="font-semibold">Tugas Selesai</div>
                        <div class="text-sm text-green-600">Terima kasih atas kerja keras Anda.</div>
                    </div>
                </div>
            @endif
        </div>

        <div class="bg-white rounded-2xl p-4 shadow-sm border border-slate-100 h-64 overflow-hidden relative">
            <x-map-widget mode="view" :latitude="$report->latitude" :longitude="$report->longitude" />
        </div>
    </div>
</div>
</x-layouts.crew>