@extends('layouts.app')
@section('title', 'Changement de mot de passe requis')

@section('content')

<div style="max-width:480px;margin:40px auto;padding:0 16px;">

    {{-- Carte --}}
    <div style="background:var(--pd-surface);border:1.5px solid var(--pd-border);
                border-radius:18px;box-shadow:var(--pd-shadow);padding:32px;">

        {{-- En-tête --}}
        <div style="display:flex;align-items:center;gap:14px;margin-bottom:24px;">
            <div style="width:44px;height:44px;border-radius:12px;flex-shrink:0;
                        background:rgba(232,168,56,0.12);
                        display:flex;align-items:center;justify-content:center;">
                <svg style="width:20px;height:20px;fill:none;stroke:var(--pd-gold);stroke-width:1.8;stroke-linecap:round;" viewBox="0 0 24 24">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                    <line x1="12" y1="9" x2="12" y2="13"/>
                    <line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
            </div>
            <div>
                <h1 style="font-family:'Sora',sans-serif;font-size:17px;font-weight:700;
                           color:var(--pd-text);margin:0 0 2px;">
                    Changement de mot de passe requis
                </h1>
                <p style="font-size:13px;color:var(--pd-muted);margin:0;">
                    Votre mot de passe doit être modifié avant de continuer.
                </p>
            </div>
        </div>

        {{-- Erreurs --}}
        @if($errors->any())
        <div style="background:rgba(231,76,60,0.08);border:1.5px solid rgba(231,76,60,0.25);
                    border-radius:10px;padding:11px 14px;margin-bottom:18px;
                    font-size:13px;color:#c0392b;">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
        @endif

        <form method="POST" action="{{ route('password.change.forced.update') }}">
            @csrf

            <div style="margin-bottom:14px;">
                <label style="display:block;font-size:13px;font-weight:500;
                              color:var(--pd-text);margin-bottom:6px;">
                    Nouveau mot de passe
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
                Changer le mot de passe
            </button>
        </form>
    </div>
</div>

@endsection
