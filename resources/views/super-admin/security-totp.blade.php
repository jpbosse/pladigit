@extends('layouts.super-admin')
@section('title', 'Configuration TOTP')

@section('content')
<div style="max-width:520px;margin:0 auto">

    <h1 style="font-size:1.3rem;font-weight:700;color:#4a1010;margin-bottom:0.25rem">
        Double authentification (TOTP)
    </h1>
    <p style="font-size:0.85rem;color:#6b7280;margin-bottom:1.5rem">
        Sécurisez l'accès super-admin avec une application d'authentification (Google Authenticator, Aegis, FreeOTP).
    </p>

    @if($already_enabled)
    <div style="background:#fef3c7;border:1px solid #f59e0b;border-radius:8px;padding:0.875rem 1rem;font-size:0.82rem;color:#92400e;margin-bottom:1.5rem">
        ⚠ Un secret TOTP est déjà configuré. Générer un nouveau code <strong>invalidera l'ancien</strong> dès que vous mettrez à jour le <code>.env</code>.
    </div>
    @endif

    @if($errors->any())
    <div style="background:#fee2e2;border:1px solid #f87171;border-radius:8px;padding:0.875rem 1rem;font-size:0.82rem;color:#b91c1c;margin-bottom:1.5rem">
        {{ $errors->first() }}
    </div>
    @endif

    {{-- Étape 1 --}}
    <div style="background:white;border:1px solid rgba(74,16,16,0.15);border-radius:10px;padding:1.5rem;margin-bottom:1.25rem">
        <div style="font-size:0.72rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#7B1C1C;margin-bottom:0.75rem">
            Étape 1 — Scanner le QR Code
        </div>
        <div style="display:flex;justify-content:center;padding:1rem;background:#f9fafb;border-radius:8px;margin-bottom:1rem">
            {!! $qr_code !!}
        </div>
        <details style="font-size:0.82rem;color:#6b7280">
            <summary style="cursor:pointer">Impossible de scanner ? Saisir le code manuellement</summary>
            <div style="margin-top:0.5rem;padding:0.6rem 0.75rem;background:#f3f4f6;border-radius:6px;font-family:monospace;font-size:0.8rem;word-break:break-all;text-align:center">
                {{ $secret }}
            </div>
        </details>
    </div>

    {{-- Étape 2 --}}
    <div style="background:white;border:1px solid rgba(74,16,16,0.15);border-radius:10px;padding:1.5rem">
        <div style="font-size:0.72rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#7B1C1C;margin-bottom:0.75rem">
            Étape 2 — Confirmer avec un code
        </div>
        <form method="POST" action="{{ route('super-admin.security.totp.confirm') }}">
            @csrf
            <div style="margin-bottom:1rem">
                <label style="display:block;font-size:0.82rem;font-weight:600;color:#374151;margin-bottom:0.4rem">
                    Code à 6 chiffres généré par l'application
                </label>
                <input type="text" name="code" inputmode="numeric" pattern="[0-9]{6}"
                       maxlength="6" autocomplete="one-time-code" autofocus
                       placeholder="123456"
                       style="width:100%;padding:0.65rem 0.875rem;border:1px solid #d1d5db;border-radius:6px;font-family:monospace;font-size:1.2rem;text-align:center;letter-spacing:0.4em;outline:none">
            </div>
            <button type="submit"
                    style="width:100%;padding:0.75rem;background:#7B1C1C;color:white;border:none;border-radius:6px;font-size:0.9rem;font-weight:700;cursor:pointer">
                Valider et obtenir le secret
            </button>
        </form>
    </div>

</div>
@endsection
