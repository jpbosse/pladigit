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
                        @foreach(['free','starter','standard','enterprise'] as $plan)
                        <option value="{{ $plan }}" {{ $organization->plan === $plan ? 'selected' : '' }}>
                            {{ ucfirst($plan) }}
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

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Statut</label>
                <select name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    @foreach(['active','suspended','pending'] as $status)
                    <option value="{{ $status }}" {{ $organization->status === $status ? 'selected' : '' }}>
                        {{ ucfirst($status) }}
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
