{{--
    _milestone_node.blade.php — Rendu récursif d'un nœud de la hiérarchie projet.

    Variables attendues :
      $ms         ProjectMilestone
      $project    Project
      $canManage  bool
      $depth      int (0 = racine)
      $siblings   Collection  (frères du même niveau pour ↑↓)
      $nodeIndex  int         (position parmi les frères)
--}}
@php
    $pct     = $ms->progressionPercent();
    $msColor = $ms->effectiveColor();
    $reached = $ms->isReached();
    $late    = $ms->isLate();
    $hasChildren = $ms->children->isNotEmpty();
    $pendingChildren = $ms->children->filter(fn($c) => !$c->isReached())->count();
    $nodeLabel = $ms->node_type ?? 'Nœud';
    $indent = $depth * 16; // 16px par niveau
    $sibCount = $siblings->count();
@endphp

<div style="margin-bottom:{{ $hasChildren ? '10px' : '6px' }};border:0.5px solid var(--pd-border);border-radius:8px;overflow:hidden;
            {{ $depth > 0 ? 'margin-left:'.$indent.'px;' : '' }}"
     @if($hasChildren) x-data="{ open: {{ !$reached ? 'true' : 'false' }} }" @endif>

    {{-- ── En-tête du nœud ── --}}
    <div style="display:flex;align-items:center;gap:10px;padding:{{ $depth === 0 ? '10px 12px' : '8px 12px' }};
                background:{{ $reached ? 'var(--pd-surface2)' : ($depth === 0 ? 'var(--pd-bg2)' : 'var(--pd-surface)') }};">

        @if($hasChildren)
        <button type="button" @click="open = !open"
                style="background:none;border:none;padding:0;cursor:pointer;color:var(--pd-muted);display:flex;align-items:center;flex-shrink:0;line-height:1;">
            <svg :style="open ? 'transform:rotate(90deg)' : ''" style="width:12px;height:12px;transition:transform .15s;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <polyline points="9 18 15 12 9 6"/>
            </svg>
        </button>
        @else
        <div style="width:12px;"></div>
        @endif

        <div style="width:{{ $depth === 0 ? '12px' : '8px' }};height:{{ $depth === 0 ? '12px' : '8px' }};border-radius:3px;background:{{ $msColor }};flex-shrink:0;{{ $reached ? 'opacity:.5;' : '' }}"></div>

        <div style="flex:1;min-width:0;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;">
                <div style="min-width:0;{{ $hasChildren ? 'cursor:pointer;' : '' }}" @if($hasChildren) @click="open = !open" @endif>
                    {{-- Badge type --}}
                    @if($ms->node_type)
                    <span style="font-size:9px;font-weight:700;background:{{ $msColor }};color:#fff;padding:1px 5px;border-radius:4px;margin-right:5px;letter-spacing:.04em;opacity:{{ $depth === 0 ? '1' : '.85' }};">{{ strtoupper($ms->node_type) }}</span>
                    @endif
                    <span style="font-size:{{ $depth === 0 ? '12px' : '11px' }};font-weight:{{ $depth === 0 ? '700' : '500' }};color:{{ $reached ? 'var(--pd-muted)' : 'var(--pd-text)' }};{{ $reached ? 'text-decoration:line-through;' : '' }}">{{ $ms->title }}</span>
                    @if($ms->description)
                    <div style="font-size:11px;color:var(--pd-muted);font-weight:400;margin-top:1px;">{{ $ms->description }}</div>
                    @endif
                </div>
                <div style="display:flex;align-items:center;gap:6px;flex-shrink:0;">
                    <span style="font-size:10px;color:{{ $late ? 'var(--pd-danger)' : 'var(--pd-muted)' }};">
                        @if($ms->start_date){{ $ms->start_date->format('d/m') }} → @endif
                        {{ $ms->due_date?->translatedFormat('d M Y') }}
                        @if($reached) ✓ @elseif($late) · En retard @endif
                    </span>
                    <span style="font-size:11px;font-weight:700;color:{{ $late ? 'var(--pd-danger)' : 'var(--pd-navy)' }};">{{ $pct }}%</span>

                    @if($canManage)
                    {{-- ↑↓ --}}
                    @if($nodeIndex > 0)
                    <form method="POST" action="{{ route('projects.milestones.move', [$project, $ms]) }}" style="display:inline;">
                        @csrf @method('PATCH')
                        <input type="hidden" name="direction" value="up">
                        <button type="submit" title="Monter"
                                style="background:none;border:0.5px solid var(--pd-border);border-radius:4px;cursor:pointer;color:var(--pd-muted);font-size:11px;padding:1px 5px;line-height:1;">↑</button>
                    </form>
                    @endif
                    @if($nodeIndex < $sibCount - 1)
                    <form method="POST" action="{{ route('projects.milestones.move', [$project, $ms]) }}" style="display:inline;">
                        @csrf @method('PATCH')
                        <input type="hidden" name="direction" value="down">
                        <button type="submit" title="Descendre"
                                style="background:none;border:0.5px solid var(--pd-border);border-radius:4px;cursor:pointer;color:var(--pd-muted);font-size:11px;padding:1px 5px;line-height:1;">↓</button>
                    </form>
                    @endif

                    {{-- Atteint / Annuler --}}
                    @if($ms->reached_at !== null)
                    <form method="POST" action="{{ route('projects.milestones.update', [$project, $ms]) }}" style="display:inline;" onsubmit="return confirm('Annuler la clôture ?');">
                        @csrf @method('PATCH')
                        <input type="hidden" name="reached" value="0">
                        <button type="submit" style="padding:1px 7px;font-size:10px;background:#FEF3C7;color:#92400E;border:0.5px solid #FCD34D;border-radius:4px;cursor:pointer;">↩ Annuler</button>
                    </form>
                    @elseif($pendingChildren === 0)
                    <form method="POST" action="{{ route('projects.milestones.update', [$project, $ms]) }}" style="display:inline;" onsubmit="return confirm('Marquer comme atteint ?');">
                        @csrf @method('PATCH')
                        <input type="hidden" name="reached" value="1">
                        <button type="submit" style="padding:1px 7px;font-size:10px;background:#F0FDF4;color:#065F46;border:0.5px solid #86EFAC;border-radius:4px;cursor:pointer;font-weight:600;">✓ Atteint</button>
                    </form>
                    @else
                    <button type="button" disabled
                            style="padding:1px 7px;font-size:10px;background:var(--pd-bg2);color:var(--pd-muted);border:0.5px solid var(--pd-border);border-radius:4px;cursor:not-allowed;"
                            title="{{ $pendingChildren }} enfant{{ $pendingChildren > 1 ? 's' : '' }} non atteint{{ $pendingChildren > 1 ? 's' : '' }}">✓ Atteint</button>
                    @endif

                    {{-- Éditer --}}
                    <button @click="openEdit({{ $ms->id }}, '{{ addslashes($ms->node_type ?? '') }}', '{{ addslashes($ms->title) }}', '{{ $ms->due_date?->format('Y-m-d') }}', '{{ $ms->start_date?->format('Y-m-d') }}', '{{ $ms->color }}', '{{ addslashes($ms->description ?? '') }}', '{{ $ms->parent_id ?? '' }}')"
                            style="padding:1px 7px;font-size:10px;background:none;color:var(--pd-muted);border:0.5px solid var(--pd-border);border-radius:4px;cursor:pointer;">✏️</button>

                    {{-- + Enfant (si profondeur < max) --}}
                    @if($depth < \App\Models\Tenant\ProjectMilestone::MAX_DEPTH)
                    <button @click="selectedParent={{ $ms->id }};selectedParentDepth={{ $depth + 1 }};showModalNew=true"
                            style="padding:1px 7px;font-size:10px;font-weight:600;background:none;color:var(--pd-navy);border:0.5px solid var(--pd-navy);border-radius:4px;cursor:pointer;">
                        + Enfant
                    </button>
                    @endif

                    {{-- Supprimer --}}
                    <form method="POST" action="{{ route('projects.milestones.destroy', [$project, $ms]) }}"
                          onsubmit="return confirm('Supprimer {{ addslashes($nodeLabel) }} « {{ addslashes($ms->title) }} »{{ $hasChildren ? ' et tous ses enfants' : '' }} ?');"
                          style="display:inline;">
                        @csrf @method('DELETE')
                        <button type="submit" style="background:none;border:none;cursor:pointer;color:var(--pd-muted);font-size:14px;padding:0 2px;">×</button>
                    </form>
                    @endif
                </div>
            </div>

            {{-- Barre de progression --}}
            @if($pct > 0)
            <div style="height:{{ $depth === 0 ? '4px' : '3px' }};background:var(--pd-border);border-radius:2px;margin-top:6px;overflow:hidden;">
                <div style="height:100%;width:{{ $pct }}%;background:{{ $msColor }};border-radius:2px;transition:width .3s;"></div>
            </div>
            @endif
        </div>
    </div>

    {{-- Commentaire (nœud atteint ou en retard) --}}
    @if(($reached || $late) && ($ms->comment || $canManage))
    <div style="padding:0 12px 8px 40px;border-top:0.5px solid var(--pd-border);" x-data="{ editing: false }">
        @if($ms->comment && !$canManage)
        <div style="font-size:11px;color:var(--pd-muted);font-style:italic;padding:6px 0;">💬 {{ $ms->comment }}</div>
        @elseif($canManage)
        <div x-show="!editing" style="display:flex;align-items:center;gap:6px;cursor:pointer;padding-top:6px;" @click="editing=true">
            @if($ms->comment)
            <div style="font-size:11px;color:var(--pd-muted);font-style:italic;flex:1;">💬 {{ $ms->comment }}</div>
            <span style="font-size:10px;color:var(--pd-muted);">✏️</span>
            @else
            <span style="font-size:10px;color:var(--pd-muted);border:0.5px dashed var(--pd-border);border-radius:4px;padding:2px 7px;">+ Ajouter un commentaire</span>
            @endif
        </div>
        <div x-show="editing" x-cloak>
            <form method="POST" action="{{ route('projects.milestones.update', [$project, $ms]) }}" style="display:flex;gap:6px;align-items:flex-end;margin-top:4px;">
                @csrf @method('PATCH')
                <textarea name="comment" rows="2"
                          style="flex:1;font-size:11px;padding:5px 8px;border:0.5px solid var(--pd-border);border-radius:6px;background:var(--pd-surface);color:var(--pd-text);resize:none;font-family:inherit;"
                          placeholder="Note…">{{ $ms->comment }}</textarea>
                <div style="display:flex;flex-direction:column;gap:4px;">
                    <button type="submit" class="pd-btn pd-btn-primary pd-btn-sm" style="padding:4px 10px;font-size:11px;">✓</button>
                    <button type="button" @click="editing=false" class="pd-btn pd-btn-secondary pd-btn-sm" style="padding:4px 10px;font-size:11px;">✕</button>
                </div>
            </form>
        </div>
        @endif
    </div>
    @endif

    {{-- Enfants (récursif) --}}
    @if($hasChildren)
    <div x-show="open" x-cloak style="border-top:0.5px solid var(--pd-border);padding:8px 10px;">
        @foreach($ms->children as $childIdx => $child)
            @include('projects.partials._milestone_node', [
                'ms'        => $child,
                'project'   => $project,
                'canManage' => $canManage,
                'depth'     => $depth + 1,
                'siblings'  => $ms->children,
                'nodeIndex' => $childIdx,
            ])
        @endforeach
    </div>
    @endif

</div>
