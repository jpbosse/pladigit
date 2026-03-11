@extends('layouts.guest')
@section('title', 'Activer mon compte')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4">
    <div class="bg-white rounded-xl shadow p-8 w-full max-w-md">

        <div class="text-center mb-6">
            <div class="text-2xl font-bold text-[#1E3A5F] mb-1">Pladigit</div>
            <h1 class="text-xl font-semibold text-gray-800">Activez votre compte</h1>
            <p class="text-sm text-gray-500 mt-1">Bonjour <strong>{{ $user->name }}</strong>, choisissez votre mot de passe.</p>
        </div>

        @if($errors->any())
            <div class="bg-red-50 border border-red-300 text-red-700 rounded-lg p-3 mb-4 text-sm">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('invitation.accept', $token) }}">
            @csrf

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" value="{{ $user->email }}" disabled
                       class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-gray-50 text-gray-500">
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Mot de passe</label>
                <input type="password" name="password" required autofocus
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1E3A5F]">
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Confirmer le mot de passe</label>
                <input type="password" name="password_confirmation" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1E3A5F]">
            </div>

            <button type="submit"
                    class="w-full py-2.5 rounded-lg text-white text-sm font-semibold hover:opacity-90 transition"
                    style="background-color: #1E3A5F;">
                Activer mon compte
            </button>
        </form>
    </div>
</div>
@endsection
