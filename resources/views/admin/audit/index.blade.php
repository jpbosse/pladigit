@extends('layouts.admin')
@section('title', 'Journal d\'audit')

@section('admin-content')

@include('admin.audit._tabs', ['active' => 'journal'])

@php
$actionMap = [
    'user.login'                    => ['🔑', '#3b9ae1', 'Connexion'],
    'user.login_failed'             => ['⛔', '#e74c3c', 'Échec de connexion'],
    'user.logout'                   => ['🚪', '#95a5a6', 'Déconnexion'],
    'user.created'                  => ['➕', '#2ecc71', 'Utilisateur créé'],
    'user.updated'                  => ['✏️', '#3b9ae1', 'Utilisateur modifié'],
    'user.deactivated'              => ['🚫', '#e74c3c', 'Utilisateur désactivé'],
    'user.password_changed'         => ['🔒', '#9b59b6', 'Mot de passe changé'],
    'user.password_reset'           => ['🔄', '#e8a838', 'MDP réinitialisé'],
    'user.locked'                   => ['🔐', '#e74c3c', 'Compte verrouillé'],
    'user.2fa_enabled'              => ['🛡', '#2ecc71', '2FA activé'],
    'user.2fa_disabled'             => ['🛡', '#e8a838', '2FA désactivé'],
    'user.backup_codes_regenerated' => ['🛡', '#9b59b6', 'Codes 2FA régénérés'],
    'department.created'            => ['🏢', '#1abc9c', 'Direction/Service créé'],
    'department.updated'            => ['✏️', '#3b9ae1', 'Structure modifiée'],
    'department.deleted'            => ['🗑', '#e74c3c', 'Structure supprimée'],
    'settings.updated'              => ['⚙️', '#95a5a6', 'Paramètres modifiés'],
    'branding.updated'              => ['🎨', '#e8a838', 'Personnalisation modifiée'],
    'ldap.synced'                   => ['🔗', '#3b9ae1', 'Synchronisation LDAP'],
];
@endphp

{{-- En-tête --}}
<div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:24px;">
    <div>
        <h1 style="font-family:'Sora',sans-serif;font-size:20px;font-weight:700;color:var(--pd-text);margin:0 0 4px;">
            Journal d'audit
        </h1>
        <p style="font-size:13px;color:var(--pd-muted);margin:0;">
            Toutes les actions sensibles enregistrées — rétention 12 mois.
        </p>
    </div>
    <div style="font-size:12px;color:var(--pd-muted);background:var(--pd-surface);border:1.5px solid var(--pd-border);border-radius:9px;padding:6px 14px;align-self:flex-start;">
        {{ number_format($logs->total()) }} entrée{{ $logs->total() > 1 ? 's' : '' }}
    </div>
</div>

{{-- Filtres --}}
<form method="GET" style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;align-items:flex-end;">
    <div style="flex:2;min-width:160px;">
        <label style="display:block;font-size:11px;color:var(--pd-muted);margin-bottom:4px;">Recherche</label>
        <input type="text" name="search" value="{{ request('search') }}"
               placeholder="Utilisateur, action, IP…"
               style="width:100%;padding:9px 12px;border-radius:9px;border:1.5px solid var(--pd-border);
                      background:var(--pd-surface);color:var(--pd-text);font-size:13px;outline:none;box-sizing:border-box;"
               onfocus="this.style.borderColor='var(--pd-accent)'" onblur="this.style.borderColor='var(--pd-border)'">
    </div>
    <div style="flex:1;min-width:140px;">
        <label style="display:block;font-size:11px;color:var(--pd-muted);margin-bottom:4px;">Action</label>
        <select name="action"
                style="width:100%;padding:9px 12px;border-radius:9px;border:1.5px solid var(--pd-border);
                       background:var(--pd-surface);color:var(--pd-text);font-size:13px;outline:none;cursor:pointer;">
            <option value="">Toutes</option>
            @foreach($actions as $act => $cnt)
            <option value="{{ $act }}" {{ request('action') === $act ? 'selected' : '' }}>
                {{ $actionMap[$act][2] ?? $act }} ({{ $cnt }})
            </option>
            @endforeach
        </select>
    </div>
    <div style="flex:1;min-width:130px;">
        <label style="display:block;font-size:11px;color:var(--pd-muted);margin-bottom:4px;">Du</label>
        <input type="date" name="from" value="{{ request('from') }}"
               style="width:100%;padding:9px 12px;border-radius:9px;border:1.5px solid var(--pd-border);background:var(--pd-surface);color:var(--pd-text);font-size:13px;outline:none;box-sizing:border-box;">
    </div>
    <div style="flex:1;min-width:130px;">
        <label style="display:block;font-size:11px;color:var(--pd-muted);margin-bottom:4px;">Au</label>
        <input type="date" name="to" value="{{ request('to') }}"
               style="width:100%;padding:9px 12px;border-radius:9px;border:1.5px solid var(--pd-border);background:var(--pd-surface);color:var(--pd-text);font-size:13px;outline:none;box-sizing:border-box;">
    </div>
    <div style="display:flex;gap:8px;">
        <button type="submit"
                style="padding:9px 20px;border-radius:9px;border:none;cursor:pointer;
                       background:var(--pd-navy);color:#fff;font-size:13px;font-weight:600;white-space:nowrap;">
            Filtrer
        </button>
        @if(request('search') || request('action') || request('from') || request('to'))
        <a href="{{ route('admin.audit.index') }}"
           style="padding:9px 14px;border-radius:9px;border:1.5px solid var(--pd-border);
                  background:var(--pd-surface);color:var(--pd-muted);font-size:13px;text-decoration:none;white-space:nowrap;">
            ✕
        </a>
        @endif
    </div>
