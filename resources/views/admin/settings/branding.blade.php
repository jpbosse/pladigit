@extends('layouts.admin')
@section('title', 'Personnalisation')

@section('admin-content')
<div class="max-w-2xl mx-auto px-4 py-6">

    <h1 class="text-2xl font-bold text-gray-800 mb-6">Personnalisation</h1>

    @if(session('success'))
        <div class="bg-green-50 border border-green-300 text-green-700 rounded-lg p-3 mb-4 text-sm">
            {{ session('success') }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.settings.branding.update') }}" enctype="multipart/form-data">
        @csrf

        {{-- Couleur primaire --}}
        <div class="bg-white rounded-xl shadow p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Couleur principale</h2>
            <div class="flex items-center gap-4">
                <input type="color" name="primary_color"
                       value="{{ $org->primary_color ?? '#1E3A5F' }}"
                       class="w-16 h-10 rounded cursor-pointer border border-gray-300">
                <input type="text" id="color_hex" value="{{ $org->primary_color ?? '#1E3A5F' }}"
                       class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-32"
                       placeholder="#1E3A5F"
                       oninput="document.querySelector('[name=primary_color]').value=this.value">
            </div>
            <script>
                document.querySelector('[name=primary_color]').addEventListener('input', function() {
                    document.getElementById('color_hex').value = this.value;
                });
            </script>
        </div>

        {{-- Logo --}}
        <div class="bg-white rounded-xl shadow p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Logo</h2>
            @if($org->logo_path)
                <img src="{{ asset('storage/' . $org->logo_path) }}" alt="Logo" class="h-16 mb-4">
            @endif
            <input type="file" name="logo" accept=".png,.jpg,.svg"
                   class="block text-sm text-gray-600">
            <p class="text-xs text-gray-400 mt-1">PNG, JPG ou SVG — max 2 Mo</p>
        </div>

        {{-- Image de fond login --}}
        <div class="bg-white rounded-xl shadow p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Image de fond (page de connexion)</h2>
            @if($org->login_bg_path)
                <img src="{{ asset('storage/' . $org->login_bg_path) }}" alt="Fond" class="h-24 mb-4 rounded">
            @endif
            <input type="file" name="login_bg" accept=".png,.jpg"
                   class="block text-sm text-gray-600">
            <p class="text-xs text-gray-400 mt-1">PNG ou JPG — max 4 Mo</p>
        </div>

        <button type="submit"
                class="px-6 py-2 rounded-lg text-white text-sm font-medium"
                style="background-color: #1E3A5F;">
            Sauvegarder
        </button>
    </form>
</div>
@endsection
