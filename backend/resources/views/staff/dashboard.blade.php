@extends('layouts.staff')

@section('title', 'Dashboard Staff')

@section('content')
<div class="space-y-6" x-data="{ assignmentModalOpen: false, selectedReport: null }">
    <!-- Stat Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="text-sm font-medium text-gray-500">Total Laporan</div>
            <div class="mt-2 text-3xl font-bold text-gray-900">{{ $stats['total'] }}</div>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <div class="text-sm font-medium text-blue-500">Menunggu (Submitted)</div>
            <div class="mt-2 text-3xl font-bold text-blue-600">{{ $stats['submitted'] }}</div>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <div class="text-sm font-medium text-amber-500">Diproses (In Progress)</div>
            <div class="mt-2 text-3xl font-bold text-amber-600">{{ $stats['in_progress'] }}</div>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <div class="text-sm font-medium text-green-500">Selesai (Completed)</div>
            <div class="mt-2 text-3xl font-bold text-green-600">{{ $stats['completed'] }}</div>
        </div>
    </div>

    <!-- Duplicates Warning -->
    @if($duplicates->isNotEmpty())
    <div class="bg-amber-50 border-l-4 border-amber-400 p-4 rounded-md">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-amber-400" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-amber-800">Peringatan: Potensi Duplikasi Laporan</h3>
                <div class="mt-2 text-sm text-amber-700">
                    <ul class="list-disc pl-5 space-y-1">
                        @foreach($duplicates as $dup)
                        <li>Laporan #{{ $dup->id1 }} dan #{{ $dup->id2 }} berada dalam radius {{ number_format($dup->distance, 0) }}m.</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Assignment Table -->
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-lg font-medium text-gray-900">Laporan Menunggu Penugasan</h2>
        </div>
        
        @if($reports->isEmpty())
        <div class="px-6 py-12 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">Tidak ada laporan baru</h3>
            <p class="mt-1 text-sm text-gray-500">Semua laporan telah ditugaskan ke petugas.</p>
        </div>
        @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kategori</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deskripsi</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Lokasi</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Tindakan</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($reports as $report)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $report->created_at->format('d M Y H:i') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                {{ $report->category->name }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900">
                            <div class="line-clamp-2">{{ $report->description }}</div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">
                            {{ number_format($report->latitude, 5) }}, {{ number_format($report->longitude, 5) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <button 
                                @click="selectedReport = {{ json_encode([
                                    'id' => $report->id, 
                                    'category' => $report->category->name, 
                                    'description' => $report->description,
                                    'location' => number_format($report->latitude, 5) . ', ' . number_format($report->longitude, 5)
                                ]) }}; assignmentModalOpen = true"
                                class="text-indigo-600 hover:text-indigo-900 font-medium bg-indigo-50 px-3 py-1 rounded-md"
                            >
                                Tugaskan
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $reports->links() }}
        </div>
        @endif

        <!-- Include Assignment Modal (inside x-data scope above; outside <tbody> so the HTML parser doesn't hoist it out of the table) -->
        @include('staff._assign-modal')
    </div>
</div>
@endsection