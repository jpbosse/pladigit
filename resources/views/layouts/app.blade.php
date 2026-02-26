<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }} — @yield('title', 'Tableau de bord')</title>
 
    @vite(['resources/css/app.css', 'resources/js/app.js'])
 
    @if(app(App\Services\TenantManager::class)->hasTenant())
    <style>
        :root {
            --color-primary: {{ app(App\Services\TenantManager::class)->current()->primary_color ?? '#1E3A5F' }};
        }
    </style>
    @endif
</head>
<body class="bg-gray-50 min-h-screen">
 
    {{-- Navbar --}}
    <nav class="shadow-sm" style="background-color: var(--color-primary, #1E3A5F);">
        <div class="max-w-7xl mx-auto px-4 py-3 flex justify-between items-center">
            <span class="text-white font-bold text-lg">
                {{ app(App\Services\TenantManager::class)->current()?->name ?? config('app.name') }}
            </span>
            <div class="flex items-center gap-4">
                <span class="text-white text-sm">{{ Auth::user()?->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-white text-sm hover:underline">
                        Déconnexion
                    </button>
                </form>
            </div>
        </div>
    </nav>
 
    <main class="py-6">
        @yield('content')
    </main>
 
</body>
</html>
