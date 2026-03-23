@extends('layouts.admin')
@section('title', 'Sécurité & Politique des mots de passe')

@section('admin-content')

<div style="max-width:860px;">

    <div style="margin-bottom:24px;">
        <h1 style="font-size:22px;font-weight:700;color:var(--pd-navy);margin:0 0 4px;">Sécurité & Mots de passe</h1>
        <p style="font-size:13px;color:var(--pd-muted);margin:0;">Ces paramètres s'appliquent à tous les utilisateurs de votre organisation.</p>
    </div>

    @if(session('success'))
    <div style="background:#F0FDF4;border:0.5px solid #86EFAC;color:#065F46;border-radius:8px;padding:10px 16px;margin-bottom:20px;font-size:13px;display:flex;align-items:center;gap:8px;">
        ✓ {{ session('success') }}
    </div>
    @endif

    @if($errors->any())
    <div style="background:#FEF2F2;border:0.5px solid #FCA5A5;color:#991B1B;border-radius:8px;padding:10px 16px;margin-bottom:20px;font-size:13px;">
        {{ $errors->first() }}
    </div>
    @endif

    <form method="POST" action="{{ route('admin.settings.security.update') }}">
        @csrf @method('PUT')

        {{-- ── Politique des mots de passe ── --}}
        <div style="background:var(--pd-surface);border:0.5px solid var(--pd-border);border-radius:12px;margin-bottom:20px;overflow:hidden;">
            <div style="padding:14px 20px;background:var(--pd-surface2);border-bottom:0.5px solid var(--pd-border);display:flex;align-items:center;gap:10px;">
                <div style="width:32px;height:32px;border-radius:8px;background:rgba(30,58,95,0.1);display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;">🔑</div>
                <div>
                    <div style="font-size:14px;font-weight:700;color:var(--pd-navy);">Politique des mots de passe</div>
                    <div style="font-size:11px;color:var(--pd-muted);">Règles appliquées lors de la création et du changement de mot de passe</div>
                </div>
            </div>
            <div style="padding:20px;">
                {{-- Longueur + Historique --}}
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;">
                    <div>
                        <label class="pd-label">Longueur minimale <span style="color:var(--pd-danger);">*</span></label>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <input type="number" name="pwd_min_length"
                                   value="{{ old('pwd_min_length', $settings->pwd_min_length ?? 12) }}"
                                   min="6" max="64" class="pd-input" style="width:100px;">
                            <span style="font-size:12px;color:var(--pd-muted);">caractères (6–64)</span>
                        </div>
                        <div style="font-size:11px;color:var(--pd-muted);margin-top:4px;">Défaut : 12. Recommandé : 12 minimum.</div>
                    </div>
                    <div>
                        <label class="pd-label">Historique des mots de passe <span style="color:var(--pd-danger);">*</span></label>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <input type="number" name="pwd_history_count"
                                   value="{{ old('pwd_history_count', $settings->pwd_history_count ?? 5) }}"
                                   min="0" max="24" class="pd-input" style="width:100px;">
                            <span style="font-size:12px;color:var(--pd-muted);">derniers mots de passe</span>
                        </div>
                        <div style="font-size:11px;color:var(--pd-muted);margin-top:4px;">0 = pas d'historique. Défaut : 5.</div>
                    </div>
                </div>
                {{-- Expiration --}}
                <div style="margin-bottom:20px;">
                    <label class="pd-label">Expiration du mot de passe</label>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <input type="number" name="pwd_validity_days"
                               value="{{ old('pwd_validity_days', $settings->pwd_validity_days ?? 365) }}"
                               min="0" max="3650" class="pd-input" style="width:100px;">
                        <span style="font-size:12px;color:var(--pd-muted);">jours (0 = pas d'expiration)</span>
                    </div>
                    <div style="font-size:11px;color:var(--pd-muted);margin-top:4px;">Défaut : 365 jours. Les utilisateurs seront invités à changer leur mot de passe à l'échéance.</div>
                </div>
                {{-- Complexité --}}
                <div>
                    <label class="pd-label" style="margin-bottom:10px;display:block;">Exigences de complexité</label>
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;">
                        @foreach([
                            ['pwd_require_uppercase', 'Majuscules',         'Au moins une lettre A–Z',       '🔠'],
                            ['pwd_require_number',    'Chiffres',            'Au moins un chiffre 0–9',       '🔢'],
                            ['pwd_require_special',   'Caractères spéciaux', 'Au moins un caractère !@#$...', '🔣'],
                        ] as [$name, $label, $hint, $icon])
                        @php $checked = old($name, $settings->$name ?? true); @endphp
                        <label style="display:flex;align-items:flex-start;gap:10px;padding:12px;border:0.5px solid var(--pd-border);border-radius:8px;cursor:pointer;background:{{ $checked ? 'rgba(30,58,95,0.04)' : 'var(--pd-surface2)' }};">
                            <input type="checkbox" name="{{ $name }}" value="1"
                                   {{ $checked ? 'checked' : '' }}
                                   style="accent-color:var(--pd-navy);margin-top:2px;flex-shrink:0;width:16px;height:16px;">
                            <div>
                                <div style="font-size:12px;font-weight:600;color:var(--pd-text);">{{ $icon }} {{ $label }}</div>
                                <div style="font-size:10px;color:var(--pd-muted);margin-top:2px;">{{ $hint }}</div>
                            </div>
                        </label>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Sessions ── --}}
        <div style="background:var(--pd-surface);border:0.5px solid var(--pd-border);border-radius:12px;margin-bottom:20px;overflow:hidden;">
            <div style="padding:14px 20px;background:var(--pd-surface2);border-bottom:0.5px solid var(--pd-border);display:flex;align-items:center;gap:10px;">
                <div style="width:32px;height:32px;border-radius:8px;background:rgba(8,145,178,0.1);display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;">⏱</div>
                <div>
                    <div style="font-size:14px;font-weight:700;color:var(--pd-navy);">Sessions</div>
                    <div style="font-size:11px;color:var(--pd-muted);">Durée d'inactivité avant déconnexion automatique</div>
                </div>
            </div>
            <div style="padding:20px;">
                @php $mins = old('session_lifetime_minutes', $settings->session_lifetime_minutes ?? 120); @endphp
                <div style="display:flex;align-items:center;gap:10px;">
                    <input type="number" name="session_lifetime_minutes"
                           value="{{ $mins }}" min="5" max="10080"
                           class="pd-input" style="width:120px;">
                    <span style="font-size:12px;color:var(--pd-muted);">minutes</span>
                    <span style="font-size:11px;color:var(--pd-muted);background:var(--pd-bg2);padding:3px 8px;border-radius:6px;">
                        ≈ {{ $mins >= 1440 ? round($mins/1440, 1).' jour(s)' : ($mins >= 60 ? round($mins/60, 1).' heure(s)' : $mins.' min') }}
                    </span>
                </div>
                <div style="font-size:11px;color:var(--pd-muted);margin-top:6px;">Entre 5 min et 7 jours (10 080 min). Défaut : 120 min.</div>
            </div>
        </div>

        {{-- ── Verrouillage ── --}}
        <div style="background:var(--pd-surface);border:0.5px solid var(--pd-border);border-radius:12px;margin-bottom:20px;overflow:hidden;">
            <div style="padding:14px 20px;background:var(--pd-surface2);border-bottom:0.5px solid var(--pd-border);display:flex;align-items:center;gap:10px;">
                <div style="width:32px;height:32px;border-radius:8px;background:rgba(220,38,38,0.08);display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;">🔒</div>
                <div>
                    <div style="font-size:14px;font-weight:700;color:var(--pd-navy);">Verrouillage de compte</div>
                    <div style="font-size:11px;color:var(--pd-muted);">Protection contre les attaques par force brute</div>
                </div>
            </div>
            <div style="padding:20px;display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div>
                    <label class="pd-label">Tentatives max avant verrouillage <span style="color:var(--pd-danger);">*</span></label>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <input type="number" name="login_max_attempts"
                               value="{{ old('login_max_attempts', $settings->login_max_attempts ?? 5) }}"
                               min="3" max="20" class="pd-input" style="width:100px;">
                        <span style="font-size:12px;color:var(--pd-muted);">tentatives</span>
                    </div>
                    <div style="font-size:11px;color:var(--pd-muted);margin-top:4px;">Entre 3 et 20. Défaut : 5.</div>
                </div>
                <div>
                    <label class="pd-label">Durée de verrouillage <span style="color:var(--pd-danger);">*</span></label>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <input type="number" name="login_lockout_minutes"
                               value="{{ old('login_lockout_minutes', $settings->login_lockout_minutes ?? 15) }}"
                               min="1" max="1440" class="pd-input" style="width:100px;">
                        <span style="font-size:12px;color:var(--pd-muted);">minutes</span>
                    </div>
                    <div style="font-size:11px;color:var(--pd-muted);margin-top:4px;">Entre 1 min et 24h. Défaut : 15 min.</div>
                </div>
            </div>
        </div>

        {{-- ── 2FA obligatoire ── --}}
        <div style="background:var(--pd-surface);border:0.5px solid var(--pd-border);border-radius:12px;margin-bottom:24px;overflow:hidden;">
            <div style="padding:14px 20px;background:var(--pd-surface2);border-bottom:0.5px solid var(--pd-border);display:flex;align-items:center;gap:10px;">
                <div style="width:32px;height:32px;border-radius:8px;background:rgba(5,150,105,0.08);display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;">📱</div>
                <div>
                    <div style="font-size:14px;font-weight:700;color:var(--pd-navy);">Double authentification (2FA)</div>
                    <div style="font-size:11px;color:var(--pd-muted);">Authentification TOTP via Google Authenticator, Aegis, etc.</div>
                </div>
            </div>
            <div style="padding:20px;">
                <label style="display:flex;align-items:center;gap:12px;cursor:pointer;">
                    <input type="checkbox" name="force_2fa" value="1"
                           {{ old('force_2fa', $settings->force_2fa ?? false) ? 'checked' : '' }}
                           style="accent-color:var(--pd-navy);width:18px;height:18px;flex-shrink:0;">
                    <div>
                        <div style="font-size:13px;font-weight:600;color:var(--pd-text);">Rendre le 2FA obligatoire pour tous les comptes</div>
                        <div style="font-size:11px;color:var(--pd-muted);margin-top:2px;">Les utilisateurs sans 2FA activé seront redirigés vers la configuration à leur prochaine connexion.</div>
                    </div>
                </label>
            </div>
        </div>

        {{-- ── Actions ── --}}
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
            <button type="submit" class="pd-btn pd-btn-primary">
                Enregistrer les paramètres
            </button>
            <div style="font-size:11px;color:var(--pd-muted);background:var(--pd-surface2);padding:8px 14px;border-radius:8px;border:0.5px solid var(--pd-border);line-height:1.8;">
                <strong>Politique active :</strong>
                min. {{ $settings->pwd_min_length ?? 12 }} car.
                @if($settings->pwd_require_uppercase ?? true)<span style="background:#EEF2FF;color:#3730A3;padding:1px 5px;border-radius:4px;margin:0 2px;font-size:10px;">Maj</span>@endif
                @if($settings->pwd_require_number ?? true)<span style="background:#FEF3C7;color:#92400E;padding:1px 5px;border-radius:4px;margin:0 2px;font-size:10px;">Chiffre</span>@endif
                @if($settings->pwd_require_special ?? true)<span style="background:#FEE2E2;color:#991B1B;padding:1px 5px;border-radius:4px;margin:0 2px;font-size:10px;">Spécial</span>@endif
                · Expiration {{ $settings->pwd_validity_days ? $settings->pwd_validity_days.'j' : 'désactivée' }}
                · Historique {{ $settings->pwd_history_count ?? 5 }}
                · Session {{ $settings->session_lifetime_minutes ?? 120 }} min
                @if($settings->force_2fa ?? false)<span style="background:#D1FAE5;color:#065F46;padding:1px 5px;border-radius:4px;margin-left:4px;font-size:10px;">2FA obligatoire</span>@endif
            </div>
        </div>

    </form>
</div>

@endsection
