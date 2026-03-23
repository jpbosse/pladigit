@extends('layouts.super-admin')
@section('title', 'Nouvelle organisation')
 
@section('content')
<div class="max-w-2xl mx-auto px-4 py-6">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">Créer une organisation</h1>
 
    <div class="bg-white rounded-xl shadow p-6">
        <form method="POST" action="{{ route('super-admin.organizations.store') }}">
            @csrf
 
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Nom de l'organisation *</label>
                <input type="text" name="name" value="{{ old('name') }}" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm
                              focus:outline-none focus:ring-2 focus:ring-blue-500
                              @error('name') border-red-400 @enderror"
                       placeholder="Communauté de Communes de l'île de Noirmoutier">
                @error('name')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
 
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Slug (identifiant URL) *</label>
                <input type="text" name="slug" value="{{ old('slug') }}" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono
                              focus:outline-none focus:ring-2 focus:ring-blue-500
                              @error('slug') border-red-400 @enderror"
                       placeholder="cc-ile-noirmoutier">
                <p class="text-xs text-gray-400 mt-1">Lettres minuscules, chiffres et tirets uniquement.</p>
                @error('slug')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
 
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Plan *</label>
		<select name="plan" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="communautaire">Communautaire — Gratuit — Illimité</option>
                    <option value="assistance">Assistance — 150 € / mois — 200 utilisateurs</option>
                    <option value="enterprise">Enterprise — Sur devis — Illimité</option>
                </select>
            </div>

            {{-- Quota de stockage --}}
            @php $diskFree = round(disk_free_space('/') / 1073741824, 1); @endphp
            <div class="mb-4 p-4 rounded-lg border border-gray-200 bg-gray-50">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Quota de stockage (Mo)
                </label>
                <div class="flex items-center gap-3">
                    <input type="number" name="storage_quota_mb"
                           value="{{ old('storage_quota_mb', 10240) }}"
                           min="512" step="512"
                           class="w-40 border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono" required>
                    <span class="text-sm text-gray-500" id="quota-label">= 10 Go alloués</span>
                </div>
                <p class="text-xs text-gray-400 mt-2">
                    Minimum 512 Mo. Espace libre sur le serveur : <strong>{{ $diskFree }} Go</strong>.
                </p>
                <script>
                    document.querySelector('[name=storage_quota_mb]').addEventListener('input', function() {
                        var gb = Math.round(this.value / 1024 * 100) / 100;
                        document.getElementById('quota-label').textContent = '= ' + gb + ' Go alloués';
                    });
                </script>
            </div>
 
 
            <div class="flex gap-3">
                <button type="submit"
                        class="px-6 py-2 rounded-lg text-white font-medium text-sm"
                        style="background-color: #1E3A5F;">
                    Créer et provisionner
                </button>
                <a href="{{ route('super-admin.organizations.index') }}"
                   class="px-6 py-2 rounded-lg bg-gray-100 text-gray-700 font-medium text-sm">
                    Annuler
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
