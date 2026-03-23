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

{{-- Phases & Jalons --}}
<div class="pd-card" style="margin-bottom:14px;" x-data="{
    showModalPhase: false,
    showModalJalon: false,
    showModalEdit: false,
    selectedPhase: null,
    editMs: { id: null, title: '', due_date: '', start_date: '', color: '#1E3A5F' },
    openEdit(id, title, due_date, start_date, color) {
        this.editMs = { id, title, due_date: due_date || '', start_date: start_date || '', color: color || '#1E3A5F' };
        this.showModalEdit = true;
        this.$nextTick(() => {
            document.dispatchEvent(new CustomEvent('open-edit-ms', {
                detail: { id, title, due_date, start_date, color }
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
            Phases &amp; Jalons
        </div>
        @if($canManage)
        <div style="display:flex;gap:6px;">
            <button @click="showModalPhase=true"
                    style="padding:3px 10px;font-size:11px;font-weight:600;background:var(--pd-navy);color:#fff;border:none;border-radius:6px;cursor:pointer;">
                + Phase
            </button>
            <button @click="selectedPhase=null;showModalJalon=true"
                    style="padding:3px 10px;font-size:11px;font-weight:600;background:none;color:var(--pd-navy);border:0.5px solid var(--pd-navy);border-radius:6px;cursor:pointer;">
                + Jalon
            </button>
        </div>
        @endif
    </div>

    {{-- Liste phases + jalons --}}
    @php $phaseCount = $project->milestones->whereNull('parent_id')->count(); @endphp
    @forelse($project->milestones as $msIndex => $ms)
    @php
        $pct      = $ms->progressionPercent();
        $msColor  = $ms->color ?? '#94A3B8';
        $isPhase  = $ms->isPhase() && $ms->children->isNotEmpty();
        $reached  = $ms->isReached();
        $late     = $ms->isLate();
        $msPos    = $project->milestones->whereNull('parent_id')->search(fn($m) => $m->id === $ms->id);
    @endphp

    @if($isPhase)
    {{-- ── Phase avec jalons enfants ── --}}
    <div style="margin-bottom:14px;border:0.5px solid var(--pd-border);border-radius:8px;overflow:hidden;">
        {{-- En-tête phase --}}
        <div style="display:flex;align-items:center;gap:10px;padding:10px 12px;background:var(--pd-bg2);">
            <div style="width:12px;height:12px;border-radius:3px;background:{{ $msColor }};flex-shrink:0;"></div>
            <div style="flex:1;">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <span style="font-size:12px;font-weight:700;color:var(--pd-navy);">{{ $ms->title }}</span>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <span style="font-size:10px;color:{{ $late ? 'var(--pd-danger)' : 'var(--pd-muted)' }};">
                            @if($ms->start_date){{ $ms->start_date->format('d/m') }} → @endif
                            {{ $ms->due_date?->translatedFormat('d M Y') }}
                            @if($reached) ✓ @elseif($late) · En retard @endif
                        </span>
                        <span style="font-size:11px;font-weight:700;color:{{ $late ? 'var(--pd-danger)' : 'var(--pd-navy)' }};">{{ $pct }}%</span>
                        @if($canManage)
                        {{-- Boutons ↑↓ réordonnancement --}}
                        @if($msPos > 0)
                        <form method="POST" action="{{ route('projects.milestones.move', [$project, $ms]) }}" style="display:inline;">
                            @csrf @method('PATCH')
                            <input type="hidden" name="direction" value="up">
                            <button type="submit"
                                    title="Monter cette phase"
                                    style="background:none;border:0.5px solid var(--pd-border);border-radius:4px;cursor:pointer;color:var(--pd-muted);font-size:11px;padding:1px 5px;line-height:1;">↑</button>
                        </form>
                        @endif
                        @if($msPos < $phaseCount - 1)
                        <form method="POST" action="{{ route('projects.milestones.move', [$project, $ms]) }}" style="display:inline;">
                            @csrf @method('PATCH')
                            <input type="hidden" name="direction" value="down">
                            <button type="submit"
                                    title="Descendre cette phase"
                                    style="background:none;border:0.5px solid var(--pd-border);border-radius:4px;cursor:pointer;color:var(--pd-muted);font-size:11px;padding:1px 5px;line-height:1;">↓</button>
                        </form>
                        @endif
                        <button @click="openEdit({{ $ms->id }}, '{{ addslashes($ms->title) }}', '{{ $ms->due_date?->format('Y-m-d') }}', '{{ $ms->start_date?->format('Y-m-d') }}', '{{ $ms->color }}')"
                                style="padding:1px 7px;font-size:10px;background:none;color:var(--pd-muted);border:0.5px solid var(--pd-border);border-radius:4px;cursor:pointer;"
                                title="Modifier cette phase">✏️</button>
                        <button @click="selectedPhase={{ $ms->id }};showModalJalon=true"
                                style="padding:1px 7px;font-size:10px;font-weight:600;background:none;color:var(--pd-navy);border:0.5px solid var(--pd-navy);border-radius:4px;cursor:pointer;">
                            + Jalon
                        </button>
                        <form method="POST" action="{{ route('projects.milestones.destroy', [$project, $ms]) }}"
                              onsubmit="return confirm('Supprimer cette phase et ses jalons ?');" style="display:inline;">
                            @csrf @method('DELETE')
                            <button type="submit" style="background:none;border:none;cursor:pointer;color:var(--pd-muted);font-size:14px;padding:0 2px;">×</button>
                        </form>
                        @endif
                    </div>
                </div>
                <div style="height:4px;background:var(--pd-border);border-radius:2px;margin-top:6px;overflow:hidden;">
                    <div style="height:100%;width:{{ $pct }}%;background:{{ $msColor }};border-radius:2px;transition:width .3s;"></div>
                </div>
            </div>
        </div>
        {{-- Jalons enfants --}}
        @foreach($ms->children as $child)
        @php
            $cpct   = $child->progressionPercent();
            $cc     = $child->color ?? $msColor;
            $creach = $child->isReached();
            $clate  = $child->isLate();
        @endphp
        <div style="display:flex;align-items:center;gap:10px;padding:8px 12px 8px 32px;border-top:0.5px solid var(--pd-border);">
            <div style="width:8px;height:8px;border-radius:50%;background:{{ $cc }};flex-shrink:0;
                        {{ $creach ? 'opacity:.4;' : '' }}"></div>
            <div style="flex:1;">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <span style="font-size:12px;font-weight:500;{{ $creach ? 'text-decoration:line-through;color:var(--pd-muted);' : '' }}">
                        🏁 {{ $child->title }}
                    </span>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <span style="font-size:10px;color:{{ $clate ? 'var(--pd-danger)' : 'var(--pd-muted)' }};">
                            {{ $child->due_date?->translatedFormat('d M Y') }}
                            @if($creach) ✓
                            @elseif($clate) · En retard
                            @endif
                        </span>
                        <span style="font-size:11px;color:var(--pd-muted);">{{ $cpct }}%</span>
                        @if($canManage)
                        @if(!$creach)
                        <form method="POST" action="{{ route('projects.milestones.update', [$project, $child]) }}" style="display:inline;" onsubmit="return confirm('Marquer ce jalon comme atteint ?');">
                            @csrf @method('PATCH')
                            <input type="hidden" name="reached" value="1">
                            <button type="submit"
                                    style="padding:1px 7px;font-size:10px;background:#F0FDF4;color:#065F46;border:0.5px solid #86EFAC;border-radius:4px;cursor:pointer;font-weight:600;"
                                    title="Marquer comme atteint">✓ Atteint</button>
                        </form>
                        @else
                        <form method="POST" action="{{ route('projects.milestones.update', [$project, $child]) }}" style="display:inline;" onsubmit="return confirm('Annuler l\'atteinte de ce jalon ?');">
                            @csrf @method('PATCH')
                            <input type="hidden" name="reached" value="0">
                            <button type="submit"
                                    style="padding:1px 7px;font-size:10px;background:#FEF3C7;color:#92400E;border:0.5px solid #FCD34D;border-radius:4px;cursor:pointer;"
                                    title="Annuler l'atteinte">↩ Annuler</button>
                        </form>
                        @endif
                        <button @click="openEdit({{ $child->id }}, '{{ addslashes($child->title) }}', '{{ $child->due_date?->format('Y-m-d') }}', '{{ $child->start_date?->format('Y-m-d') }}', '{{ $child->color }}')"
                                style="padding:1px 6px;font-size:10px;background:none;color:var(--pd-muted);border:0.5px solid var(--pd-border);border-radius:4px;cursor:pointer;"
                                title="Modifier ce jalon">✏️</button>
                        <form method="POST" action="{{ route('projects.milestones.destroy', [$project, $child]) }}"
                              onsubmit="return confirm('Supprimer ce jalon ?');" style="display:inline;">
                            @csrf @method('DELETE')
                            <button type="submit" style="background:none;border:none;cursor:pointer;color:var(--pd-muted);font-size:13px;padding:0 2px;">×</button>
                        </form>
                        @endif
                    </div>
                </div>
                @if($cpct > 0)
                <div style="height:3px;background:var(--pd-border);border-radius:2px;margin-top:4px;overflow:hidden;">
                    <div style="height:100%;width:{{ $cpct }}%;background:{{ $cc }};border-radius:2px;"></div>
                </div>
                @endif
                {{-- Commentaire jalon atteint ou en retard --}}
                @if($creach || $clate)
                <div style="margin-top:6px;" x-data="{ editing: false }">
                    @if($child->comment && !$canManage)
                    <div style="font-size:11px;color:var(--pd-muted);font-style:italic;padding:4px 0;">💬 {{ $child->comment }}</div>
                    @elseif($canManage)
                    <div x-show="!editing" style="display:flex;align-items:center;gap:6px;cursor:pointer;" @click="editing=true">
                        @if($child->comment)
                        <div style="font-size:11px;color:var(--pd-muted);font-style:italic;flex:1;">💬 {{ $child->comment }}</div>
                        <span style="font-size:10px;color:var(--pd-muted);">✏️</span>
                        @else
                        <span style="font-size:10px;color:var(--pd-muted);border:0.5px dashed var(--pd-border);border-radius:4px;padding:2px 7px;">+ Ajouter un commentaire</span>
                        @endif
                    </div>
                    <div x-show="editing" x-cloak>
                        <form method="POST" action="{{ route('projects.milestones.update', [$project, $child]) }}" style="display:flex;gap:6px;align-items:flex-end;margin-top:2px;">
                            @csrf @method('PATCH')
                            <textarea name="comment" rows="2"
                                      style="flex:1;font-size:11px;padding:5px 8px;border:0.5px solid var(--pd-border);border-radius:6px;background:var(--pd-surface);color:var(--pd-text);resize:none;font-family:inherit;"
                                      placeholder="Note sur ce jalon…">{{ $child->comment }}</textarea>
                            <div style="display:flex;flex-direction:column;gap:4px;">
                                <button type="submit" class="pd-btn pd-btn-primary pd-btn-sm" style="padding:4px 10px;font-size:11px;">✓</button>
                                <button type="button" @click="editing=false" class="pd-btn pd-btn-secondary pd-btn-sm" style="padding:4px 10px;font-size:11px;">✕</button>
                            </div>
                        </form>
                    </div>
                    @endif
                </div>
                @endif
            </div>
        </div>
        @endforeach
    </div>

    @else
    {{-- ── Jalon autonome (phase sans enfants) ── --}}
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
        <div style="width:9px;height:9px;border-radius:50%;background:{{ $msColor }};flex-shrink:0;{{ $reached ? 'opacity:.4;' : '' }}"></div>
        <div style="flex:1;">
            <div style="display:flex;justify-content:space-between;font-size:12px;font-weight:500;">
                <span style="{{ $reached ? 'text-decoration:line-through;color:var(--pd-muted);' : '' }}">
                    🏁 {{ $ms->title }}
                </span>
                <div style="display:flex;align-items:center;gap:6px;">
                    <span style="font-size:11px;color:{{ $late ? 'var(--pd-danger)' : 'var(--pd-muted)' }};">
                        {{ $ms->due_date?->translatedFormat('d M Y') }}
                        @if($reached) ✓ @elseif($late) · En retard @endif
                    </span>
                    @if($canManage)
                    @if($msPos > 0)
                    <form method="POST" action="{{ route('projects.milestones.move', [$project, $ms]) }}" style="display:inline;">
                        @csrf @method('PATCH')
                        <input type="hidden" name="direction" value="up">
                        <button type="submit" title="Monter"
                                style="background:none;border:0.5px solid var(--pd-border);border-radius:4px;cursor:pointer;color:var(--pd-muted);font-size:11px;padding:1px 5px;line-height:1;">↑</button>
                    </form>
                    @endif
                    @if($msPos !== false && $msPos < $phaseCount - 1)
                    <form method="POST" action="{{ route('projects.milestones.move', [$project, $ms]) }}" style="display:inline;">
                        @csrf @method('PATCH')
                        <input type="hidden" name="direction" value="down">
                        <button type="submit" title="Descendre"
                                style="background:none;border:0.5px solid var(--pd-border);border-radius:4px;cursor:pointer;color:var(--pd-muted);font-size:11px;padding:1px 5px;line-height:1;">↓</button>
                    </form>
                    @endif
                    @if(!$reached)
                    <form method="POST" action="{{ route('projects.milestones.update', [$project, $ms]) }}" style="display:inline;" onsubmit="return confirm('Marquer ce jalon comme atteint ?');">
                        @csrf @method('PATCH')
                        <input type="hidden" name="reached" value="1">
                        <button type="submit"
                                style="padding:1px 7px;font-size:10px;background:#F0FDF4;color:#065F46;border:0.5px solid #86EFAC;border-radius:4px;cursor:pointer;font-weight:600;"
                                title="Marquer comme atteint">✓ Atteint</button>
                    </form>
                    @else
                    <form method="POST" action="{{ route('projects.milestones.update', [$project, $ms]) }}" style="display:inline;" onsubmit="return confirm('Annuler l\'atteinte de ce jalon ?');">
                        @csrf @method('PATCH')
                        <input type="hidden" name="reached" value="0">
                        <button type="submit"
                                style="padding:1px 7px;font-size:10px;background:#FEF3C7;color:#92400E;border:0.5px solid #FCD34D;border-radius:4px;cursor:pointer;"
                                title="Annuler l'atteinte">↩ Annuler</button>
                    </form>
                    @endif
                    <button @click="openEdit({{ $ms->id }}, '{{ addslashes($ms->title) }}', '{{ $ms->due_date?->format('Y-m-d') }}', '{{ $ms->start_date?->format('Y-m-d') }}', '{{ $ms->color }}')"
                            style="padding:1px 6px;font-size:10px;background:none;color:var(--pd-muted);border:0.5px solid var(--pd-border);border-radius:4px;cursor:pointer;"
                            title="Modifier">✏️</button>
                    <form method="POST" action="{{ route('projects.milestones.destroy', [$project, $ms]) }}"
                          onsubmit="return confirm('Supprimer ce jalon ?');" style="display:inline;">
                        @csrf @method('DELETE')
                        <button type="submit" style="background:none;border:none;cursor:pointer;color:var(--pd-muted);font-size:13px;padding:0 2px;">×</button>
                    </form>
                    @endif
                </div>
            </div>
            <div class="bbar-wrap" style="margin-top:4px;">
                <div class="bbar-fill" style="width:{{ $pct }}%;background:{{ $msColor }};"></div>
            </div>
            {{-- Commentaire jalon atteint ou en retard --}}
            @if($reached || $late)
            <div style="margin-top:6px;" x-data="{ editing: false }">
                @if($ms->comment && !$canManage)
                <div style="font-size:11px;color:var(--pd-muted);font-style:italic;padding:4px 0;">💬 {{ $ms->comment }}</div>
                @elseif($canManage)
                <div x-show="!editing" style="display:flex;align-items:center;gap:6px;cursor:pointer;" @click="editing=true">
                    @if($ms->comment)
                    <div style="font-size:11px;color:var(--pd-muted);font-style:italic;flex:1;">💬 {{ $ms->comment }}</div>
                    <span style="font-size:10px;color:var(--pd-muted);">✏️</span>
                    @else
                    <span style="font-size:10px;color:var(--pd-muted);border:0.5px dashed var(--pd-border);border-radius:4px;padding:2px 7px;">+ Ajouter un commentaire</span>
                    @endif
                </div>
                <div x-show="editing" x-cloak>
                    <form method="POST" action="{{ route('projects.milestones.update', [$project, $ms]) }}" style="display:flex;gap:6px;align-items:flex-end;margin-top:2px;">
                        @csrf @method('PATCH')
                        <textarea name="comment" rows="2"
                                  style="flex:1;font-size:11px;padding:5px 8px;border:0.5px solid var(--pd-border);border-radius:6px;background:var(--pd-surface);color:var(--pd-text);resize:none;font-family:inherit;"
                                  placeholder="Note sur ce jalon…">{{ $ms->comment }}</textarea>
                        <div style="display:flex;flex-direction:column;gap:4px;">
                            <button type="submit" class="pd-btn pd-btn-primary pd-btn-sm" style="padding:4px 10px;font-size:11px;">✓</button>
                            <button type="button" @click="editing=false" class="pd-btn pd-btn-secondary pd-btn-sm" style="padding:4px 10px;font-size:11px;">✕</button>
                        </div>
                    </form>
                </div>
                @endif
            </div>
            @endif
        </div>
        <span style="font-size:11px;color:var(--pd-muted);min-width:28px;text-align:right;">{{ $pct }}%</span>
    </div>
    @endif

    @empty
    @if($canManage)
    <div style="text-align:center;padding:20px;color:var(--pd-muted);font-size:12px;">
        Aucune phase ni jalon — commencez par créer une phase.
    </div>
    @endif
    @endforelse

    {{-- ════════════════════════════════════════ --}}
    {{-- ── Modal : Nouvelle phase ── --}}
    @if($canManage)
    <div x-show="showModalPhase" x-cloak
         style="position:fixed;inset:0;z-index:9000;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.45);backdrop-filter:blur(2px);"
         @click.self="showModalPhase=false">
        <div class="pd-modal pd-modal-md" style="animation:pd-modal-in .18s ease-out;">
            <div class="pd-modal-header" style="background:#1E3A5F;border-radius:14px 14px 0 0;padding:20px 20px 16px;border-bottom:none;display:flex;align-items:flex-start;justify-content:space-between;">
                <div>
                    <div class="pd-modal-title" style="font-size:16px;font-weight:700;color:#fff;line-height:1.3;">Nouvelle phase</div>
                    <div class="pd-modal-subtitle" style="font-size:12px;color:rgba(255,255,255,.75);margin-top:3px;">Définissez une période avec ses jalons</div>
                </div>
                <button type="button" @click="showModalPhase=false" class="pd-modal-close" style="background:none;border:none;cursor:pointer;color:rgba(255,255,255,.8);font-size:22px;line-height:1;padding:0 2px;margin-left:12px;flex-shrink:0;">×</button>
            </div>
            <form method="POST" action="{{ route('projects.phases.store', $project) }}">
                @csrf
                <div class="pd-modal-body">
                    <div class="pd-form-group">
                        <label class="pd-label pd-label-req">Nom de la phase</label>
                        <input type="text" name="title" class="pd-input" placeholder="Phase 1 — Socle technique" required style="width:100%;">
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
                    <button type="button" @click="showModalPhase=false" class="pd-btn pd-btn-secondary pd-btn-sm">Annuler</button>
                    <button type="submit" class="pd-btn pd-btn-primary pd-btn-sm">Créer la phase</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ── Modal : Nouveau jalon ── --}}
    <div x-show="showModalJalon" x-cloak
         style="position:fixed;inset:0;z-index:9000;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.45);backdrop-filter:blur(2px);"
         @click.self="showModalJalon=false">
        <div class="pd-modal pd-modal-md" style="animation:pd-modal-in .18s ease-out;">
            <div class="pd-modal-header" style="background:#EA580C;border-radius:14px 14px 0 0;padding:20px 20px 16px;border-bottom:none;display:flex;align-items:flex-start;justify-content:space-between;">
                <div>
                    <div class="pd-modal-title" style="font-size:16px;font-weight:700;color:#fff;line-height:1.3;">Nouveau jalon</div>
                    <div class="pd-modal-subtitle" style="font-size:12px;color:rgba(255,255,255,.75);margin-top:3px;">Point de contrôle rattaché à une phase</div>
                </div>
                <button type="button" @click="showModalJalon=false" class="pd-modal-close" style="background:none;border:none;cursor:pointer;color:rgba(255,255,255,.8);font-size:22px;line-height:1;padding:0 2px;margin-left:12px;flex-shrink:0;">×</button>
            </div>
            <form method="POST" action="{{ route('projects.milestones.store', $project) }}">
                @csrf
                <input type="hidden" name="parent_id" :value="selectedPhase">
                <div class="pd-modal-body">
                    <div class="pd-form-group">
                        <label class="pd-label pd-label-req">Titre du jalon</label>
                        <input type="text" name="title" class="pd-input" placeholder="Livraison v1.0 — CI vert" required style="width:100%;">
                    </div>
                    @if($project->milestones->where('parent_id', null)->isNotEmpty())
                    <div class="pd-form-group">
                        <label class="pd-label">Rattacher à une phase</label>
                        <select name="parent_id" class="pd-input" style="width:100%;" x-model="selectedPhase">
                            <option value="">— Jalon autonome —</option>
                            @foreach($project->milestones->whereNull('parent_id') as $phase)
                            <option value="{{ $phase->id }}">{{ $phase->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                    <div class="pd-form-group">
                        <label class="pd-label pd-label-req">Date cible</label>
                        <input type="date" name="due_date" class="pd-input" required style="width:100%;">
                    </div>
                    <div class="pd-form-group">
                        <label class="pd-label">Couleur</label>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:4px;">
                            @foreach(['#EA580C','#16A34A','#1E3A5F','#8B5CF6','#0891B2','#DC2626','#D97706'] as $c)
                            <label style="cursor:pointer;">
                                <input type="radio" name="color" value="{{ $c }}" style="display:none;" {{ $c === '#EA580C' ? 'checked' : '' }}>
                                <div style="width:24px;height:24px;border-radius:50%;background:{{ $c }};border:2px solid transparent;transition:border .1s;"
                                     onclick="this.style.border='2px solid #000';"></div>
                            </label>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="pd-modal-footer">
                    <button type="button" @click="showModalJalon=false" class="pd-btn pd-btn-secondary pd-btn-sm">Annuler</button>
                    <button type="submit" class="pd-btn pd-btn-primary pd-btn-sm">Créer le jalon</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    {{-- ── Modal : Modifier phase/jalon ── --}}
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
                    <div class="pd-form-group">
                        <label class="pd-label pd-label-req">Titre</label>
                        <input type="text" name="title" id="edit-ms-title" class="pd-input" required style="width:100%;">
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
    // Patch openEdit pour remplir les champs natifs
    document.addEventListener('open-edit-ms', function (e) {
        const d = e.detail;
        const base = '{{ url('projects/' . $project->id . '/milestones') }}/';
        document.getElementById('form-edit-milestone').action = base + d.id;
        document.getElementById('edit-ms-title').value   = d.title;
        document.getElementById('edit-ms-start').value   = d.start_date || '';
        document.getElementById('edit-ms-due').value     = d.due_date   || '';
        selectMsColor(d.color || '#1E3A5F');
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
