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
<div class="pd-card" style="margin-bottom:14px;" x-data="{ showModalPhase: false, showModalJalon: false, selectedPhase: null }">

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

</div>

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
