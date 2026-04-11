{{-- _but.blade.php — But & description --}}
@php $members = $project->projectMembers->sortByDesc(fn($m) => $m->role === 'owner'); @endphp

<div class="section-hdr">
    <div>
        <div class="section-title">
            But &amp; description
            @if($project->is_private)
            <span style="display:inline-block;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700;background:#EDE9FE;color:#6D28D9;border:1px solid #C4B5FD;margin-left:8px;vertical-align:middle;cursor:help;"
                  title="Projet privé — visible uniquement par les membres explicitement nommés. La hiérarchie organisationnelle n'a pas accès.">🔒 Privé</span>
            @endif
        </div>
        <div class="section-sub">Informations générales du projet</div>
    </div>
    @if($canManage)
    <a href="{{ route('projects.edit', $project) }}" class="btn-sm">Modifier</a>
    @endif
</div>

<div class="stat-grid">
    <div class="stat-card" style="border-top:3px solid {{ $project->color }};grid-column:span 2;">
        <div class="stat-lbl">Avancement global</div>
        <div style="display:flex;align-items:baseline;gap:10px;">
            <div class="stat-val">{{ $progression }}%</div>
            <div style="font-size:12px;color:var(--pd-muted);">{{ $taskStats['done'] }}/{{ $taskStats['total'] }} tâches</div>
        </div>
        <div class="bbar-wrap" style="margin-top:8px;height:8px;">
            <div class="bbar-fill" style="width:{{ $progression }}%;background:{{ $project->color }};"></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-lbl">Échéance</div>
        <div class="stat-val" style="font-size:16px;">{{ $project->due_date?->translatedFormat('d M Y') ?? '—' }}</div>
        @if($project->due_date)
        <div class="stat-sub" style="color:{{ $project->due_date->isPast() ? 'var(--pd-danger)' : 'var(--pd-muted)' }};">
            {{ $project->due_date->isPast() ? 'Dépassée' : $project->due_date->diffForHumans() }}
        </div>
        @endif
    </div>
    <div class="stat-card">
        <div class="stat-lbl">Équipe</div>
        <div class="stat-val">{{ $members->count() }}</div>
        <div class="stat-sub">membres actifs</div>
    </div>
</div>

{{-- Description --}}
@if($project->description)
<div class="pd-card" style="margin-bottom:14px;">
    <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--pd-muted);margin-bottom:8px;">Objectif</div>
    <div style="font-size:13px;line-height:1.7;color:var(--pd-text);" class="trix-content">
        {!! $project->description !!}
    </div>
</div>
@endif

