@extends('layouts.admin')
@section('title', 'Hiérarchie organisationnelle')

@section('admin-content')

{{-- En-tête --}}
<div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:24px;">
    <div>
        <h1 style="font-family:'Sora',sans-serif;font-size:20px;font-weight:700;color:var(--pd-text);margin:0 0 4px;">
            Hiérarchie organisationnelle
        </h1>
        <p style="font-size:13px;color:var(--pd-muted);margin:0;">Structure libre — Directions, Services, Pôles, Bureaux…</p>
    </div>
    <a href="{{ route('admin.departments.organigramme') }}" target="_blank"
       style="display:inline-flex;align-items:center;gap:7px;padding:9px 18px;border-radius:10px;
              border:1.5px solid var(--pd-border);background:var(--pd-surface);
              color:var(--pd-text);font-size:13px;font-weight:600;text-decoration:none;transition:border-color 0.15s;"
       onmouseover="this.style.borderColor='var(--pd-accent)'" onmouseout="this.style.borderColor='var(--pd-border)'">
        <svg style="width:14px;height:14px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;" viewBox="0 0 24 24"><rect x="3" y="3" width="6" height="6" rx="1"/><rect x="15" y="3" width="6" height="6" rx="1"/><rect x="9" y="15" width="6" height="6" rx="1"/><path d="M6 9v3a3 3 0 0 0 3 3h6a3 3 0 0 0 3-3V9"/><line x1="12" y1="12" x2="12" y2="15"/></svg>
        Organigramme
    </a>
</div>

{{-- Compteurs --}}
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:24px;">
    @foreach([['roots','Entité(s) racine','🏢','59,154,225'],['total','Total entités','📂','118,99,197'],['members','Membre(s) affectés','👥','232,168,56']] as [$key,$label,$icon,$rgb])
    <div style="background:var(--pd-surface);border:1.5px solid var(--pd-border);border-radius:14px;padding:16px 20px;display:flex;align-items:center;gap:14px;box-shadow:var(--pd-shadow);">
        <div style="width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;background:rgba({{ $rgb }},0.1);flex-shrink:0;">
            {{ $icon }}
        </div>
        <div>
            <p style="font-family:'Sora',sans-serif;font-size:22px;font-weight:700;color:var(--pd-text);margin:0 0 1px;">{{ $stats[$key] }}</p>
            <p style="font-size:12px;color:var(--pd-muted);margin:0;">{{ $label }}</p>
        </div>
    </div>
    @endforeach
</div>

{{-- Flash --}}
@if(session('success'))
<div style="background:rgba(46,204,113,0.08);border:1.5px solid rgba(46,204,113,0.3);border-radius:10px;padding:11px 16px;margin-bottom:20px;font-size:13px;color:#1a8a4a;display:flex;align-items:center;gap:8px;">
    <svg style="width:15px;height:15px;flex-shrink:0;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
    {{ session('success') }}
</div>
@endif
@if($errors->any())
<div style="background:rgba(231,76,60,0.08);border:1.5px solid rgba(231,76,60,0.25);border-radius:10px;padding:11px 16px;margin-bottom:20px;font-size:13px;color:#c0392b;display:flex;align-items:center;gap:8px;">
    <svg style="width:15px;height:15px;flex-shrink:0;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    {{ $errors->first() }}
</div>
@endif

