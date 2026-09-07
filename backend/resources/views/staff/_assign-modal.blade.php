<!-- Alpine.js Modal for Assignment -->
<div x-show="assignmentModalOpen" 
     class="fixed inset-0 z-50 overflow-y-auto" 
     aria-labelledby="modal-title" 
     role="dialog" 
     aria-modal="true"
     x-cloak>
    
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        
        <!-- Background overlay -->
        <div x-show="assignmentModalOpen" 
             x-transition:enter="ease-out duration-300" 
             x-transition:enter-start="opacity-0" 
             x-transition:enter-end="opacity-100" 
             x-transition:leave="ease-in duration-200" 
             x-transition:leave-start="opacity-100" 
             x-transition:leave-end="opacity-0" 
             class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" 
             @click="assignmentModalOpen = false"
             aria-hidden="true"></div>

        <!-- Center modal trick -->
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <!-- Modal panel -->
        <div x-show="assignmentModalOpen" 
             x-transition:enter="ease-out duration-300" 
             x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" 
             x-transition:leave="ease-in duration-200" 
             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" 
             x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
             class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6"
             x-data="{ selectedCrew: null }">
            
            <div class="sm:flex sm:items-start">
                <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-indigo-100 sm:mx-0 sm:h-10 sm:w-10">
                    <svg class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                </div>
                <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                    <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                        Tugaskan Petugas
                    </h3>
                    
                    <!-- Report Summary -->
                    <div class="mt-4 bg-gray-50 rounded-md p-3 text-sm" x-show="selectedReport">
                        <p class="font-medium text-gray-900" x-text="selectedReport?.category"></p>
                        <p class="text-gray-500 mt-1 line-clamp-2" x-text="selectedReport?.description"></p>
                        <p class="text-gray-400 mt-1 text-xs" x-text="selectedReport?.location"></p>
                    </div>

                    <!-- Crew List -->
                    <div class="mt-5 max-h-60 overflow-y-auto space-y-2">
                        @foreach($crewUsers as $crew)
                        <label class="relative flex cursor-pointer rounded-lg border bg-white p-4 shadow-sm focus:outline-none"
                               :class="{'border-indigo-500 ring-2 ring-indigo-500': selectedCrew == {{ $crew->id }}, 'border-gray-300': selectedCrew != {{ $crew->id }}}">
                            <input type="radio" name="crew_selection" value="{{ $crew->id }}" class="sr-only" x-model="selectedCrew">
                            <span class="flex flex-1">
                                <span class="flex flex-col">
                                    <span class="block text-sm font-medium text-gray-900">{{ $crew->name }}</span>
                                    <span class="mt-1 flex items-center text-sm text-gray-500">
                                        {{ $crew->assigned_reports_count ?? 0 }} tugas aktif
                                    </span>
                                </span>
                            </span>
                            <svg class="h-5 w-5 text-indigo-600" :class="{'invisible': selectedCrew != {{ $crew->id }}}" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                        </label>
                        @endforeach
                    </div>
                </div>
            </div>
            
            <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
                <form :action="`/staff/reports/${selectedReport?.id}/assign`" method="POST" class="inline-block w-full sm:w-auto">
                    @csrf
                    <input type="hidden" name="crew_user_id" :value="selectedCrew">
                    <button type="submit" 
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                            :disabled="!selectedCrew">
                        Tugaskan
                    </button>
                </form>
                <button type="button" 
                        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:w-auto sm:text-sm"
                        @click="assignmentModalOpen = false; selectedCrew = null">
                    Batal
                </button>
            </div>
        </div>
    </div>
</div>