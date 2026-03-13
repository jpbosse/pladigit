@extends('layouts.admin')
@section('title', 'Nouvel utilisateur')

@section('admin-content')

<div style="max-width:720px;">

    {{-- En-tête --}}
    <div style="margin-bottom:24px;">
        <h1 style="font-family:'Sora',sans-serif;font-size:20px;font-weight:700;color:var(--pd-text);margin:0 0 4px;">
            Nouvel utilisateur
        </h1>
        <p style="font-size:13px;color:var(--pd-muted);margin:0;">
            Un email d'invitation sera envoyé automatiquement — lien valable 72h.
        </p>
    </div>

    {{-- Erreurs --}}
    @if($errors->any())
    <div style="background:rgba(231,76,60,0.08);border:1.5px solid rgba(231,76,60,0.25);border-radius:10px;padding:11px 16px;margin-bottom:20px;font-size:13px;color:#c0392b;">
        {{ $errors->first() }}
    </div>
    @endif

    <div style="background:var(--pd-surface);border:1.5px solid var(--pd-border);border-radius:14px;padding:28px;box-shadow:var(--pd-shadow);">
        <form method="POST" action="{{ route('admin.users.store') }}">
            @csrf

            {{-- Identité --}}
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;">
                <div>
                    <label style="display:block;font-size:13px;font-weight:500;color:var(--pd-text);margin-bottom:6px;">Nom complet</label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                           style="width:100%;box-sizing:border-box;padding:10px 13px;border-radius:9px;border:1.5px solid var(--pd-border);background:var(--pd-bg);color:var(--pd-text);font-family:'DM Sans',sans-serif;font-size:14px;outline:none;"
                           onfocus="this.style.borderColor='var(--pd-accent)'" onblur="this.style.borderColor='var(--pd-border)'">
                </div>
                <div>
                    <label style="display:block;font-size:13px;font-weight:500;color:var(--pd-text);margin-bottom:6px;">Adresse e-mail</label>
                    <input type="email" name="email" value="{{ old('email') }}" required
                           style="width:100%;box-sizing:border-box;padding:10px 13px;border-radius:9px;border:1.5px solid var(--pd-border);background:var(--pd-bg);color:var(--pd-text);font-family:'DM Sans',sans-serif;font-size:14px;outline:none;"
                           onfocus="this.style.borderColor='var(--pd-accent)'" onblur="this.style.borderColor='var(--pd-border)'">
                </div>
            </div>

            {{-- Rôle --}}
            <div style="margin-bottom:20px;">
                <label style="display:block;font-size:13px;font-weight:500;color:var(--pd-text);margin-bottom:6px;">Rôle</label>
                <select name="role" id="roleSelect" onchange="updateDepartmentLabel()"
                        style="width:100%;padding:10px 13px;border-radius:9px;border:1.5px solid var(--pd-border);background:var(--pd-bg);color:var(--pd-text);font-family:'DM Sans',sans-serif;font-size:14px;outline:none;cursor:pointer;"
                        onfocus="this.style.borderColor='var(--pd-accent)'" onblur="this.style.borderColor='var(--pd-border)'">
                    @foreach(App\Enums\UserRole::cases() as $role)
                        <option value="{{ $role->value }}" {{ old('role') === $role->value ? 'selected' : '' }}>
                            {{ $role->label() }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Directions & Services --}}
            <div style="margin-bottom:20px;border:1.5px solid var(--pd-border);border-radius:12px;padding:16px;background:var(--pd-bg);">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                    <label style="font-size:13px;font-weight:500;color:var(--pd-text);" id="deptLabel">
                        Directions / Services
                    </label>
                    <button type="button" onclick="toggleNewDept()"
                            style="font-size:12px;color:var(--pd-accent);background:none;border:none;cursor:pointer;font-family:inherit;padding:0;">
                        + Créer un nouveau
                    </button>
                </div>
                <div style="max-height:200px;overflow-y:auto;display:flex;flex-direction:column;gap:4px;">
                    @forelse($directions as $direction)
                        <label style="display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:8px;cursor:pointer;border:1.5px solid transparent;transition:all 0.15s;"
                               onmouseover="this.style.background='var(--pd-surface)';this.style.borderColor='var(--pd-border)'"
                               onmouseout="this.style.background='';this.style.borderColor='transparent'">
                            <input type="checkbox" name="department_ids[]" value="{{ $direction->id }}"
                                   style="accent-color:var(--pd-accent);width:15px;height:15px;"
                                   {{ in_array($direction->id, old('department_ids', [])) ? 'checked' : '' }}>
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
                                   {{ in_array($service->id, old('department_ids', [])) ? 'checked' : '' }}>
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

                {{-- Formulaire inline création département --}}
                <div id="newDeptForm" style="display:none;margin-top:12px;padding-top:12px;border-top:1px solid var(--pd-border);">
                    <p style="font-size:12px;font-weight:600;color:var(--pd-text);margin:0 0 8px;">Créer et affecter un nouveau département :</p>
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

            {{-- Info invitation --}}
            <div style="background:rgba(59,154,225,0.08);border:1.5px solid rgba(59,154,225,0.2);border-radius:10px;padding:12px 16px;margin-bottom:24px;font-size:13px;color:var(--pd-accent);display:flex;align-items:center;gap:10px;">
                <svg style="width:16px;height:16px;flex-shrink:0;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                Un email d'invitation sera envoyé. L'utilisateur choisira son mot de passe (lien valable 72h).
            </div>

            {{-- Boutons --}}
            <div style="display:flex;gap:10px;">
                <button type="submit"
                        style="padding:10px 24px;border-radius:10px;border:none;cursor:pointer;
                               background:linear-gradient(135deg,var(--pd-navy-dark),var(--pd-navy-light));
                               color:#fff;font-family:'DM Sans',sans-serif;font-size:14px;font-weight:600;
                               transition:opacity 0.2s;"
                        onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">
                    Créer l'utilisateur
                </button>
                <a href="{{ route('admin.users.index') }}"
                   style="padding:10px 20px;border-radius:10px;border:1.5px solid var(--pd-border);
                          background:var(--pd-bg);color:var(--pd-text);font-size:14px;text-decoration:none;
                          display:inline-flex;align-items:center;transition:border-color 0.15s;"
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
        'dgs':'Accès global — aucune affectation requise',
        'resp_direction':'Directions gérées (responsable)',
        'resp_service':'Services gérés (responsable)',
        'user':'Services / Directions d\'appartenance',
    };
    document.getElementById('deptLabel').textContent = labels[document.getElementById('roleSelect').value] || 'Directions / Services';
}
function toggleNewDept() { const f=document.getElementById('newDeptForm'); f.style.display=f.style.display==='none'?'block':'none'; }
function toggleParentSelect() { document.getElementById('parentSelect').style.display=document.getElementById('newDeptType').value==='service'?'block':'none'; }
updateDepartmentLabel();
</script>

@endsection
