@extends('layouts.super-admin')
@section('title', 'Modifier ' . $organization->name)

@section('content')
<div class="max-w-2xl mx-auto px-4 py-6">

    <div class="text-sm text-gray-500 mb-4">
        <a href="{{ route('super-admin.organizations.index') }}" class="hover:underline">Organisations</a>
        <span class="mx-2">›</span>
        <a href="{{ route('super-admin.organizations.show', $organization) }}" class="hover:underline">{{ $organization->name }}</a>
        <span class="mx-2">›</span>
        <span>Modifier</span>
    </div>

    <div class="bg-white rounded-xl shadow p-6">
        <h1 class="text-xl font-bold text-gray-800 mb-6">Modifier {{ $organization->name }}</h1>

        <form method="POST" action="{{ route('super-admin.organizations.update', $organization) }}">
            @csrf
            @method('PUT')

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Nom</label>
                <input type="text" name="name" value="{{ old('name', $organization->name) }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Plan</label>
                    <select name="plan" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        @foreach([
                            'communautaire' => 'Communautaire — 0 €/mois (auto-hébergé)',
                            'assistance'    => 'Assistance — 150 €/mois (200 utilisateurs)',
                            'enterprise'    => 'Enterprise — Sur devis (illimité)',
                        ] as $value => $label)
                        <option value="{{ $value }}" {{ $organization->plan === $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Max utilisateurs</label>
                    <input type="number" name="max_users" value="{{ old('max_users', $organization->max_users) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" min="1" required>
                </div>
            </div>

            {{-- Quota de stockage --}}
            @php
                $quotaMb = old('storage_quota_mb', $organization->storage_quota_mb ?? 10240);
                $quotaGb = round($quotaMb / 1024, 2);
                $pct     = $quotaMb > 0 ? min(100, round($usedMb / $quotaMb * 100)) : 0;
            @endphp
            <div class="mb-4 p-4 rounded-lg border border-gray-200 bg-gray-50">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Quota de stockage (Mo)
                </label>
                <div class="flex items-center gap-3">
                    <input type="number" name="storage_quota_mb"
                           value="{{ $quotaMb }}"
                           min="512" step="512"
                           class="w-40 border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono" required>
                    <span class="text-sm text-gray-500">= {{ $quotaGb }} Go alloués</span>
                </div>
                <p class="text-xs text-gray-400 mt-2">Minimum 512 Mo. Espace libre sur le serveur : <strong>{{ $diskFreeGb }} Go</strong>.</p>
                {{-- Barre d'utilisation actuelle --}}
                <div class="mt-3">
                    <div class="flex justify-between text-xs text-gray-500 mb-1">
                        <span>Utilisation actuelle</span>
                        <span>{{ $usedMb }} Mo / {{ $quotaMb }} Mo ({{ $pct }}%)</span>
                    </div>
                    <div class="h-2 rounded-full bg-gray-200 overflow-hidden">
                        <div class="h-full rounded-full transition-all"
                             style="width:{{ $pct }}%;background:{{ $pct > 80 ? '#e74c3c' : ($pct > 60 ? '#E8A838' : '#2ECC71') }};"></div>
                    </div>
                </div>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Statut</label>
                <select name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    @foreach([
                        'active'    => 'Actif',
                        'suspended' => 'Suspendu',
                        'pending'   => 'En attente',
                    ] as $value => $label)
                    <option value="{{ $value }}" {{ $organization->status === $value ? 'selected' : '' }}>
                        {{ $label }}
                    </option>
                    @endforeach
                </select>
            </div>

            @if($errors->any())
                <div class="bg-red-50 border border-red-300 text-red-700 rounded-lg p-3 mb-4 text-sm">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="flex gap-3">
                <button type="submit"
                        class="px-6 py-2 rounded-lg text-white text-sm font-medium"
                        style="background-color: #1E3A5F;">
                    Enregistrer
                </button>
                <a href="{{ route('super-admin.organizations.show', $organization) }}"
                   class="px-6 py-2 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">
                    Annuler
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
