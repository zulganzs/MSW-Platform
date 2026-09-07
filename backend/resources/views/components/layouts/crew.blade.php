<x-layouts.app>
    <div class="min-h-screen bg-gray-50 flex flex-col items-center">
        <!-- Topbar -->
        <header class="w-full h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6 shrink-0">
            <div class="flex items-center gap-8">
                <a href="{{ route('crew.dashboard') }}" class="font-bold text-xl text-blue-600">Crew Portal</a>

                <nav class="hidden md:flex items-center gap-6">
                    <a href="#" class="text-sm font-medium text-gray-700 hover:text-blue-600 transition-colors">Tugas Saya</a>
                    <a href="#" class="text-sm font-medium text-gray-700 hover:text-blue-600 transition-colors">Peta Tugas</a>
                </nav>
            </div>

            <div class="flex items-center gap-4">
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
        <main class="flex-1 w-full max-w-[1000px] mx-auto p-4 sm:p-6 lg:p-8">
            {{ $slot }}
        </main>
    </div>
</x-layouts.app>
