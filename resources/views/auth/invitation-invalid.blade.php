@extends('layouts.guest')
@section('title', 'Lien invalide')

@section('content')

<div style="text-align:center;padding:8px 0;">

    @if($reason === 'expired')
    <div style="font-size:44px;margin-bottom:16px;">⏳</div>
    <h1 style="font-family:'Sora',sans-serif;font-size:19px;font-weight:700;
               color:var(--pd-navy);margin:0 0 10px;">
        Lien expiré
    </h1>
    <p style="font-size:13px;color:var(--pd-muted);line-height:1.6;margin:0 0 24px;">
        Ce lien d'activation a expiré (validité 72 heures).<br>
        Contactez votre administrateur pour recevoir un nouvel email d'invitation.
    </p>
    @else
    <div style="font-size:44px;margin-bottom:16px;">🔒</div>
    <h1 style="font-family:'Sora',sans-serif;font-size:19px;font-weight:700;
               color:var(--pd-navy);margin:0 0 10px;">
        Lien invalide
    </h1>
    <p style="font-size:13px;color:var(--pd-muted);line-height:1.6;margin:0 0 24px;">
        Ce lien d'activation est invalide ou a déjà été utilisé.<br>
        Si vous avez déjà activé votre compte, connectez-vous normalement.
    </p>
    @endif

    <a href="{{ route('login') }}"
       style="display:inline-block;padding:10px 24px;border-radius:10px;
              background:linear-gradient(135deg,var(--pd-navy-dark),var(--pd-navy-light));
              color:#fff;font-family:'DM Sans',sans-serif;
              font-size:14px;font-weight:600;text-decoration:none;
              transition:opacity 0.2s;"
       onmouseover="this.style.opacity='0.9'"
       onmouseout="this.style.opacity='1'">
        Aller à la connexion
    </a>
</div>

@endsection
