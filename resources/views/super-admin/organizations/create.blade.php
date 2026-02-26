@extends('layouts.app')
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
                       placeholder="Mairie d'Olonne-sur-Mer">
                @error('name')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
 
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Slug (identifiant URL) *</label>
                <input type="text" name="slug" value="{{ old('slug') }}" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono
                              focus:outline-none focus:ring-2 focus:ring-blue-500
                              @error('slug') border-red-400 @enderror"
                       placeholder="mairie-olonne">
                <p class="text-xs text-gray-400 mt-1">Lettres minuscules, chiffres et tirets uniquement.</p>
                @error('slug')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
 
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Plan *</label>
                <select name="plan" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm
                               focus:outline-none focus:ring-2 focus:ring-blue-500">
                    @foreach(['free', 'starter', 'standard', 'enterprise'] as $plan)
                    <option value="{{ $plan }}" {{ old('plan') === $plan ? 'selected' : '' }}>
                        {{ Str::ucfirst($plan) }}
                    </option>
                    @endforeach
                </select>
            </div>
 
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Nombre max d'utilisateurs *</label>
                <input type="number" name="max_users" value="{{ old('max_users', 50) }}" required min="1"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm
                              focus:outline-none focus:ring-2 focus:ring-blue-500">
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
