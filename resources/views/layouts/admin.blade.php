@extends('layouts.app')
@section('content')
<div class="max-w-6xl mx-auto px-4 py-6">

    {{-- ── Barre de navigation admin ──────────────────────────────── --}}
    <div class="flex gap-2 mb-6 border-b pb-3 items-center flex-wrap">

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

        <a href="{{ route('admin.departments.index') }}"
           class="px-4 py-2 rounded-lg text-sm font-medium
           {{ request()->routeIs('admin.departments.*') ? 'text-white' : 'text-gray-600 hover:bg-gray-100' }}"
           style="{{ request()->routeIs('admin.departments.*') ? 'background-color:#1E3A5F' : '' }}">
            🏢 Hiérarchie
        </a>

        <a href="{{ route('admin.settings.branding') }}"
           class="px-4 py-2 rounded-lg text-sm font-medium
           {{ request()->routeIs('admin.settings.branding*') ? 'text-white' : 'text-gray-600 hover:bg-gray-100' }}"
           style="{{ request()->routeIs('admin.settings.branding*') ? 'background-color:#1E3A5F' : '' }}">
            🎨 Personnalisation
        </a>

        <a href="{{ route('admin.settings.nas') }}"
           class="px-4 py-2 rounded-lg text-sm font-medium
           {{ request()->routeIs('admin.settings.nas*') ? 'text-white' : 'text-gray-600 hover:bg-gray-100' }}"
           style="{{ request()->routeIs('admin.settings.nas*') ? 'background-color:#1E3A5F' : '' }}">
            🖧 NAS
        </a>

        <a href="{{ route('admin.settings.media') }}"
           class="px-4 py-2 rounded-lg text-sm font-medium
           {{ request()->routeIs('admin.settings.media*') ? 'text-white' : 'text-gray-600 hover:bg-gray-100' }}"
           style="{{ request()->routeIs('admin.settings.media*') ? 'background-color:#1E3A5F' : '' }}">
            📷 Photothèque
        </a>

        <a href="{{ route('admin.settings.ldap') }}"
           class="px-4 py-2 rounded-lg text-sm font-medium
           {{ request()->routeIs('admin.settings.ldap*') ? 'text-white' : 'text-gray-600 hover:bg-gray-100' }}"
           style="{{ request()->routeIs('admin.settings.ldap*') ? 'background-color:#1E3A5F' : '' }}">
            🔐 LDAP
        </a>

        <a href="{{ route('admin.settings.smtp') }}"
           class="px-4 py-2 rounded-lg text-sm font-medium
           {{ request()->routeIs('admin.settings.smtp*') ? 'text-white' : 'text-gray-600 hover:bg-gray-100' }}"
           style="{{ request()->routeIs('admin.settings.smtp*') ? 'background-color:#1E3A5F' : '' }}">
            📧 SMTP
        </a>

    </div>

    @yield('admin-content')
</div>
@endsection
