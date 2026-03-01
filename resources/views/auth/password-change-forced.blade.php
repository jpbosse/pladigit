@extends('layouts.app')
@section('title', 'Changement de mot de passe')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-gray-50">
    <div class="bg-white rounded-xl shadow p-8 w-full max-w-md">
        <h1 class="text-2xl font-bold text-gray-800 mb-2">Changement de mot de passe</h1>
        <p class="text-sm text-gray-500 mb-6">Votre mot de passe doit être modifié avant de continuer.</p>

        @if($errors->any())
            <div class="bg-red-50 border border-red-300 text-red-700 rounded-lg p-3 mb-4 text-sm">
                @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('password.change.forced.update') }}">
            @csrf
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Nouveau mot de passe</label>
                <input type="password" name="password"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
            </div>
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Confirmer le mot de passe</label>
                <input type="password" name="password_confirmation"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
            </div>
            <button type="submit"
                    class="w-full py-2 rounded-lg text-white text-sm font-medium"
                    style="background-color: #1E3A5F;">
                Changer le mot de passe
            </button>
        </form>
    </div>
</div>
@endsection
