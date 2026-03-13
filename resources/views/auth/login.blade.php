@extends('layouts.guest')
@section('title', 'Connexion')

@section('content')

{{-- Titre --}}
<div style="text-align:center;margin-bottom:24px;">
    <h1 style="font-family:'Sora',sans-serif;font-size:20px;font-weight:700;
               color:var(--pd-navy);margin:0 0 4px;">
        Connexion
    </h1>
    <p style="font-size:13px;color:var(--pd-muted);margin:0;">
        Accédez à votre espace de travail
    </p>
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

{{-- Status (ex: après reset mdp) --}}
@if(session('status'))
<div style="background:rgba(46,204,113,0.08);border:1.5px solid rgba(46,204,113,0.3);
            border-radius:10px;padding:11px 14px;margin-bottom:18px;
            font-size:13px;color:#1a8a4a;">
    {{ session('status') }}
</div>
@endif

<form method="POST" action="{{ route('login') }}" novalidate>
    @csrf

    {{-- Email --}}
    <div style="margin-bottom:16px;">
        <label for="email"
               style="display:block;font-size:13px;font-weight:500;
                      color:var(--pd-text);margin-bottom:6px;">
            Adresse e-mail
        </label>
        <input id="email" name="email" type="email" required
               autocomplete="email"
               value="{{ old('email') }}"
               placeholder="votre@email.fr"
               style="width:100%;box-sizing:border-box;
                      padding:10px 13px;border-radius:9px;
                      border:1.5px solid {{ $errors->has('email') ? '#e74c3c' : 'var(--pd-border)' }};
                      background:var(--pd-bg);color:var(--pd-text);
                      font-family:'DM Sans',sans-serif;font-size:14px;
                      outline:none;transition:border-color 0.15s;"
               onfocus="this.style.borderColor='var(--pd-accent)'"
               onblur="this.style.borderColor='{{ $errors->has('email') ? '#e74c3c' : 'var(--pd-border)' }}'">
        @error('email')
        <p style="font-size:12px;color:#e74c3c;margin:5px 0 0;">{{ $message }}</p>
        @enderror
    </div>

    {{-- Mot de passe --}}
    <div style="margin-bottom:18px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
            <label for="password"
                   style="font-size:13px;font-weight:500;color:var(--pd-text);">
                Mot de passe
            </label>
            <a href="#"
               style="font-size:12px;color:var(--pd-accent);text-decoration:none;"
               onmouseover="this.style.textDecoration='underline'"
               onmouseout="this.style.textDecoration='none'">
                Mot de passe oublié ?
            </a>
        </div>
        <div style="position:relative;">
            <input id="password" name="password" type="password" required
                   autocomplete="current-password"
                   style="width:100%;box-sizing:border-box;
                          padding:10px 42px 10px 13px;border-radius:9px;
                          border:1.5px solid {{ $errors->has('password') ? '#e74c3c' : 'var(--pd-border)' }};
                          background:var(--pd-bg);color:var(--pd-text);
                          font-family:'DM Sans',sans-serif;font-size:14px;
                          outline:none;transition:border-color 0.15s;"
                   onfocus="this.style.borderColor='var(--pd-accent)'"
                   onblur="this.style.borderColor='{{ $errors->has('password') ? '#e74c3c' : 'var(--pd-border)' }}'">
            {{-- Toggle visibilité --}}
            <button type="button" onclick="togglePwd()"
                    style="position:absolute;right:11px;top:50%;transform:translateY(-50%);
                           background:none;border:none;cursor:pointer;
                           color:var(--pd-muted);padding:4px;"
                    aria-label="Afficher/masquer le mot de passe">
                <svg id="pwd-eye" style="width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:1.8;" viewBox="0 0 24 24">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" stroke-linecap="round"/>
                    <circle cx="12" cy="12" r="3"/>
                </svg>
            </button>
        </div>
        @error('password')
        <p style="font-size:12px;color:#e74c3c;margin:5px 0 0;">{{ $message }}</p>
        @enderror
    </div>

    {{-- Se souvenir de moi --}}
    <div style="margin-bottom:20px;">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;
                      font-size:13px;color:var(--pd-muted);">
            <input type="checkbox" name="remember"
                   style="width:15px;height:15px;accent-color:var(--pd-accent);cursor:pointer;">
            Se souvenir de moi
        </label>
    </div>

    {{-- Bouton connexion --}}
    <button type="submit"
            style="width:100%;padding:11px;border-radius:10px;border:none;
                   background:linear-gradient(135deg,var(--pd-navy-dark),var(--pd-navy-light));
                   color:#fff;font-family:'DM Sans',sans-serif;
                   font-size:14px;font-weight:600;cursor:pointer;
                   transition:opacity 0.2s,transform 0.1s;"
            onmouseover="this.style.opacity='0.9'"
            onmouseout="this.style.opacity='1'"
            onmousedown="this.style.transform='scale(0.99)'"
            onmouseup="this.style.transform='scale(1)'">
        Se connecter
    </button>

</form>

@push('scripts')
<script>
function togglePwd() {
    var inp = document.getElementById('password');
    var eye = document.getElementById('pwd-eye');
    if (inp.type === 'password') {
        inp.type = 'text';
        eye.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24" stroke-linecap="round" stroke-width="1.8" fill="none" stroke="currentColor"/><line x1="1" y1="1" x2="23" y2="23" stroke-linecap="round" stroke-width="1.8" stroke="currentColor"/>';
    } else {
        inp.type = 'password';
        eye.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" stroke-linecap="round" stroke-width="1.8" fill="none" stroke="currentColor"/><circle cx="12" cy="12" r="3" stroke-width="1.8" fill="none" stroke="currentColor"/>';
    }
}
</script>
@endpush

@endsection
