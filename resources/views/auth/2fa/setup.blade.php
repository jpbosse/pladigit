@extends('layouts.app')
@section('title', 'Activer la double authentification')
 
@section('content')
<div class="max-w-lg mx-auto">
    <h1 class="text-2xl font-bold mb-2" style="color: var(--color-primary, #1E3A5F);">
        Activer la double authentification
    </h1>
    <p class="text-gray-500 text-sm mb-6">
        Scannez ce QR Code avec votre application (Google Authenticator, Aegis, FreeOTP),
        puis saisissez le code généré pour confirmer l'activation.
    </p>
 
    {{-- QR Code SVG généré côté serveur --}}
    <div class="flex justify-center mb-6 p-4 bg-white border rounded-xl">
        {!! $qr_code !!}
    </div>
 
    {{-- Secret manuel (si scan impossible) --}}
    <details class="mb-6">
        <summary class="text-sm text-gray-500 cursor-pointer hover:text-gray-700">
            Impossible de scanner ? Saisir le code manuellement
        </summary>
        <div class="mt-2 p-3 bg-gray-100 rounded font-mono text-sm text-center break-all">
            {{ $secret }}
        </div>
    </details>
 
    <form method="POST" action="{{ route('2fa.confirm') }}">
        @csrf
        <div class="mb-4">
            <label for="code" class="block text-sm font-medium text-gray-700 mb-1">
                Code de confirmation (6 chiffres)
            </label>
            <input id="code" name="code" type="text" required
                   maxlength="6" inputmode="numeric" autocomplete="one-time-code"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-center
                          font-mono text-xl tracking-widest focus:ring-2 focus:ring-blue-500
                          @error('code') border-red-400 @enderror"
                   placeholder="000000" autofocus>
            @error('code')
                <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>
 
        <button type="submit"
                class="w-full py-2 px-4 rounded-lg text-white font-medium"
                style="background-color: var(--color-primary, #1E3A5F);">
            Confirmer et activer
        </button>
    </form>
</div>
@endsection
