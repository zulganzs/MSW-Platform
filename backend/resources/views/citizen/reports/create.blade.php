<x-layouts.citizen>
<div class="max-w-3xl mx-auto py-8 px-4" x-data="reportForm()">
    <h1 class="text-2xl font-bold mb-6 text-gray-900">Buat Laporan Baru</h1>

    <form method="POST" action="{{ route('citizen.reports.store') }}" enctype="multipart/form-data" @submit="submitting = true" class="space-y-6 bg-white p-6 rounded-lg shadow-sm border border-gray-200">
        @csrf

        <!-- Kategori -->
        <div>
            <label for="category_id" class="block text-sm font-medium text-gray-700">Kategori</label>
            <select name="category_id" id="category_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">Pilih Kategori...</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>
                        {{ $category->name }}
                    </option>
                @endforeach
            </select>
            @error('category_id')
                <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <!-- Deskripsi -->
        <div>
            <label for="description" class="block text-sm font-medium text-gray-700">Deskripsi Laporan</label>
            <div class="mt-1 relative">
                <textarea 
                    name="description" 
                    id="description" 
                    rows="4" 
                    required 
                    minlength="10"
                    maxlength="500"
                    x-model="description"
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                    placeholder="Jelaskan masalah yang Anda temui secara detail..."
                ></textarea>
                <div class="absolute bottom-2 right-2 text-xs text-gray-500">
                    <span x-text="description.length"></span>/500 karakter
                </div>
            </div>
            @error('description')
                <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <!-- Lokasi Peta (Leaflet) -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Lokasi Kejadian</label>
            <p class="text-xs text-gray-500 mb-2">Klik pada peta untuk menentukan titik lokasi kejadian.</p>
            
            <div id="map" class="h-64 w-full rounded-md border border-gray-300 mb-2 z-0"></div>
            
            <input type="hidden" name="latitude" id="latitude" x-model="lat" required>
            <input type="hidden" name="longitude" id="longitude" x-model="lng" required>
            
            <div class="flex gap-4 text-sm text-gray-600" x-show="lat && lng">
                <p>Lat: <span x-text="lat" class="font-mono"></span></p>
                <p>Lng: <span x-text="lng" class="font-mono"></span></p>
            </div>
            
            @error('latitude')
                <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <!-- Foto / Lampiran -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Foto / Bukti Kejadian</label>
            
            <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md hover:bg-gray-50 cursor-pointer" @click="$refs.fileInput.click()">
                <div class="space-y-1 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                        <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    <div class="flex text-sm text-gray-600 justify-center">
                        <span class="relative rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500">
                            Unggah foto
                        </span>
                        <p class="pl-1">atau seret dan lepas ke sini</p>
                    </div>
                    <p class="text-xs text-gray-500">PNG, JPG up to 5MB</p>
                </div>
            </div>
            
            <input 
                type="file" 
                name="photos[]" 
                multiple 
                accept="image/png, image/jpeg" 
                class="hidden" 
                x-ref="fileInput" 
                @change="handleFileSelect"
            >

            <!-- Image Previews -->
            <div class="mt-4 grid grid-cols-3 sm:grid-cols-4 gap-4" x-show="imagePreviews.length > 0">
                <template x-for="(preview, index) in imagePreviews" :key="index">
                    <div class="relative group aspect-square rounded-md overflow-hidden bg-gray-100 border border-gray-200">
                        <img :src="preview" class="object-cover w-full h-full" alt="Preview">
                        <button type="button" aria-label="Hapus foto" @click.stop="removeImage(index)" class="absolute top-1 right-1 bg-red-500 text-white rounded-full p-1 opacity-0 group-hover:opacity-100 transition-opacity">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                </template>
            </div>
            @error('photos.*')
                <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <!-- Visibilitas -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-3">Visibilitas Laporan</label>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <!-- Public -->
                <label class="relative flex cursor-pointer rounded-lg border bg-white p-4 shadow-sm focus:outline-none" :class="visibility === 'public' ? 'border-blue-500 ring-1 ring-blue-500' : 'border-gray-300'">
                    <input type="radio" name="visibility" value="public" class="sr-only" x-model="visibility">
                    <div class="flex flex-col">
                        <span class="block text-sm font-medium text-gray-900 flex items-center gap-2">
                            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            Publik
                        </span>
                        <span class="mt-1 flex items-center text-xs text-gray-500">Semua orang bisa melihat</span>
                    </div>
                </label>
                <!-- Private -->
                <label class="relative flex cursor-pointer rounded-lg border bg-white p-4 shadow-sm focus:outline-none" :class="visibility === 'private' ? 'border-blue-500 ring-1 ring-blue-500' : 'border-gray-300'">
                    <input type="radio" name="visibility" value="private" class="sr-only" x-model="visibility">
                    <div class="flex flex-col">
                        <span class="block text-sm font-medium text-gray-900 flex items-center gap-2">
                            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                            Privat
                        </span>
                        <span class="mt-1 flex items-center text-xs text-gray-500">Hanya Anda & Petugas</span>
                    </div>
                </label>
                <!-- Anonymous -->
                <label class="relative flex cursor-pointer rounded-lg border bg-white p-4 shadow-sm focus:outline-none" :class="visibility === 'anonymous' ? 'border-blue-500 ring-1 ring-blue-500' : 'border-gray-300'">
                    <input type="radio" name="visibility" value="anonymous" class="sr-only" x-model="visibility">
                    <div class="flex flex-col">
                        <span class="block text-sm font-medium text-gray-900 flex items-center gap-2">
                            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            Anonim
                        </span>
                        <span class="mt-1 flex items-center text-xs text-gray-500">Publik, identitas disembunyikan</span>
                    </div>
                </label>
            </div>
            @error('visibility')
                <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <div class="pt-4 border-t border-gray-200 flex justify-end">
            <button 
                type="submit" 
                class="inline-flex justify-center rounded-md border border-transparent bg-blue-600 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                :disabled="submitting"
                :class="{'opacity-75 cursor-not-allowed': submitting}"
            >
                <span x-show="!submitting">Kirim Laporan</span>
                <span x-show="submitting" class="flex items-center gap-2">
                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    Mengirim...
                </span>
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('reportForm', () => ({
            description: '{{ old("description", "") }}',
            visibility: '{{ old("visibility", "public") }}',
            lat: '{{ old("latitude", "-6.2088") }}', // Default Jakarta
            lng: '{{ old("longitude", "106.8456") }}',
            submitting: false,
            files: [],
            imagePreviews: [],
            
            init() {
                // Initialize map
                setTimeout(() => {
                    const map = L.map('map').setView([this.lat, this.lng], 13);
                    
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                    }).addTo(map);

                    let marker = L.marker([this.lat, this.lng], {draggable: true}).addTo(map);
                    
                    // Update alpine data when marker dragged
                    marker.on('dragend', (e) => {
                        const position = marker.getLatLng();
                        this.lat = position.lat.toFixed(6);
                        this.lng = position.lng.toFixed(6);
                    });

                    // Move marker on map click
                    map.on('click', (e) => {
                        marker.setLatLng(e.latlng);
                        this.lat = e.latlng.lat.toFixed(6);
                        this.lng = e.latlng.lng.toFixed(6);
                    });
                }, 100);
            },
            
            handleFileSelect(event) {
                const selectedFiles = Array.from(event.target.files);
                
                // Check sizes and limits here if needed (e.g. 5MB)
                const validFiles = selectedFiles.filter(file => file.size <= 5 * 1024 * 1024);
                
                if (validFiles.length !== selectedFiles.length) {
                    alert('Beberapa file diabaikan karena ukurannya melebihi 5MB.');
                }
                
                this.files = [...this.files, ...validFiles];
                
                // Clear existing previews to rebuild (simplified for this example)
                this.imagePreviews = [];
                
                this.files.forEach(file => {
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        this.imagePreviews.push(e.target.result);
                    };
                    reader.readAsDataURL(file);
                });
                
                // Update the actual input element's files property to match our state
                // This requires a DataTransfer object to reconstruct the FileList
                this.syncFileInput();
            },
            
            removeImage(index) {
                this.files.splice(index, 1);
                this.imagePreviews.splice(index, 1);
                this.syncFileInput();
            },
            
            syncFileInput() {
                const dataTransfer = new DataTransfer();
                this.files.forEach(file => {
                    dataTransfer.items.add(file);
                });
                this.$refs.fileInput.files = dataTransfer.files;
            }
        }));
    });
</script>
@endpush
</x-layouts.citizen>