{{-- Hiérarchie projet (phases, étapes, jalons…) --}}
<div class="pd-card" style="margin-bottom:14px;" x-data="{
    showModalNew: false,
    showModalEdit: false,
    selectedParent: null,
    selectedParentDepth: 0,
    editMs: { id: null, node_type: '', title: '', due_date: '', start_date: '', color: '#1E3A5F', description: '', parent_id: '' },
    openEdit(id, node_type, title, due_date, start_date, color, description, parent_id) {
        this.editMs = { id, node_type: node_type || '', title, due_date: due_date || '', start_date: start_date || '', color: color || '#1E3A5F', description: description || '', parent_id: parent_id || '' };
        this.showModalEdit = true;
        this.$nextTick(() => {
            document.dispatchEvent(new CustomEvent('open-edit-ms', {
                detail: { id, node_type, title, due_date, start_date, color, description, parent_id }
            }));
        });
    },
    init() {
        @if($errors->has('due_date'))
        this.showModalEdit = true;
        @endif
    }
}">

    {{-- En-tête --}}
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
        <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--pd-muted);">
            Planification
        </div>
        @if($canManage)
        <button @click="selectedParent=null;selectedParentDepth=0;showModalNew=true"
                style="padding:3px 10px;font-size:11px;font-weight:600;background:var(--pd-navy);color:#fff;border:none;border-radius:6px;cursor:pointer;">
            + Nouveau
        </button>
        @endif
    </div>

    {{-- Arbre récursif des nœuds racines --}}
    @php $roots = $project->milestones->whereNull('parent_id')->values(); @endphp
    @forelse($roots as $rootIdx => $ms)
        @include('projects.partials._milestone_node', [
            'ms'        => $ms,
            'project'   => $project,
            'canManage' => $canManage,
            'depth'     => 0,
            'siblings'  => $roots,
            'nodeIndex' => $rootIdx,
        ])
    @empty
    @if($canManage)
    <div style="text-align:center;padding:20px;color:var(--pd-muted);font-size:12px;">
        Aucun nœud — commencez par créer une phase ou un jalon.
    </div>
    @endif
    @endforelse

    {{-- ── Modal : Nouveau nœud ── --}}
    @if($canManage)
    <div x-show="showModalNew" x-cloak
         style="position:fixed;inset:0;z-index:9000;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.45);backdrop-filter:blur(2px);"
         @click.self="showModalNew=false">
        <div class="pd-modal pd-modal-md" style="animation:pd-modal-in .18s ease-out;">
            <div class="pd-modal-header" style="background:#1E3A5F;border-radius:14px 14px 0 0;padding:20px 20px 16px;border-bottom:none;display:flex;align-items:flex-start;justify-content:space-between;">
                <div>
                    <div class="pd-modal-title" style="font-size:16px;font-weight:700;color:#fff;line-height:1.3;"
                         x-text="selectedParent ? 'Nouvel enfant' : 'Nouveau nœud racine'"></div>
                    <div class="pd-modal-subtitle" style="font-size:12px;color:rgba(255,255,255,.75);margin-top:3px;"
                         x-text="selectedParent ? 'Niveau ' + (selectedParentDepth + 1) + ' / {{ \App\Models\Tenant\ProjectMilestone::MAX_DEPTH + 1 }}' : 'Niveau 1 / {{ \App\Models\Tenant\ProjectMilestone::MAX_DEPTH + 1 }}'"></div>
                </div>
                <button type="button" @click="showModalNew=false" class="pd-modal-close" style="background:none;border:none;cursor:pointer;color:rgba(255,255,255,.8);font-size:22px;line-height:1;padding:0 2px;margin-left:12px;flex-shrink:0;">×</button>
            </div>
            <form method="POST" action="{{ route('projects.milestones.store', $project) }}">
                @csrf
                <input type="hidden" name="parent_id" :value="selectedParent">
                <div class="pd-modal-body">
                    <div class="pd-form-group">
                        <label class="pd-label pd-label-req">Type</label>
                        <input type="text" name="node_type" id="new-ms-node-type" class="pd-input"
                               list="node-type-suggestions"
                               placeholder="Phase, Étape, Jalon…"
                               style="width:100%;">
                        <datalist id="node-type-suggestions">
                            @php
                                $usedTypes = $project->milestones->pluck('node_type')->filter()->unique()->values();
                                $allTypes  = collect(\App\Models\Tenant\ProjectMilestone::TYPE_SUGGESTIONS)->merge($usedTypes)->unique()->values();
                            @endphp
                            @foreach($allTypes as $suggestion)
                            <option value="{{ $suggestion }}">
                            @endforeach
                        </datalist>
                    </div>
                    <div class="pd-form-group">
                        <label class="pd-label pd-label-req">Titre</label>
                        <input type="text" name="title" class="pd-input" placeholder="Ex : Livraison v1.0 — CI vert" required style="width:100%;">
                    </div>
                    <div class="pd-form-group">
                        <label class="pd-label">Description</label>
                        <textarea name="description" class="pd-input" rows="2" style="width:100%;resize:vertical;" placeholder="Objectif, critère d'atteinte…"></textarea>
                    </div>
                    <div class="pd-form-row-2">
                        <div class="pd-form-group">
                            <label class="pd-label">Début</label>
                            <input type="date" name="start_date" class="pd-input" style="width:100%;">
                        </div>
                        <div class="pd-form-group">
                            <label class="pd-label pd-label-req">Fin prévue</label>
                            <input type="date" name="due_date" class="pd-input" required style="width:100%;">
                        </div>
                    </div>
                    <div class="pd-form-group">
                        <label class="pd-label">Couleur</label>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:4px;">
                            @foreach(['#1E3A5F','#16A34A','#EA580C','#8B5CF6','#0891B2','#DC2626','#D97706'] as $c)
                            <label style="cursor:pointer;">
                                <input type="radio" name="color" value="{{ $c }}" style="display:none;" {{ $c === '#1E3A5F' ? 'checked' : '' }}>
                                <div style="width:24px;height:24px;border-radius:50%;background:{{ $c }};border:2px solid transparent;transition:border .1s;"
                                     onclick="this.style.border='2px solid #000';this.closest('label').querySelector('input').checked=true;"></div>
                            </label>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="pd-modal-footer">
                    <button type="button" @click="showModalNew=false" class="pd-btn pd-btn-secondary pd-btn-sm">Annuler</button>
                    <button type="submit" class="pd-btn pd-btn-primary pd-btn-sm">Créer</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    {{-- ── Modal : Modifier un nœud ── --}}
    @if($canManage)
    <div x-show="showModalEdit" x-cloak
         style="position:fixed;inset:0;z-index:9000;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.45);backdrop-filter:blur(2px);"
         @click.self="showModalEdit=false">
        <div class="pd-modal pd-modal-md" style="animation:pd-modal-in .18s ease-out;">
            <div style="background:#1E3A5F;border-radius:14px 14px 0 0;padding:18px 20px;display:flex;align-items:flex-start;justify-content:space-between;">
                <div>
                    <div style="font-size:15px;font-weight:700;color:#fff;">Modifier</div>
                    <div style="font-size:11px;color:rgba(255,255,255,.7);margin-top:2px;" x-text="editMs.title"></div>
                </div>
                <button @click="showModalEdit=false" style="background:none;border:none;cursor:pointer;color:rgba(255,255,255,.8);font-size:20px;line-height:1;margin-left:12px;">×</button>
            </div>
            <form id="form-edit-milestone" method="POST">
                @csrf
                <input type="hidden" name="_method" value="PATCH">
                <div class="pd-modal-body">
                    @if($errors->has('due_date'))
                    <div style="padding:10px 12px;background:#FEE2E2;color:#991B1B;border-radius:8px;margin-bottom:12px;font-size:12px;line-height:1.5;">
                        ⚠ {{ $errors->first('due_date') }}
                    </div>
                    @endif
                    @php
                        // Liste à plat de tous les nœuds (DFS) avec profondeur pour indentation
                        $flatForSelect = [];
                        $buildSelect = function($nodes, $depth) use (&$buildSelect, &$flatForSelect) {
                            foreach ($nodes as $n) {
                                $flatForSelect[] = ['node' => $n, 'depth' => $depth];
                                if ($n->relationLoaded('children') && $n->children->isNotEmpty()) {
                                    $buildSelect($n->children, $depth + 1);
                                }
                            }
                        };
                        $buildSelect($project->milestones->whereNull('parent_id'), 0);
                    @endphp
                    <div class="pd-form-group">
                        <label class="pd-label">Rattacher sous</label>
                        <select name="parent_id" id="edit-ms-parent" class="pd-input" style="width:100%;">
                            <option value="">— Nœud racine (aucun parent) —</option>
                            @foreach($flatForSelect as $item)
                            <option value="{{ $item['node']->id }}" data-self="{{ $item['node']->id }}">
                                {{ str_repeat('·  ', $item['depth']) }}{{ $item['node']->node_type ? '['.$item['node']->node_type.'] ' : '' }}{{ $item['node']->title }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="pd-form-group">
                        <label class="pd-label">Type</label>
                        <input type="text" name="node_type" id="edit-ms-node-type" class="pd-input"
                               list="node-type-suggestions-edit"
                               placeholder="Phase, Étape, Jalon…"
                               style="width:100%;">
                        <datalist id="node-type-suggestions-edit">
                            @foreach($allTypes as $suggestion)
                            <option value="{{ $suggestion }}">
                            @endforeach
                        </datalist>
                    </div>
                    <div class="pd-form-group">
                        <label class="pd-label pd-label-req">Titre</label>
                        <input type="text" name="title" id="edit-ms-title" class="pd-input" required style="width:100%;">
                    </div>
                    <div class="pd-form-group">
                        <label class="pd-label">Description</label>
                        <textarea name="description" id="edit-ms-description" class="pd-input" rows="2" style="width:100%;resize:vertical;" placeholder="Objectif, critère d'atteinte…"></textarea>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div class="pd-form-group" style="margin-bottom:0;">
                            <label class="pd-label">Début</label>
                            <input type="date" name="start_date" id="edit-ms-start" class="pd-input" style="width:100%;">
                        </div>
                        <div class="pd-form-group" style="margin-bottom:0;">
                            <label class="pd-label pd-label-req">Fin prévue</label>
                            <input type="date" name="due_date" id="edit-ms-due" class="pd-input" required style="width:100%;">
                        </div>
                    </div>
                    <div class="pd-form-group" style="margin-top:14px;margin-bottom:0;">
                        <label class="pd-label">Couleur</label>
                        <input type="hidden" name="color" id="edit-ms-color" value="#1E3A5F">
                        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:10px;" id="edit-ms-colors">
                            @foreach(['#1E3A5F','#16A34A','#EA580C','#8B5CF6','#0891B2','#DC2626','#D97706'] as $c)
                            <div data-color="{{ $c }}"
                                 onclick="selectMsColor('{{ $c }}')"
                                 style="width:38px;height:38px;border-radius:50%;background:{{ $c }};cursor:pointer;box-shadow:0 2px 4px rgba(0,0,0,.3);border:3px solid transparent;transition:all .12s;">
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="pd-modal-footer">
                    <button type="button" @click="showModalEdit=false" class="pd-btn pd-btn-secondary pd-btn-sm">Annuler</button>
                    <button type="submit" class="pd-btn pd-btn-primary pd-btn-sm">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
    @endif

</div>

<script>
function selectMsColor(color) {
    document.getElementById('edit-ms-color').value = color;
    document.querySelectorAll('#edit-ms-colors [data-color]').forEach(function(el) {
        el.style.border = el.dataset.color === color
            ? '3px solid #000'
            : '3px solid transparent';
        el.style.transform = el.dataset.color === color ? 'scale(1.15)' : 'scale(1)';
    });
}

document.addEventListener('alpine:init', function () {
    document.addEventListener('open-edit-ms', function (e) {
        const d = e.detail;
        const base = '{{ url('projects/' . $project->id . '/milestones') }}/';
        document.getElementById('form-edit-milestone').action = base + d.id;
        document.getElementById('edit-ms-node-type').value   = d.node_type  || '';
        document.getElementById('edit-ms-title').value       = d.title;
        document.getElementById('edit-ms-description').value = d.description || '';
        document.getElementById('edit-ms-start').value       = d.start_date || '';
        document.getElementById('edit-ms-due').value         = d.due_date   || '';
        selectMsColor(d.color || '#1E3A5F');
        // Parent : sélectionner la bonne option, exclure le nœud lui-même
        const parentSel = document.getElementById('edit-ms-parent');
        if (parentSel) {
            Array.from(parentSel.options).forEach(opt => {
                opt.disabled = (opt.dataset.self == d.id);
            });
            parentSel.value = d.parent_id || '';
        }
    });
});
</script>

{{-- Membres --}}
<div class="pd-card">
    <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--pd-muted);margin-bottom:10px;">Équipe</div>
    @foreach($members as $pm)
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
        <div class="sh-avatar" style="background:var(--pd-bg2);color:var(--pd-navy);">{{ strtoupper(substr($pm->user->name,0,2)) }}</div>
        <div style="flex:1;"><div style="font-size:12px;font-weight:500;">{{ $pm->user->name }}</div><div style="font-size:11px;color:var(--pd-muted);">{{ \App\Enums\ProjectRole::tryFrom($pm->role)?->label() ?? $pm->role }}</div></div>
    </div>
    @endforeach
</div>
