@extends('layouts.app')
@section('content')
<div class="max-w-6xl mx-auto px-4 py-6">
    {{-- Menu admin --}}
    <div class="flex gap-2 mb-6 border-b pb-3 items-center">
        <a href="{{ route('dashboard') }}"
           class="px-4 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-100 mr-4">
            ← Retour
        </a>
        <a href="{{ route('admin.users.index') }}"
           class="px-4 py-2 rounded-lg text-sm font-medium
           {{ request()->routeIs('admin.users.*') ? 'text-white' : 'text-gray-600 hover:bg-gray-100' }}"
           style="{{ request()->routeIs('admin.users.*') ? 'background-color:#1E3A5F' : '' }}">
            👥 Utilisateurs
        </a>
        <a href="{{ route('admin.settings.branding') }}"
           class="px-4 py-2 rounded-lg text-sm font-medium
           {{ request()->routeIs('admin.settings.branding*') ? 'text-white' : 'text-gray-600 hover:bg-gray-100' }}"
           style="{{ request()->routeIs('admin.settings.branding*') ? 'background-color:#1E3A5F' : '' }}">
            🎨 Personnalisation
        </a>
    </div>
    @yield('admin-content')
</div>
@endsection
