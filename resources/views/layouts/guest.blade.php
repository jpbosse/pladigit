<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }} — @yield('title', 'Connexion')</title>
 
    {{-- Assets compilés par Vite --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
 
    {{-- Couleur personnalisée du tenant injectée dynamiquement --}}
    @if(app(App\Services\TenantManager::class)->hasTenant())
    <style>
        :root {
            --color-primary: {{ app(App\Services\TenantManager::class)->current()->primary_color ?? '#1E3A5F' }};
        }
    </style>
    @endif
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center"
      style="background-color: #f0f4f8;">
 
    <div class="w-full max-w-md">
 
        {{-- Logo tenant si disponible --}}
        @if(app(App\Services\TenantManager::class)->hasTenant() &&
            app(App\Services\TenantManager::class)->current()->logo_path)
        <div class="text-center mb-6">
            <img src="{{ asset('storage/' . app(App\Services\TenantManager::class)->current()->logo_path) }}"
                 alt="Logo" class="h-16 mx-auto">
        </div>
        @endif
 
        <div class="bg-white rounded-xl shadow-lg p-8">
            @yield('content')
        </div>
 
        <p class="text-center text-xs text-gray-400 mt-4">
            Propulsé par <strong>Pladigit</strong> — Les Bézots
        </p>
    </div>
</body>
</html>
