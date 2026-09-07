<x-layouts.citizen>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900">Dashboard Saya</h1>
    </div>

    @if (session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl mb-6">
            {{ session('success') }}
        </div>
    @endif

    <!-- Map with all report locations -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden mb-8" x-data="dashboardMap()">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-900">Peta Laporan</h2>
            <span class="text-xs text-slate-500">{{ $mapReports->count() }} laporan</span>
        </div>
        <div id="dashboard-map" style="height: 400px;" class="w-full z-0"></div>
    </div>

    <!-- Quick Actions -->
    <div class="flex flex-wrap gap-4 mb-8">
        <a href="{{ route('citizen.reports.create') }}" class="inline-flex items-center gap-2 px-5 py-3 text-sm font-medium text-white bg-blue-600 rounded-xl hover:bg-blue-700 transition-colors shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Buat Laporan
        </a>
        <a href="{{ route('citizen.map') }}" class="inline-flex items-center gap-2 px-5 py-3 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-1.447-.894L15 9m0 8V9m0 0L9 7"></path></svg>
            Lihat Peta
        </a>
        <a href="{{ route('citizen.reports.index') }}" class="inline-flex items-center gap-2 px-5 py-3 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
            Semua Laporan
        </a>
    </div>

    <!-- Recent Reports -->
    <div class="mb-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-slate-900">Laporan Terbaru</h2>
            @if($recentReports->isNotEmpty())
            <a href="{{ route('citizen.reports.index') }}" class="text-sm text-blue-600 hover:text-blue-700 font-medium">Lihat semua</a>
            @endif
        </div>

        <div class="space-y-4">
            @forelse($recentReports as $report)
                <x-report-card :report="$report" />
            @empty
                <div class="bg-white rounded-xl border border-slate-100 p-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    <p class="mt-4 text-sm text-slate-500">Belum ada laporan. Buat laporan pertama Anda!</p>
                </div>
            @endforelse
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('dashboardMap', () => ({
                reports: @json($mapReports),
                map: null,
                markers: [],

                init() {
                    if (typeof L === 'undefined') return;

                    this.map = L.map('dashboard-map').setView([-6.200000, 106.816666], 11);

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; OpenStreetMap contributors'
                    }).addTo(this.map);

                    this.renderMarkers();
                },

                getStatusColor(status) {
                    const colors = {
                        submitted: 'bg-yellow-500',
                        assigned: 'bg-blue-500',
                        in_progress: 'bg-indigo-500',
                        completed: 'bg-green-500',
                        rejected: 'bg-red-500'
                    };
                    return colors[status] || 'bg-gray-500';
                },

                getStatusText(status) {
                    const texts = {
                        submitted: 'Menunggu',
                        assigned: 'Ditugaskan',
                        in_progress: 'Proses',
                        completed: 'Selesai',
                        rejected: 'Ditolak'
                    };
                    return texts[status] || status;
                },

                renderMarkers() {
                    if (!this.map) return;
                    const bounds = L.latLngBounds();
                    let hasValid = false;

                    this.reports.forEach(report => {
                        if (report.latitude && report.longitude) {
                            hasValid = true;
                            const pinColor = this.getStatusColor(report.status);

                            const icon = L.divIcon({
                                className: 'custom-div-icon',
                                html: `<div class="w-4 h-4 rounded-full ${pinColor} border-2 border-white shadow-sm shadow-black/30"></div>`,
                                iconSize: [16, 16],
                                iconAnchor: [8, 8]
                            });

                            const popupHtml = `
                                <div class="p-1 min-w-[200px]">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="font-bold text-sm text-gray-900">${report.category?.name ?? 'Tanpa Kategori'}</span>
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full text-white ${pinColor}">${this.getStatusText(report.status)}</span>
                                    </div>
                                    <p class="text-xs text-gray-600 line-clamp-2 mb-3">${report.description ?? ''}</p>
                                    <a href="/citizen/reports/${report.id}" class="block text-center text-xs font-medium text-blue-600 hover:text-blue-700 bg-blue-50 py-1.5 rounded">Lihat Detail</a>
                                </div>
                            `;

                            const marker = L.marker([report.latitude, report.longitude], { icon })
                                .bindPopup(popupHtml)
                                .addTo(this.map);

                            bounds.extend([report.latitude, report.longitude]);
                            this.markers.push(marker);
                        }
                    });

                    if (hasValid) {
                        this.map.fitBounds(bounds, { padding: [50, 50] });
                    }
                }
            }));
        });
    </script>
    @endpush
</x-layouts.citizen>
