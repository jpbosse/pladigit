@extends('layouts.guest')

@section('title', 'Album protégé')

@section('content')
<div style="text-align:center;margin-bottom:20px;">
    <div style="font-size:32px;margin-bottom:8px;">🔒</div>
    <h1 style="font-size:16px;font-weight:700;color:var(--pd-text);margin-bottom:4px;">
        {{ $link->album->name }}
    </h1>
    <p style="font-size:12px;color:var(--pd-muted);">Cet album est protégé par un mot de passe.</p>
</div>

@if($errors->any())
<div style="margin-bottom:14px;padding:8px 12px;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;font-size:12px;color:#b91c1c;">
    {{ $errors->first() }}
</div>
@endif

<form method="POST" action="{{ route('media.shared.auth', $token) }}">
    @csrf
    <div style="margin-bottom:14px;">
        <label style="display:block;font-size:12px;font-weight:600;color:var(--pd-text);margin-bottom:6px;">
            Mot de passe
        </label>
        <input type="password" name="password" autofocus
               style="width:100%;box-sizing:border-box;padding:9px 12px;border:1.5px solid var(--pd-border);border-radius:8px;font-size:13px;background:var(--pd-bg);color:var(--pd-text);outline:none;"
               placeholder="Entrez le mot de passe…">
    </div>
    <button type="submit"
            style="width:100%;padding:10px;background:var(--pd-navy);color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">
        Accéder à l'album
    </button>
</form>
@endsection
