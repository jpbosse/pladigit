@extends('layouts.guest')
@section('title', 'Connexion')
 
@section('content')
<h1 class="text-2xl font-bold text-center mb-6" style="color: var(--color-primary, #1E3A5F);">
    Connexion
</h1>
 
{{-- Affichage des erreurs globales --}}
@if ($errors->any())
    <div class="bg-red-50 border border-red-300 text-red-700 rounded-lg p-3 mb-4 text-sm">
        @foreach ($errors->all() as $error)
            <p>{{ $error }}</p>
        @endforeach
    </div>
@endif
 
{{-- Message de succès (ex: après reset mdp) --}}
@if (session('status'))
    <div class="bg-green-50 border border-green-300 text-green-700 rounded-lg p-3 mb-4 text-sm">
        {{ session('status') }}
    </div>
@endif
 
<form method="POST" action="{{ route('login') }}" novalidate>
    @csrf
 
    {{-- Email --}}
    <div class="mb-4">
        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
            Adresse e-mail
        </label>
        <input id="email" name="email" type="email" required autocomplete="email"
               value="{{ old('email') }}"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm
                      focus:outline-none focus:ring-2 focus:ring-blue-500
                      @error('email') border-red-400 @enderror"
               placeholder="votre@email.fr">
        @error('email')
            <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
        @enderror
    </div>
 
    {{-- Mot de passe --}}
    <div class="mb-4">
        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
            Mot de passe
        </label>
        <input id="password" name="password" type="password" required
               autocomplete="current-password"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm
                      focus:outline-none focus:ring-2 focus:ring-blue-500
                      @error('password') border-red-400 @enderror">
        @error('password')
            <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
        @enderror
    </div>
 
    {{-- Se souvenir de moi --}}
    <div class="flex items-center justify-between mb-6">
        <label class="flex items-center text-sm text-gray-600">
            <input type="checkbox" name="remember" class="mr-2">
            Se souvenir de moi
        </label>
        <a href="#"
           class="text-sm text-blue-600 hover:underline">
            Mot de passe oublié ?
        </a>
    </div>
 
    {{-- Bouton de connexion --}}
    <button type="submit"
            class="w-full py-2 px-4 rounded-lg text-white font-medium text-sm
                   transition-colors duration-200"
            style="background-color: var(--color-primary, #1E3A5F);">
        Se connecter
    </button>
</form>
@endsection
