@extends('layouts.guest')
@section('title', 'Activer mon compte')

@section('content')

{{-- Titre --}}
<div style="text-align:center;margin-bottom:24px;">
    <div style="width:52px;height:52px;border-radius:14px;margin:0 auto 14px;
                background:linear-gradient(135deg,var(--pd-navy-dark),var(--pd-navy-light));
                display:flex;align-items:center;justify-content:center;">
        <svg style="width:24px;height:24px;fill:none;stroke:#fff;stroke-width:1.8;stroke-linecap:round;" viewBox="0 0 24 24">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
            <circle cx="12" cy="7" r="4"/>
        </svg>
    </div>
    <h1 style="font-family:'Sora',sans-serif;font-size:19px;font-weight:700;
               color:var(--pd-navy);margin:0 0 4px;">
        Activez votre compte
    </h1>
    <p style="font-size:13px;color:var(--pd-muted);margin:0;">
        Bonjour <strong style="color:var(--pd-text);">{{ $user->name }}</strong>,
        choisissez votre mot de passe.
    </p>
</div>

{{-- Erreurs --}}
@if($errors->any())
<div style="background:rgba(231,76,60,0.08);border:1.5px solid rgba(231,76,60,0.25);
            border-radius:10px;padding:11px 14px;margin-bottom:18px;
            font-size:13px;color:#c0392b;">
    {{ $errors->first() }}
</div>
@endif

<form method="POST" action="{{ route('invitation.accept', $token) }}">
    @csrf

    {{-- Email (lecture seule) --}}
    <div style="margin-bottom:14px;">
        <label style="display:block;font-size:13px;font-weight:500;
                      color:var(--pd-text);margin-bottom:6px;">
            Adresse e-mail
        </label>
        <input type="email" value="{{ $user->email }}" disabled
               style="width:100%;box-sizing:border-box;
                      padding:10px 13px;border-radius:9px;
                      border:1.5px solid var(--pd-border);
                      background:var(--pd-bg);color:var(--pd-muted);
                      font-family:'DM Sans',sans-serif;font-size:14px;
                      cursor:not-allowed;">
    </div>

    {{-- Mot de passe --}}
    <div style="margin-bottom:14px;">
        <label style="display:block;font-size:13px;font-weight:500;
                      color:var(--pd-text);margin-bottom:6px;">
            Mot de passe
        </label>
        <input type="password" name="password" required autofocus
               style="width:100%;box-sizing:border-box;
                      padding:10px 13px;border-radius:9px;
                      border:1.5px solid var(--pd-border);
                      background:var(--pd-bg);color:var(--pd-text);
                      font-family:'DM Sans',sans-serif;font-size:14px;
                      outline:none;transition:border-color 0.15s;"
               onfocus="this.style.borderColor='var(--pd-accent)'"
               onblur="this.style.borderColor='var(--pd-border)'">
    </div>

    {{-- Confirmation --}}
    <div style="margin-bottom:22px;">
        <label style="display:block;font-size:13px;font-weight:500;
                      color:var(--pd-text);margin-bottom:6px;">
            Confirmer le mot de passe
        </label>
        <input type="password" name="password_confirmation" required
               style="width:100%;box-sizing:border-box;
                      padding:10px 13px;border-radius:9px;
                      border:1.5px solid var(--pd-border);
                      background:var(--pd-bg);color:var(--pd-text);
                      font-family:'DM Sans',sans-serif;font-size:14px;
                      outline:none;transition:border-color 0.15s;"
               onfocus="this.style.borderColor='var(--pd-accent)'"
               onblur="this.style.borderColor='var(--pd-border)'">
    </div>

    <button type="submit"
            style="width:100%;padding:11px;border-radius:10px;border:none;
                   background:linear-gradient(135deg,var(--pd-navy-dark),var(--pd-navy-light));
                   color:#fff;font-family:'DM Sans',sans-serif;
                   font-size:14px;font-weight:600;cursor:pointer;
                   transition:opacity 0.2s;"
            onmouseover="this.style.opacity='0.9'"
            onmouseout="this.style.opacity='1'">
        Activer mon compte
    </button>
</form>

@endsection
