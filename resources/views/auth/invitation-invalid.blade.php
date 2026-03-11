@extends('layouts.guest')
@section('title', 'Lien invalide')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4">
    <div class="bg-white rounded-xl shadow p-8 w-full max-w-md text-center">

        <div class="text-2xl font-bold text-[#1E3A5F] mb-6">Pladigit</div>

        @if($reason === 'expired')
            <div class="text-4xl mb-4">⏳</div>
            <h1 class="text-xl font-semibold text-gray-800 mb-2">Lien expiré</h1>
            <p class="text-sm text-gray-500">
                Ce lien d'activation a expiré (validité 72 heures).<br>
                Contactez votre administrateur pour recevoir un nouvel email d'invitation.
            </p>
        @else
            <div class="text-4xl mb-4">🔒</div>
            <h1 class="text-xl font-semibold text-gray-800 mb-2">Lien invalide</h1>
            <p class="text-sm text-gray-500">
                Ce lien d'activation est invalide ou a déjà été utilisé.<br>
                Si vous avez déjà activé votre compte, connectez-vous normalement.
            </p>
        @endif

        <a href="{{ route('login') }}"
           class="inline-block mt-6 px-6 py-2.5 rounded-lg text-white text-sm font-semibold hover:opacity-90 transition"
           style="background-color: #1E3A5F;">
            Aller à la connexion
        </a>
    </div>
</div>
@endsection
