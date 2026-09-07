<x-layouts.citizen>
    <x-slot:bodyClass>h-screen overflow-hidden</x-slot:bodyClass>
    <div style="height: calc(100vh - 64px);" class="w-full relative rounded-xl overflow-hidden shadow-sm border border-gray-200 mt-2" x-data="citizenMap()">
        <div id="map" class="w-full h-full z-0"></div>
        
        <!-- Search Overlay -->
        <div class="absolute top-4 left-1/2 -translate-x-1/2 z-10 w-11/12 max-w-md">
            <input type="text" x-model="searchQuery" @input.debounce.150ms="filterMarkers" placeholder="Cari laporan..." class="w-full px-4 py-3 rounded-full shadow-md border-0 ring-1 ring-gray-200 focus:ring-2 focus:ring-blue-500 text-sm">
        </div>
        
        <!-- FAB -->
        <a href="{{ route('citizen.reports.create') }}" class="absolute bottom-6 right-6 z-10 w-14 h-14 bg-blue-600 text-white rounded-full flex items-center justify-center shadow-lg hover:bg-blue-700 transition-transform hover:scale-105 active:scale-95">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
        </a>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('citizenMap', () => ({
                reports: @json($reports),
                searchQuery: '',
                map: null,
                markers: [],
                
                init() {
                    if (typeof L === 'undefined') return;
                    
                    this.map = L.map('map').setView([-6.200000, 106.816666], 11);
                    
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
                    
                    this.markers.forEach(marker => this.map.removeLayer(marker));
                    this.markers = [];
                    
                    const q = this.searchQuery.toLowerCase();
                    const filtered = this.reports.filter(r => 
                        !q || 
                        (r.description && r.description.toLowerCase().includes(q)) || 
                        (r.category && r.category.name && r.category.name.toLowerCase().includes(q))
                    );
                    
                    const bounds = L.latLngBounds();
                    let hasValidPoints = false;
                    
                    filtered.forEach(report => {
                        if (report.latitude && report.longitude) {
                            hasValidPoints = true;
                            
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
                                        <span class="font-bold text-sm text-gray-900">${report.category.name}</span>
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full text-white ${pinColor}">${this.getStatusText(report.status)}</span>
                                    </div>
                                    <p class="text-xs text-gray-600 line-clamp-2 mb-3">${report.description}</p>
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
                    
                    if (hasValidPoints && this.searchQuery === '') {
                        this.map.fitBounds(bounds, { padding: [50, 50] });
                    }
                },
                
                filterMarkers() {
                    this.renderMarkers();
                }
            }));
        });
    </script>
    @endpush
</x-layouts.citizen>
