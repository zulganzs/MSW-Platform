@props(['latitude' => null, 'longitude' => null, 'markers' => [], 'mode' => 'view', 'height' => 300])

<div data-testid="map-widget" 
     style="height: {{ $height }}px;" 
     class="w-full rounded-lg border border-slate-200 overflow-hidden z-0"
     x-data="{
        initMap() {
            const map = L.map($el).setView([{{ $latitude ?? -6.2 }}, {{ $longitude ?? 106.8 }}], 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);

            @if($mode === 'pick')
                let currentMarker = null;
                @if($latitude && $longitude)
                    currentMarker = L.marker([{{ $latitude }}, {{ $longitude }}]).addTo(map);
                @endif
                
                map.on('click', e => {
                    if (currentMarker) {
                        currentMarker.setLatLng(e.latlng);
                    } else {
                        currentMarker = L.marker(e.latlng).addTo(map);
                    }
                    if (typeof $wire !== 'undefined') {
                        $wire.set('latitude', e.latlng.lat);
                        $wire.set('longitude', e.latlng.lng);
                    }
                    $dispatch('location-picked', { lat: e.latlng.lat, lng: e.latlng.lng });
                });
            @else
                @foreach($markers as $m)
                    L.marker([{{ $m['latitude'] }}, {{ $m['longitude'] }}]).addTo(map)
                    @if(isset($m['category_name']))
                        .bindPopup('{{ $m['category_name'] }}')
                    @endif
                    ;
                @endforeach
            @endif
        }
     }"
     x-init="if (typeof L !== 'undefined') initMap()"
>
</div>
