@extends('layouts.guest')
@section('title', 'Vérification en deux étapes')

@section('content')

{{-- Icône + titre --}}
<div style="text-align:center;margin-bottom:24px;">
    <div style="width:52px;height:52px;border-radius:14px;margin:0 auto 14px;
                background:linear-gradient(135deg,var(--pd-navy-dark),var(--pd-navy-light));
                display:flex;align-items:center;justify-content:center;">
        <svg style="width:24px;height:24px;fill:none;stroke:#fff;stroke-width:1.8;stroke-linecap:round;" viewBox="0 0 24 24">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
        </svg>
    </div>
    <h1 style="font-family:'Sora',sans-serif;font-size:19px;font-weight:700;
               color:var(--pd-navy);margin:0 0 6px;">
        Vérification en deux étapes
    </h1>
    <p style="font-size:13px;color:var(--pd-muted);margin:0;line-height:1.5;">
        Saisissez le code à 6 chiffres de votre application,<br>
        ou un code de secours à 8 caractères.
    </p>
</div>

{{-- Erreurs --}}
@if($errors->any())
<div style="background:rgba(231,76,60,0.08);border:1.5px solid rgba(231,76,60,0.25);
            border-radius:10px;padding:11px 14px;margin-bottom:18px;
            font-size:13px;color:#c0392b;text-align:center;">
    @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
</div>
@endif

<form method="POST" action="{{ route('2fa.verify') }}">
    @csrf

    {{-- Champ code --}}
    <div style="margin-bottom:20px;">
        <label for="code"
               style="display:block;font-size:13px;font-weight:500;
                      color:var(--pd-text);margin-bottom:8px;text-align:center;">
            Code de vérification
        </label>
        <input id="code" name="code" type="text" required
               autocomplete="one-time-code"
               inputmode="numeric"
               maxlength="8"
               placeholder="000 000"
               autofocus
               style="width:100%;box-sizing:border-box;
                      padding:14px 16px;border-radius:12px;
                      border:1.5px solid {{ $errors->has('code') ? '#e74c3c' : 'var(--pd-border)' }};
                      background:var(--pd-bg);color:var(--pd-text);
                      font-family:'Sora',monospace;font-size:26px;
                      font-weight:600;letter-spacing:8px;
                      text-align:center;outline:none;
                      transition:border-color 0.15s;"
               onfocus="this.style.borderColor='var(--pd-accent)'"
               onblur="this.style.borderColor='{{ $errors->has('code') ? '#e74c3c' : 'var(--pd-border)' }}'">
        @error('code')
        <p style="font-size:12px;color:#e74c3c;margin:5px 0 0;text-align:center;">{{ $message }}</p>
        @enderror
    </div>

    <button type="submit"
            style="width:100%;padding:11px;border-radius:10px;border:none;
                   background:linear-gradient(135deg,var(--pd-navy-dark),var(--pd-navy-light));
                   color:#fff;font-family:'DM Sans',sans-serif;
                   font-size:14px;font-weight:600;cursor:pointer;
                   transition:opacity 0.2s;"
            onmouseover="this.style.opacity='0.9'"
            onmouseout="this.style.opacity='1'">
        Vérifier
    </button>
</form>

<div style="text-align:center;margin-top:16px;">
    <a href="{{ route('login') }}"
       style="font-size:12.5px;color:var(--pd-muted);text-decoration:none;"
       onmouseover="this.style.color='var(--pd-text)'"
       onmouseout="this.style.color='var(--pd-muted)'">
        ← Retour à la connexion
    </a>
</div>

@endsection
