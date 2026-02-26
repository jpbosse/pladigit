@extends('layouts.guest')
@section('title', 'Vérification en deux étapes')
 
@section('content')
<div class="text-center mb-6">
    {{-- Icône bouclier --}}
    <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-3"
         style="background-color: var(--color-primary, #1E3A5F);">
        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955
                     11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824
                     10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
        </svg>
    </div>
    <h1 class="text-2xl font-bold" style="color: var(--color-primary, #1E3A5F);">
        Vérification en deux étapes
    </h1>
    <p class="text-gray-500 text-sm mt-1">
        Saisissez le code à 6 chiffres de votre application d'authentification,<br>
        ou un code de secours à 8 caractères.
    </p>
</div>
 
@if ($errors->any())
    <div class="bg-red-50 border border-red-300 text-red-700 rounded-lg p-3 mb-4 text-sm">
        @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
    </div>
@endif
 
<form method="POST" action="{{ route('2fa.verify') }}">
    @csrf
    <div class="mb-6">
        <label for="code" class="block text-sm font-medium text-gray-700 mb-1">
            Code de vérification
        </label>
        <input id="code" name="code" type="text" required
               autocomplete="one-time-code"
               inputmode="numeric"
               maxlength="8"
               class="w-full border border-gray-300 rounded-lg px-3 py-3 text-center
                      text-xl font-mono tracking-widest
                      focus:outline-none focus:ring-2 focus:ring-blue-500
                      @error('code') border-red-400 @enderror"
               placeholder="000000"
               autofocus>
        @error('code')
            <p class="text-red-600 text-xs mt-1 text-center">{{ $message }}</p>
        @enderror
    </div>
 
    <button type="submit"
            class="w-full py-2 px-4 rounded-lg text-white font-medium text-sm"
            style="background-color: var(--color-primary, #1E3A5F);">
        Vérifier
    </button>
</form>
 
<p class="text-center text-xs text-gray-400 mt-4">
    <a href="{{ route('login') }}" class="hover:underline">← Retour à la connexion</a>
</p>
@endsection
