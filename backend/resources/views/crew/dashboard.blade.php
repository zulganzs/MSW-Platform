@extends('layouts.crew')

@section('content')
<div class="mb-6 flex justify-between items-center">
    <div class="flex items-center gap-3">
        <h1 class="text-2xl font-bold text-slate-900">Tugas Saya</h1>
        <span class="bg-blue-100 text-blue-700 text-sm font-semibold px-3 py-1 rounded-full">{{ $counts['assigned'] }} Aktif</span>
    </div>
    
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="text-slate-600 hover:text-slate-900">Keluar</button>
    </form>
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

<div class="flex flex-wrap gap-2 mb-6">
    <a href="{{ route('crew.dashboard') }}" class="px-4 py-2 rounded-full text-sm font-medium {{ !$currentStatus ? 'bg-slate-800 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
        Semua ({{ $counts['all'] }})
    </a>
    <a href="{{ route('crew.dashboard', ['status' => 'assigned']) }}" class="px-4 py-2 rounded-full text-sm font-medium {{ $currentStatus === 'assigned' ? 'bg-blue-600 text-white' : 'bg-blue-50 text-blue-700 hover:bg-blue-100' }}">
        Ditugaskan ({{ $counts['assigned'] }})
    </a>
    <a href="{{ route('crew.dashboard', ['status' => 'in_progress']) }}" class="px-4 py-2 rounded-full text-sm font-medium {{ $currentStatus === 'in_progress' ? 'bg-yellow-500 text-white' : 'bg-yellow-50 text-yellow-700 hover:bg-yellow-100' }}">
        Dikerjakan ({{ $counts['in_progress'] }})
    </a>
    <a href="{{ route('crew.dashboard', ['status' => 'completed']) }}" class="px-4 py-2 rounded-full text-sm font-medium {{ $currentStatus === 'completed' ? 'bg-green-600 text-white' : 'bg-green-50 text-green-700 hover:bg-green-100' }}">
        Selesai ({{ $counts['completed'] }})
    </a>
    
    <a href="{{ route('crew.dashboard', ['status' => $currentStatus]) }}" class="ml-auto px-4 py-2 rounded-full text-sm font-medium border border-slate-200 text-slate-700 hover:bg-slate-50 flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
        </svg>
        Perbarui
    </a>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    @forelse ($reports as $report)
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden flex flex-col">
            <div class="p-5 flex-1">
                <div class="flex justify-between items-start mb-3">
                    <span class="bg-slate-100 text-slate-700 text-xs font-semibold px-2 py-1 rounded">
                        {{ $report->category->name ?? 'Kategori' }}
                    </span>
                    
                    @if ($report->status === 'assigned')
                        <span class="bg-blue-50 text-blue-700 border border-blue-200 text-xs font-bold px-2 py-1 rounded-full">DITUGASKAN</span>
                    @elseif ($report->status === 'in_progress')
                        <span class="bg-yellow-50 text-yellow-700 border border-yellow-200 text-xs font-bold px-2 py-1 rounded-full">DIKERJAKAN</span>
                    @elseif ($report->status === 'completed')
                        <span class="bg-green-50 text-green-700 border border-green-200 text-xs font-bold px-2 py-1 rounded-full">SELESAI</span>
                    @endif
                </div>
                
                <h3 class="font-bold text-slate-900 mb-2 line-clamp-2">Laporan #{{ $report->id }}</h3>
                <p class="text-sm text-slate-600 mb-4 line-clamp-3">{{ $report->description }}</p>
                
                <div class="text-xs text-slate-500 mb-4">
                    Ditugaskan: {{ $report->created_at->format('d M Y H:i') }}
                </div>
            </div>
            
            <div class="p-4 bg-slate-50 border-t border-slate-100">
                @if ($report->status === 'assigned')
                    <form method="POST" action="{{ route('crew.reports.status', $report) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="in_progress">
                        <button type="submit" class="w-full bg-blue-600 text-white text-sm font-medium py-2 rounded-xl hover:bg-blue-700 transition-colors">
                            Mulai Pengerjaan
                        </button>
                    </form>
                @elseif ($report->status === 'in_progress')
                    <div class="grid grid-cols-2 gap-2">
                        <a href="{{ route('crew.reports.show', $report) }}" class="text-center bg-white border border-slate-200 text-slate-700 text-sm font-medium py-2 rounded-xl hover:bg-slate-50 transition-colors">
                            Lihat Detail
                        </a>
                        <a href="{{ route('crew.reports.show', $report) }}#upload" class="text-center bg-yellow-500 text-white text-sm font-medium py-2 rounded-xl hover:bg-yellow-600 transition-colors">
                            Upload Bukti
                        </a>
                    </div>
                @else
                    <a href="{{ route('crew.reports.show', $report) }}" class="block text-center bg-white border border-slate-200 text-slate-700 text-sm font-medium py-2 rounded-xl hover:bg-slate-50 transition-colors w-full">
                        Lihat Detail
                    </a>
                @endif
            </div>
        </div>
    @empty
        <div class="col-span-full bg-white rounded-2xl shadow-sm border border-slate-100 p-12 text-center">
            <svg class="w-16 h-16 text-slate-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
            <h3 class="text-lg font-bold text-slate-900 mb-1">Tidak Ada Tugas</h3>
            <p class="text-slate-500">Belum ada tugas yang {{ $currentStatus ? 'berstatus ' . $currentStatus : 'ditugaskan kepada Anda' }}.</p>
        </div>
    @endforelse
</div>

@if ($reports->hasPages())
    <div class="mt-6">
        {{ $reports->links() }}
    </div>
@endif
@endsection