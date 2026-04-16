@extends('layouts.super-admin')
@section('title', 'TOTP configuré')

@section('content')
<div style="max-width:520px;margin:0 auto">

    <div style="text-align:center;margin-bottom:1.5rem">
        <div style="font-size:3rem">✅</div>
        <h1 style="font-size:1.3rem;font-weight:700;color:#4a1010;margin-top:0.5rem">Code vérifié avec succès</h1>
        <p style="font-size:0.85rem;color:#6b7280;margin-top:0.25rem">
            Ajoutez maintenant le secret dans votre <code>.env</code> pour activer le TOTP.
        </p>
    </div>

    <div style="background:white;border:1px solid rgba(74,16,16,0.15);border-radius:10px;padding:1.5rem;margin-bottom:1.25rem">
        <div style="font-size:0.72rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#7B1C1C;margin-bottom:0.75rem">
            À ajouter dans .env
        </div>
        <div style="background:#1e293b;border-radius:8px;padding:1rem 1.25rem;font-family:monospace;font-size:0.85rem;color:#86efac;word-break:break-all;position:relative">
            SUPER_ADMIN_TOTP_SECRET={{ $secret }}
            <button onclick="navigator.clipboard.writeText('SUPER_ADMIN_TOTP_SECRET={{ $secret }}');this.textContent='✓ Copié';setTimeout(()=>this.textContent='Copier',2000)"
                    style="position:absolute;top:0.5rem;right:0.5rem;background:rgba(255,255,255,0.1);border:none;color:#cbd5e1;border-radius:4px;padding:0.25rem 0.6rem;font-size:0.72rem;cursor:pointer">
                Copier
            </button>
        </div>
    </div>

    <div style="background:#f0fdf4;border:1px solid #86efac;border-radius:10px;padding:1rem 1.25rem;font-size:0.82rem;color:#166534;margin-bottom:1.5rem">
        <strong>Étapes suivantes :</strong>
        <ol style="margin-top:0.5rem;padding-left:1.25rem;line-height:1.8">
            <li>Copiez la ligne ci-dessus dans votre <code>.env</code> sur le serveur</li>
            <li>Exécutez <code>php artisan config:cache</code></li>
            <li>À votre prochaine connexion, le code TOTP sera demandé</li>
        </ol>
    </div>

    <div style="text-align:center">
        <a href="{{ route('super-admin.dashboard') }}"
           style="display:inline-block;padding:0.65rem 1.5rem;background:#7B1C1C;color:white;border-radius:6px;font-size:0.85rem;font-weight:600;text-decoration:none">
            Retour au tableau de bord
        </a>
    </div>

</div>
@endsection
