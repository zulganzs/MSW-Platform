<x-layouts.app>
    <div x-data="{ sidebarOpen: localStorage.getItem('sidebarOpen') !== 'false' }" 
         x-init="$watch('sidebarOpen', val => localStorage.setItem('sidebarOpen', val))"
         class="min-h-screen bg-gray-50 flex">
         
        <!-- Sidebar -->
        <aside :class="sidebarOpen ? 'w-[280px]' : 'w-16'" class="bg-slate-900 text-slate-300 transition-all duration-300 flex flex-col shrink-0 min-h-screen">
            <div class="h-16 flex items-center justify-between px-4 border-b border-slate-800 shrink-0">
                <a href="{{ route('staff.dashboard') }}" x-show="sidebarOpen" class="font-bold text-xl text-white truncate transition-opacity">Staff Portal</a>
                <button @click="sidebarOpen = !sidebarOpen" class="p-1 hover:text-white rounded transition-colors mx-auto" :class="sidebarOpen ? '' : 'mx-auto'">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
                </button>
            </div>
            
            <nav class="flex-1 py-4 flex flex-col gap-1 px-2 overflow-y-auto">
                <a href="{{ route('staff.dashboard') }}" class="flex items-center gap-3 px-3 py-2 rounded hover:bg-slate-800 transition-colors {{ request()->routeIs('staff.dashboard') ? 'bg-slate-800 text-white' : '' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0"><rect x="3" y="3" width="7" height="9"></rect><rect x="14" y="3" width="7" height="5"></rect><rect x="14" y="12" width="7" height="9"></rect><rect x="3" y="16" width="7" height="5"></rect></svg>
                    <span x-show="sidebarOpen" class="truncate transition-opacity">Dashboard</span>
                </a>
                <!-- Add other nav items here -->
            </nav>
            
            <div class="p-4 border-t border-slate-800 flex items-center gap-3 shrink-0">
                <div class="w-8 h-8 bg-slate-700 text-white rounded-full flex items-center justify-center font-bold shrink-0">
                    {{ substr(auth()->user()->name, 0, 1) }}
                </div>
                <div x-show="sidebarOpen" class="overflow-hidden transition-opacity">
                    <p class="text-sm font-medium text-white truncate">{{ auth()->user()->name }}</p>
                    <p class="text-xs text-slate-400 truncate">{{ auth()->user()->email }}</p>
                </div>
            </div>
        </aside>

        <div class="flex-1 flex flex-col min-w-0">
            <!-- Topbar -->
            <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6 shrink-0">
                <div class="flex-1 flex items-center">
                    <div class="relative w-full max-w-md hidden sm:block">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" /></svg>
                        </div>
                        <input type="text" class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="Search...">
                    </div>
                </div>
                
                <div class="flex items-center gap-4 ml-4">
                    <x-dropdown>
                        <x-slot:trigger>
                            <button class="flex items-center gap-2 hover:bg-gray-50 p-2 rounded-full transition-colors">
                                <div class="w-8 h-8 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center font-bold">
                                    {{ substr(auth()->user()->name, 0, 1) }}
                                </div>
                            </button>
                        </x-slot:trigger>
                        <x-slot:content>
                            <div class="px-4 py-2 border-b border-gray-100">
                                <p class="text-sm font-medium text-gray-900">{{ auth()->user()->name }}</p>
                                <p class="text-sm text-gray-500">{{ auth()->user()->email }}</p>
                            </div>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-50">Keluar</button>
                            </form>
                        </x-slot:content>
                    </x-dropdown>
                </div>
            </header>

            <!-- Main Content -->
            <main class="flex-1 p-4 sm:p-6 lg:p-8 overflow-y-auto">
                {{ $slot }}
            </main>
        </div>
    </div>
</x-layouts.app>