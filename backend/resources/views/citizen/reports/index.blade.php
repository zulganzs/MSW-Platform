<x-layouts.citizen>
<div class="mb-6 flex items-center justify-between">
    <h1 class="text-2xl font-bold text-slate-900">Laporan Saya</h1>
    <a href="{{ route('citizen.reports.create') }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors shadow-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
        Buat Laporan
    </a>
</div>

<form action="{{ route('citizen.reports.index') }}" method="GET" class="mb-6 flex gap-4 flex-wrap">
    <select name="status" class="rounded-xl border border-slate-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-500 h-10 px-4 text-sm" onchange="this.form.submit()">
        <option value="">Semua Status</option>
        <option value="submitted" {{ request('status') === 'submitted' ? 'selected' : '' }}>Dikirim</option>
        <option value="assigned" {{ request('status') === 'assigned' ? 'selected' : '' }}>Ditugaskan</option>
        <option value="in_progress" {{ request('status') === 'in_progress' ? 'selected' : '' }}>Dikerjakan</option>
        <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Selesai</option>
    </select>
    
    <select name="category_id" class="rounded-xl border border-slate-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-500 h-10 px-4 text-sm" onchange="this.form.submit()">
        <option value="">Semua Kategori</option>
        @foreach($categories as $category)
            <option value="{{ $category->id }}" {{ request('category_id') == $category->id ? 'selected' : '' }}>
                {{ $category->name }}
            </option>
        @endforeach
    </select>
</form>

<div class="space-y-4 mb-6">
    @forelse($reports as $report)
        <x-report-card :report="$report" />
    @empty
        <x-empty-state icon="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" title="Belum ada laporan" description="Anda belum membuat laporan atau tidak ada laporan yang sesuai dengan filter." />
    @endforelse
</div>

<div>
    {{ $reports->links() }}
</div>
</x-layouts.citizen>