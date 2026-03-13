@extends('layouts.admin')
@section('title', 'Modifier ' . $user->name)

@section('admin-content')

<div style="max-width:720px;">

    {{-- En-tête avec avatar --}}
    <div style="display:flex;align-items:center;gap:16px;margin-bottom:24px;">
        <div style="width:48px;height:48px;border-radius:12px;flex-shrink:0;
                    background:linear-gradient(135deg,var(--pd-navy-dark),var(--pd-accent));
                    display:flex;align-items:center;justify-content:center;
                    font-family:'Sora',sans-serif;font-size:18px;font-weight:700;color:#fff;">
            {{ strtoupper(substr($user->name,0,2)) }}
        </div>
        <div>
            <h1 style="font-family:'Sora',sans-serif;font-size:20px;font-weight:700;color:var(--pd-text);margin:0 0 3px;">
                {{ $user->name }}
            </h1>
            <div style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--pd-muted);">
                <span>{{ $user->email }}</span>
                @if($user->ldap_dn)
                <span style="display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;background:rgba(59,154,225,0.1);color:var(--pd-accent);">LDAP</span>
                @endif
            </div>
        </div>
    </div>

    {{-- Alerte LDAP --}}
    @if($user->ldap_dn)
    <div style="background:rgba(59,154,225,0.08);border:1.5px solid rgba(59,154,225,0.2);border-radius:10px;padding:11px 16px;margin-bottom:20px;font-size:13px;color:var(--pd-accent);display:flex;align-items:center;gap:8px;">
        <svg style="width:15px;height:15px;flex-shrink:0;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        Compte LDAP — le nom et l'email sont gérés par l'annuaire Active Directory.
    </div>
    @endif

    {{-- Flash --}}
    @if(session('success'))
    <div style="background:rgba(46,204,113,0.08);border:1.5px solid rgba(46,204,113,0.3);border-radius:10px;padding:11px 16px;margin-bottom:20px;font-size:13px;color:#1a8a4a;display:flex;align-items:center;gap:8px;">
        <svg style="width:15px;height:15px;flex-shrink:0;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
        {{ session('success') }}
    </div>
    @endif
    @if($errors->any())
    <div style="background:rgba(231,76,60,0.08);border:1.5px solid rgba(231,76,60,0.25);border-radius:10px;padding:11px 16px;margin-bottom:20px;font-size:13px;color:#c0392b;">
        {{ $errors->first() }}
    </div>
    @endif

    <div style="background:var(--pd-surface);border:1.5px solid var(--pd-border);border-radius:14px;padding:28px;box-shadow:var(--pd-shadow);">
        <form method="POST" action="{{ route('admin.users.update', $user) }}">
            @csrf @method('PUT')

            {{-- Section : Identité --}}
            <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--pd-muted);margin:0 0 14px;">Identité</p>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px;">
                <div>
                    <label style="display:block;font-size:13px;font-weight:500;color:var(--pd-text);margin-bottom:6px;">Nom complet</label>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}" required
                           {{ $user->ldap_dn ? 'disabled' : '' }}
                           style="width:100%;box-sizing:border-box;padding:10px 13px;border-radius:9px;border:1.5px solid var(--pd-border);background:{{ $user->ldap_dn ? 'var(--pd-bg)' : 'var(--pd-bg)' }};color:var(--pd-text);font-family:'DM Sans',sans-serif;font-size:14px;outline:none;{{ $user->ldap_dn ? 'opacity:0.6;cursor:not-allowed;' : '' }}"
                           onfocus="this.style.borderColor='var(--pd-accent)'" onblur="this.style.borderColor='var(--pd-border)'">
                </div>
                <div>
                    <label style="display:block;font-size:13px;font-weight:500;color:var(--pd-text);margin-bottom:6px;">Adresse e-mail</label>
                    <input type="email" value="{{ $user->email }}" disabled
                           style="width:100%;box-sizing:border-box;padding:10px 13px;border-radius:9px;border:1.5px solid var(--pd-border);background:var(--pd-bg);color:var(--pd-muted);font-family:'DM Sans',sans-serif;font-size:14px;outline:none;opacity:0.6;cursor:not-allowed;">
                    <p style="font-size:11px;color:var(--pd-muted);margin:4px 0 0;">Non modifiable</p>
                </div>
            </div>

            {{-- Section : Rôle & Statut --}}
            <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--pd-muted);margin:0 0 14px;">Rôle & Statut</p>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px;">
                <div>
                    <label style="display:block;font-size:13px;font-weight:500;color:var(--pd-text);margin-bottom:6px;">Rôle</label>
                    <select name="role" id="roleSelect" onchange="updateDepartmentLabel()"
                            style="width:100%;padding:10px 13px;border-radius:9px;border:1.5px solid var(--pd-border);background:var(--pd-bg);color:var(--pd-text);font-family:'DM Sans',sans-serif;font-size:14px;outline:none;cursor:pointer;"
                            onfocus="this.style.borderColor='var(--pd-accent)'" onblur="this.style.borderColor='var(--pd-border)'">
                        @foreach(App\Enums\UserRole::cases() as $role)
                            <option value="{{ $role->value }}" {{ old('role', $user->role) === $role->value ? 'selected' : '' }}>
                                {{ $role->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:13px;font-weight:500;color:var(--pd-text);margin-bottom:6px;">Statut</label>
                    <select name="status"
                            style="width:100%;padding:10px 13px;border-radius:9px;border:1.5px solid var(--pd-border);background:var(--pd-bg);color:var(--pd-text);font-family:'DM Sans',sans-serif;font-size:14px;outline:none;cursor:pointer;"
                            onfocus="this.style.borderColor='var(--pd-accent)'" onblur="this.style.borderColor='var(--pd-border)'">
                        @foreach(['active' => 'Actif', 'inactive' => 'Inactif', 'locked' => 'Verrouillé'] as $val => $lbl)
                            <option value="{{ $val }}" {{ old('status', $user->status) === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Section : Directions & Services --}}
            <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--pd-muted);margin:0 0 14px;">Organisation</p>
            <div style="margin-bottom:24px;border:1.5px solid var(--pd-border);border-radius:12px;padding:16px;background:var(--pd-bg);">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                    <label style="font-size:13px;font-weight:500;color:var(--pd-text);" id="deptLabel">Directions / Services</label>
                    <button type="button" onclick="toggleNewDept()"
                            style="font-size:12px;color:var(--pd-accent);background:none;border:none;cursor:pointer;font-family:inherit;padding:0;">
                        + Créer un nouveau
                    </button>
                </div>
                <div style="max-height:220px;overflow-y:auto;display:flex;flex-direction:column;gap:4px;">
                    @forelse($directions as $direction)
                        <label style="display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:8px;cursor:pointer;border:1.5px solid transparent;transition:all 0.15s;"
                               onmouseover="this.style.background='var(--pd-surface)';this.style.borderColor='var(--pd-border)'"
                               onmouseout="this.style.background='';this.style.borderColor='transparent'">
                            <input type="checkbox" name="department_ids[]" value="{{ $direction->id }}"
                                   style="accent-color:var(--pd-accent);width:15px;height:15px;"
                                   {{ in_array($direction->id, old('department_ids', $userDeptIds)) ? 'checked' : '' }}>
                            <span style="font-size:13px;color:var(--pd-text);">
                                🏢 <strong>{{ $direction->name }}</strong>
                                <span style="font-size:11px;color:var(--pd-muted);margin-left:4px;">(direction)</span>
                            </span>
                        </label>
                        @foreach($services->where('parent_id', $direction->id) as $service)
                        <label style="display:flex;align-items:center;gap:10px;padding:8px 10px;padding-left:28px;border-radius:8px;cursor:pointer;border:1.5px solid transparent;transition:all 0.15s;"
                               onmouseover="this.style.background='var(--pd-surface)';this.style.borderColor='var(--pd-border)'"
                               onmouseout="this.style.background='';this.style.borderColor='transparent'">
                            <input type="checkbox" name="department_ids[]" value="{{ $service->id }}"
                                   style="accent-color:var(--pd-accent);width:15px;height:15px;"
                                   {{ in_array($service->id, old('department_ids', $userDeptIds)) ? 'checked' : '' }}>
                            <span style="font-size:13px;color:var(--pd-text);">
                                📂 {{ $service->name }}
                                <span style="font-size:11px;color:var(--pd-muted);margin-left:4px;">(service)</span>
                            </span>
                        </label>
                        @endforeach
                    @empty
                        <p style="font-size:12px;color:var(--pd-muted);font-style:italic;padding:8px;">
                            Aucune direction.
                            <a href="{{ route('admin.departments.index') }}" style="color:var(--pd-accent);">Créer les directions</a> d'abord.
                        </p>
                    @endforelse
                </div>
                <div id="newDeptForm" style="display:none;margin-top:12px;padding-top:12px;border-top:1px solid var(--pd-border);">
                    <p style="font-size:12px;font-weight:600;color:var(--pd-text);margin:0 0 8px;">Créer et affecter :</p>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:8px;">
                        <div>
                            <label style="display:block;font-size:12px;color:var(--pd-muted);margin-bottom:4px;">Type</label>
                            <select name="new_department_type" id="newDeptType" onchange="toggleParentSelect()"
                                    style="width:100%;padding:7px 10px;border-radius:7px;border:1.5px solid var(--pd-border);background:var(--pd-surface);color:var(--pd-text);font-size:12px;outline:none;">
                                <option value="direction">Direction</option>
                                <option value="service">Service</option>
                            </select>
                        </div>
                        <div>
                            <label style="display:block;font-size:12px;color:var(--pd-muted);margin-bottom:4px;">Nom</label>
                            <input type="text" name="new_department_name" placeholder="Nom du département"
                                   style="width:100%;box-sizing:border-box;padding:7px 10px;border-radius:7px;border:1.5px solid var(--pd-border);background:var(--pd-surface);color:var(--pd-text);font-size:12px;outline:none;">
                        </div>
                    </div>
                    <div id="parentSelect" style="display:none;">
                        <label style="display:block;font-size:12px;color:var(--pd-muted);margin-bottom:4px;">Direction parente</label>
                        <select name="new_department_parent_id"
                                style="width:100%;padding:7px 10px;border-radius:7px;border:1.5px solid var(--pd-border);background:var(--pd-surface);color:var(--pd-text);font-size:12px;outline:none;margin-bottom:8px;">
                            <option value="">— Choisir —</option>
                            @foreach($directions as $dir)
                                <option value="{{ $dir->id }}">{{ $dir->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            {{-- Section : Mot de passe (local uniquement) --}}
            @if(!$user->ldap_dn)
            <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--pd-muted);margin:0 0 14px;">Changer le mot de passe <span style="font-weight:400;text-transform:none;letter-spacing:0;">(laisser vide pour conserver)</span></p>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px;">
                <div>
                    <label style="display:block;font-size:13px;font-weight:500;color:var(--pd-text);margin-bottom:6px;">Nouveau mot de passe</label>
                    <input type="password" name="password"
                           style="width:100%;box-sizing:border-box;padding:10px 13px;border-radius:9px;border:1.5px solid var(--pd-border);background:var(--pd-bg);color:var(--pd-text);font-family:'DM Sans',sans-serif;font-size:14px;outline:none;"
                           onfocus="this.style.borderColor='var(--pd-accent)'" onblur="this.style.borderColor='var(--pd-border)'">
                </div>
                <div>
                    <label style="display:block;font-size:13px;font-weight:500;color:var(--pd-text);margin-bottom:6px;">Confirmer</label>
                    <input type="password" name="password_confirmation"
                           style="width:100%;box-sizing:border-box;padding:10px 13px;border-radius:9px;border:1.5px solid var(--pd-border);background:var(--pd-bg);color:var(--pd-text);font-family:'DM Sans',sans-serif;font-size:14px;outline:none;"
                           onfocus="this.style.borderColor='var(--pd-accent)'" onblur="this.style.borderColor='var(--pd-border)'">
                </div>
            </div>
            @endif

            {{-- Boutons --}}
            <div style="display:flex;gap:10px;padding-top:4px;border-top:1px solid var(--pd-border);margin-top:4px;">
                <button type="submit"
                        style="padding:10px 24px;border-radius:10px;border:none;cursor:pointer;
                               background:linear-gradient(135deg,var(--pd-navy-dark),var(--pd-navy-light));
                               color:#fff;font-family:'DM Sans',sans-serif;font-size:14px;font-weight:600;
                               transition:opacity 0.2s;margin-top:16px;"
                        onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">
                    Enregistrer
                </button>
                <a href="{{ route('admin.users.index') }}"
                   style="padding:10px 20px;border-radius:10px;border:1.5px solid var(--pd-border);
                          background:var(--pd-bg);color:var(--pd-text);font-size:14px;text-decoration:none;
                          display:inline-flex;align-items:center;margin-top:16px;transition:border-color 0.15s;"
                   onmouseover="this.style.borderColor='var(--pd-accent)'" onmouseout="this.style.borderColor='var(--pd-border)'">
                    Annuler
                </a>
            </div>
        </form>
    </div>
</div>

<script>
function updateDepartmentLabel() {
    const labels = {
        'admin':'Accès global — aucune affectation requise',
        'president':'Accès global — aucune affectation requise',
        'dgs':'Direction(s) sous responsabilité',
        'resp_direction':'Direction(s) gérée(s) (responsable)',
        'resp_service':'Service(s) géré(s) (responsable)',
        'user':'Service(s) / Direction(s) d\'appartenance',
    };
    document.getElementById('deptLabel').textContent = labels[document.getElementById('roleSelect').value] || 'Directions / Services';
}
function toggleNewDept() { const f=document.getElementById('newDeptForm'); f.style.display=f.style.display==='none'?'block':'none'; }
function toggleParentSelect() { document.getElementById('parentSelect').style.display=document.getElementById('newDeptType').value==='service'?'block':'none'; }
updateDepartmentLabel();
</script>

@endsection