</form>

{{-- Tableau --}}
<div style="background:var(--pd-surface);border:1.5px solid var(--pd-border);border-radius:14px;overflow:hidden;box-shadow:var(--pd-shadow);">
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
        <thead>
            <tr style="background:var(--pd-navy-dark);">
                <th style="padding:11px 16px;text-align:left;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.8px;color:rgba(255,255,255,0.65);">Date</th>
                <th style="padding:11px 16px;text-align:left;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.8px;color:rgba(255,255,255,0.65);">Utilisateur</th>
                <th style="padding:11px 16px;text-align:left;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.8px;color:rgba(255,255,255,0.65);">Action</th>
                <th style="padding:11px 16px;text-align:left;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.8px;color:rgba(255,255,255,0.65);">Détails</th>
                <th style="padding:11px 16px;text-align:left;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.8px;color:rgba(255,255,255,0.65);">IP</th>
            </tr>
        </thead>
        <tbody>
            @forelse($logs as $log)
            @php
                [$ico, $col, $lbl] = $actionMap[$log->action] ?? ['📌', '#95a5a6', $log->action];
                $oldVals = $log->old_values ? json_decode($log->old_values, true) : null;
                $newVals = $log->new_values ? json_decode($log->new_values, true) : null;
            @endphp
            <tr style="border-top:1px solid var(--pd-border);transition:background 0.1s;"
                onmouseover="this.style.background='var(--pd-bg)'" onmouseout="this.style.background=''">

                {{-- Date --}}
                <td style="padding:11px 16px;white-space:nowrap;color:var(--pd-muted);">
                    <span title="{{ $log->created_at }}">
                        {{ \Carbon\Carbon::parse($log->created_at)->locale('fr')->isoFormat('D MMM, HH:mm') }}
                    </span>
                </td>

                {{-- Utilisateur --}}
                <td style="padding:11px 16px;">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <div style="width:28px;height:28px;border-radius:7px;flex-shrink:0;
                                    background:linear-gradient(135deg,var(--pd-navy-dark),var(--pd-accent));
                                    display:flex;align-items:center;justify-content:center;
                                    font-family:'Sora',sans-serif;font-size:10px;font-weight:700;color:#fff;">
                            {{ strtoupper(substr($log->user_name ?? '?', 0, 2)) }}
                        </div>
                        <span style="font-weight:500;color:var(--pd-text);">{{ $log->user_name ?? '—' }}</span>
                    </div>
                </td>

                {{-- Action --}}
                <td style="padding:11px 16px;">
                    <span style="display:inline-flex;align-items:center;gap:6px;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;
                                 background:{{ $col }}18;color:{{ $col }};">
                        {{ $ico }} {{ $lbl }}
                    </span>
                </td>

                {{-- Détails --}}
                <td style="padding:11px 16px;color:var(--pd-muted);font-size:12px;max-width:260px;">
                    @if($log->model_type && $log->model_id)
                        <span style="font-size:11px;">{{ class_basename($log->model_type) }} #{{ $log->model_id }}</span>
                    @endif
                    @if($newVals && count($newVals))
                        <div style="margin-top:3px;">
                            @foreach(array_slice($newVals, 0, 2) as $k => $v)
                                <span style="background:var(--pd-bg);border:1px solid var(--pd-border);border-radius:5px;padding:1px 6px;font-size:11px;margin-right:3px;">
                                    {{ $k }}
                                </span>
                            @endforeach
                            @if(count($newVals) > 2)
                                <span style="font-size:11px;color:var(--pd-muted);">+{{ count($newVals) - 2 }} champ(s)</span>
                            @endif
                        </div>
                    @endif
                </td>

                {{-- IP --}}
                <td style="padding:11px 16px;font-size:12px;color:var(--pd-muted);white-space:nowrap;font-family:monospace;">
                    {{ $log->ip_address ?? '—' }}
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" style="padding:48px 16px;text-align:center;color:var(--pd-muted);">
                    <svg style="width:28px;height:28px;fill:none;stroke:currentColor;stroke-width:1.5;margin:0 auto 10px;display:block;opacity:0.3;" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    Aucune entrée trouvée.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($logs->hasPages())
<div style="margin-top:20px;">{{ $logs->links() }}</div>
@endif

@endsection