{{-- Contenu principal --}}
<div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start;">

    {{-- Arborescence --}}
    <div>
        @if($roots->count())
        <div style="display:flex;justify-content:flex-end;margin-bottom:10px;">
            <button onclick="toggleAll()" id="toggleAllBtn"
                    style="font-size:12px;padding:6px 14px;border-radius:8px;border:1.5px solid var(--pd-border);
                           background:var(--pd-surface);color:var(--pd-muted);cursor:pointer;font-family:inherit;transition:border-color 0.15s;"
                    onmouseover="this.style.borderColor='var(--pd-accent)'" onmouseout="this.style.borderColor='var(--pd-border)'">
                ▼ Tout déplier
            </button>
        </div>
        @endif

        <div style="display:flex;flex-direction:column;gap:4px;">
            @forelse($roots as $root)
                @include('admin.departments.partials.dept-node-admin', ['node' => $root, 'depth' => 0, 'allDepts' => $allDepts])
            @empty
            <div style="background:var(--pd-surface);border:1.5px solid var(--pd-border);border-radius:14px;padding:48px 24px;text-align:center;color:var(--pd-muted);">
                <div style="font-size:36px;margin-bottom:12px;">🏢</div>
                <p style="font-size:14px;font-weight:500;color:var(--pd-text);margin:0 0 4px;">Aucune entité créée</p>
                <p style="font-size:12px;margin:0;">Commencez par créer une entité dans le formulaire.</p>
            </div>
            @endforelse
        </div>
    </div>

    {{-- Formulaire création --}}
    <div style="position:sticky;top:calc(var(--pd-topbar-h) + 16px);display:flex;flex-direction:column;gap:14px;">
        <div style="background:var(--pd-surface);border:1.5px solid var(--pd-border);border-radius:14px;padding:22px;box-shadow:var(--pd-shadow);">
            <p style="font-family:'Sora',sans-serif;font-size:14px;font-weight:700;color:var(--pd-text);margin:0 0 18px;padding-bottom:12px;border-bottom:1px solid var(--pd-border);">
                Nouvelle entité
            </p>
            <form method="POST" action="{{ route('admin.departments.store') }}" style="display:flex;flex-direction:column;gap:14px;">
                @csrf

                <div>
                    <label style="display:block;font-size:12px;font-weight:500;color:var(--pd-text);margin-bottom:5px;">Nom <span style="color:var(--pd-danger);">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}" placeholder="Ex : Direction des Finances" required
                           style="width:100%;box-sizing:border-box;padding:9px 12px;border-radius:9px;border:1.5px solid var(--pd-border);background:var(--pd-bg);color:var(--pd-text);font-family:'DM Sans',sans-serif;font-size:13px;outline:none;"
                           onfocus="this.style.borderColor='var(--pd-accent)'" onblur="this.style.borderColor='var(--pd-border)'">
                </div>

                <div>
                    <label style="display:block;font-size:12px;font-weight:500;color:var(--pd-text);margin-bottom:5px;">Type / Label</label>
                    <input type="text" name="label" id="labelInput" value="{{ old('label') }}"
                           placeholder="Direction, Pôle, Bureau…" maxlength="100" list="label-suggestions" autocomplete="off"
                           style="width:100%;box-sizing:border-box;padding:9px 12px;border-radius:9px;border:1.5px solid var(--pd-border);background:var(--pd-bg);color:var(--pd-text);font-family:'DM Sans',sans-serif;font-size:13px;outline:none;"
                           onfocus="this.style.borderColor='var(--pd-accent)'" onblur="this.style.borderColor='var(--pd-border)'">
                    <datalist id="label-suggestions">
                        @foreach($labelSuggestions as $s)<option value="{{ $s }}">@endforeach
                    </datalist>
                    <p style="font-size:11px;color:var(--pd-muted);margin:4px 0 0;">Affiché dans l'organigramme.</p>
                </div>

                <div>
                    <label style="display:block;font-size:12px;font-weight:500;color:var(--pd-text);margin-bottom:5px;">Couleur</label>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <input type="color" name="color" value="{{ old('color', '#1E3A5F') }}"
                               style="width:40px;height:36px;border-radius:8px;border:1.5px solid var(--pd-border);cursor:pointer;padding:2px;">
                        <span style="font-size:11px;color:var(--pd-muted);">Nœud d'organigramme</span>
                    </div>
                </div>

                <div>
                    <label style="display:block;font-size:12px;font-weight:500;color:var(--pd-text);margin-bottom:5px;">Rattaché à</label>
                    <select name="parent_id"
                            style="width:100%;padding:9px 12px;border-radius:9px;border:1.5px solid var(--pd-border);background:var(--pd-bg);color:var(--pd-text);font-family:'DM Sans',sans-serif;font-size:13px;outline:none;cursor:pointer;"
                            onfocus="this.style.borderColor='var(--pd-accent)'" onblur="this.style.borderColor='var(--pd-border)'">
                        <option value="">— Aucun (entité racine) —</option>
                        @foreach($allDepts as $dept)
                            <option value="{{ $dept->id }}" {{ old('parent_id') == $dept->id ? 'selected' : '' }}>
                                {{ str_repeat('  ', $dept->depth ?? 0) }}{{ $dept->label ? '[' . $dept->label . '] ' : '' }}{{ $dept->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="hidden" name="is_transversal" value="0">
                    <input type="checkbox" name="is_transversal" id="isTransversal" value="1"
                           {{ old('is_transversal') ? 'checked' : '' }}
                           style="accent-color:var(--pd-accent);width:15px;height:15px;">
                    <span style="font-size:12px;color:var(--pd-text);">Entité transversale
                        <span style="color:var(--pd-muted);">(hors hiérarchie stricte)</span>
                    </span>
                </label>

                <div>
                    <label style="display:block;font-size:12px;font-weight:500;color:var(--pd-text);margin-bottom:5px;">Ordre d'affichage</label>
                    <input type="number" name="sort_order" value="{{ old('sort_order', 0) }}" min="0" max="999"
                           style="width:100%;box-sizing:border-box;padding:9px 12px;border-radius:9px;border:1.5px solid var(--pd-border);background:var(--pd-bg);color:var(--pd-text);font-family:'DM Sans',sans-serif;font-size:13px;outline:none;"
                           onfocus="this.style.borderColor='var(--pd-accent)'" onblur="this.style.borderColor='var(--pd-border)'">
                </div>

                <button type="submit"
                        style="width:100%;padding:10px;border-radius:10px;border:none;cursor:pointer;
                               background:linear-gradient(135deg,var(--pd-navy-dark),var(--pd-navy-light));
                               color:#fff;font-family:'DM Sans',sans-serif;font-size:14px;font-weight:600;
                               transition:opacity 0.2s;"
                        onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">
                    Créer l'entité
                </button>
            </form>
        </div>

        <div style="background:rgba(59,154,225,0.05);border:1.5px solid rgba(59,154,225,0.15);border-radius:12px;padding:14px 16px;font-size:12px;color:var(--pd-muted);line-height:1.7;">
            <p style="font-weight:600;color:var(--pd-text);margin:0 0 6px;">ℹ Liberté de structure</p>
            <p style="margin:0;">• Hiérarchie libre : Pôle → Direction → Service → Bureau</p>
            <p style="margin:0;">• Aucune restriction de type ou de profondeur</p>
            <p style="margin:0;">• Impossible de supprimer un nœud avec des membres</p>
        </div>
    </div>

</div>

{{-- Modal modification --}}
<div id="editModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);display:none;align-items:center;justify-content:center;z-index:500;">
    <div style="background:var(--pd-surface);border-radius:16px;width:100%;max-width:460px;margin:16px;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
        <div style="display:flex;justify-content:space-between;align-items:center;padding:18px 22px;border-bottom:1px solid var(--pd-border);">
            <h3 style="font-family:'Sora',sans-serif;font-size:15px;font-weight:700;color:var(--pd-text);margin:0;">Modifier l'entité</h3>
            <button onclick="closeEditModal()"
                    style="background:none;border:none;cursor:pointer;color:var(--pd-muted);font-size:22px;line-height:1;padding:0;">×</button>
        </div>
        <form method="POST" id="editForm" style="padding:22px;display:flex;flex-direction:column;gap:14px;">
            @csrf @method('PUT')

            <div>
                <label style="display:block;font-size:12px;font-weight:500;color:var(--pd-text);margin-bottom:5px;">Nom *</label>
                <input type="text" name="name" id="editName" required
                       style="width:100%;box-sizing:border-box;padding:9px 12px;border-radius:9px;border:1.5px solid var(--pd-border);background:var(--pd-bg);color:var(--pd-text);font-family:'DM Sans',sans-serif;font-size:13px;outline:none;"
                       onfocus="this.style.borderColor='var(--pd-accent)'" onblur="this.style.borderColor='var(--pd-border)'">
            </div>

            <div>
                <label style="display:block;font-size:12px;font-weight:500;color:var(--pd-text);margin-bottom:5px;">Type / Label</label>
                <input type="text" name="label" id="editLabel" maxlength="100" placeholder="Direction, Pôle…" list="label-suggestions" autocomplete="off"
                       style="width:100%;box-sizing:border-box;padding:9px 12px;border-radius:9px;border:1.5px solid var(--pd-border);background:var(--pd-bg);color:var(--pd-text);font-family:'DM Sans',sans-serif;font-size:13px;outline:none;"
                       onfocus="this.style.borderColor='var(--pd-accent)'" onblur="this.style.borderColor='var(--pd-border)'">
            </div>

            <div>
                <label style="display:block;font-size:12px;font-weight:500;color:var(--pd-text);margin-bottom:5px;">Couleur</label>
                <div style="display:flex;align-items:center;gap:10px;">
                    <input type="color" name="color" id="editColor"
                           style="width:40px;height:36px;border-radius:8px;border:1.5px solid var(--pd-border);cursor:pointer;padding:2px;">
                    <button type="button" onclick="document.getElementById('editColor').value='#1E3A5F'"
                            style="font-size:11px;color:var(--pd-muted);background:none;border:none;cursor:pointer;text-decoration:underline;font-family:inherit;">
                        Réinitialiser
                    </button>
                </div>
            </div>

            <div>
                <label style="display:block;font-size:12px;font-weight:500;color:var(--pd-text);margin-bottom:5px;">Rattaché à</label>
                <select name="parent_id" id="editParentId"
                        style="width:100%;padding:9px 12px;border-radius:9px;border:1.5px solid var(--pd-border);background:var(--pd-bg);color:var(--pd-text);font-family:'DM Sans',sans-serif;font-size:13px;outline:none;">
                    <option value="">— Aucun (entité racine) —</option>
                    @foreach($allDepts as $dept)
                        <option value="{{ $dept->id }}">{{ $dept->label ? '[' . $dept->label . '] ' : '' }}{{ $dept->name }}</option>
                    @endforeach
                </select>
            </div>

            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                <input type="hidden" name="is_transversal" value="0">
                <input type="checkbox" name="is_transversal" id="editTransversal" value="1"
                       style="accent-color:var(--pd-accent);width:15px;height:15px;">
                <span style="font-size:12px;color:var(--pd-text);">Entité transversale</span>
            </label>

            <div>
                <label style="display:block;font-size:12px;font-weight:500;color:var(--pd-text);margin-bottom:5px;">Ordre</label>
                <input type="number" name="sort_order" id="editSortOrder" min="0" max="999"
                       style="width:100%;box-sizing:border-box;padding:9px 12px;border-radius:9px;border:1.5px solid var(--pd-border);background:var(--pd-bg);color:var(--pd-text);font-family:'DM Sans',sans-serif;font-size:13px;outline:none;"
                       onfocus="this.style.borderColor='var(--pd-accent)'" onblur="this.style.borderColor='var(--pd-border)'">
            </div>

            <div style="display:flex;gap:10px;padding-top:4px;border-top:1px solid var(--pd-border);margin-top:4px;">
                <button type="submit"
                        style="flex:1;padding:10px;border-radius:10px;border:none;cursor:pointer;
                               background:linear-gradient(135deg,var(--pd-navy-dark),var(--pd-navy-light));
                               color:#fff;font-family:'DM Sans',sans-serif;font-size:13px;font-weight:600;margin-top:10px;"
                        onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">
                    Enregistrer
                </button>
                <button type="button" onclick="closeEditModal()"
                        style="flex:1;padding:10px;border-radius:10px;border:1.5px solid var(--pd-border);cursor:pointer;
                               background:var(--pd-bg);color:var(--pd-text);font-family:'DM Sans',sans-serif;font-size:13px;margin-top:10px;transition:border-color 0.15s;"
                        onmouseover="this.style.borderColor='var(--pd-accent)'" onmouseout="this.style.borderColor='var(--pd-border)'">
                    Annuler
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(id, name, label, color, parentId, isTransversal, sortOrder) {
    document.getElementById('editForm').action = '/admin/departments/' + id;
    document.getElementById('editName').value       = name || '';
    document.getElementById('editLabel').value      = label || '';
    document.getElementById('editColor').value      = color || '#1E3A5F';
    document.getElementById('editSortOrder').value  = sortOrder || 0;
    document.getElementById('editTransversal').checked = !!isTransversal;
    document.getElementById('editParentId').value   = parentId || '';
    const modal = document.getElementById('editModal');
    modal.style.display = 'flex';
    setTimeout(() => document.getElementById('editName').focus(), 50);
}
function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});
let allExpanded = false;
function toggleAll() {
    allExpanded = !allExpanded;
    document.querySelectorAll('.dept-children').forEach(el => el.style.display = allExpanded ? 'block' : 'none');
    document.querySelectorAll('.dept-toggle').forEach(el => el.textContent = allExpanded ? '▼' : '▶');
    document.getElementById('toggleAllBtn').textContent = allExpanded ? '▲ Tout replier' : '▼ Tout déplier';
}
function toggleNode(btn) {
    const children = btn.closest('.dept-header').nextElementSibling;
    if (!children || !children.classList.contains('dept-children')) return;
    const isOpen = children.style.display !== 'none';
    children.style.display = isOpen ? 'none' : 'block';
    btn.textContent = isOpen ? '▶' : '▼';
}
</script>

@endsection
