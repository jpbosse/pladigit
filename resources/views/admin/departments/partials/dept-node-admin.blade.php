{{-- Partial récursif — dept-node-admin.blade.php --}}
@php
    $hasChildren = $node->allChildren && $node->allChildren->count() > 0;
    $hasMembers  = $node->members && $node->members->count() > 0;
    $label       = $node->label ?: ($node->parent_id ? 'Service' : 'Direction');
    $color       = $node->color ?: match(true) {
        $depth === 0 => '#1E3A5F',
        $depth === 1 => '#1a5276',
        $depth === 2 => '#2980b9',
        default      => '#5dade2',
    };
    $indent = $depth * 20;
@endphp

<div class="dept-node" style="{{ $depth > 0 ? "margin-left:{$indent}px;" : '' }}margin-bottom:4px;">

    {{-- En-tête --}}
    <div class="dept-header"
         style="display:flex;justify-content:space-between;align-items:center;
                padding:10px 14px;border-radius:10px;cursor:pointer;user-select:none;
                background:{{ $color }};color:#fff;
                transition:opacity 0.15s;"
         onmouseover="this.style.opacity='0.92'" onmouseout="this.style.opacity='1'"
         onclick="(function(el){
             var c=el.nextElementSibling;
             while(c&&!c.classList.contains('dept-members')&&!c.classList.contains('dept-children'))c=c.nextElementSibling;
             var cc=el.parentElement.querySelector('.dept-children');
             if(cc){var open=cc.style.display!=='none';cc.style.display=open?'none':'block';
             var t=el.querySelector('.dept-toggle');if(t)t.textContent=open?'▶':'▼';}
         })(this)">

        <div style="display:flex;align-items:center;gap:10px;min-width:0;">
            @if($hasChildren)
                <span class="dept-toggle" style="font-size:10px;opacity:0.7;flex-shrink:0;">▶</span>
            @else
                <span style="width:10px;flex-shrink:0;"></span>
            @endif
            <span style="font-size:15px;flex-shrink:0;">{{ $depth === 0 ? '🏢' : ($hasChildren ? '📂' : '📌') }}</span>
            <div style="min-width:0;">
                <div style="display:flex;align-items:center;gap:6px;">
                    <span style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;opacity:0.65;">{{ $label }}</span>
                    @if($node->is_transversal)
                        <span style="font-size:10px;background:rgba(255,255,255,0.2);padding:1px 6px;border-radius:10px;">↔ transversal</span>
                    @endif
                </div>
                <p style="font-size:13px;font-weight:600;margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $node->name }}</p>
            </div>
        </div>

        <div style="display:flex;align-items:center;gap:8px;flex-shrink:0;margin-left:12px;" onclick="event.stopPropagation()">
            <span style="font-size:11px;opacity:0.55;">
                👥{{ $node->members->count() }}@if($hasChildren) · {{ $node->allChildren->count() }}↓@endif
            </span>
            <button onclick="openEditModal({{ $node->id }},'{{ addslashes($node->name) }}','{{ addslashes($node->label ?? '') }}','{{ $node->color ?? '' }}',{{ $node->parent_id ?: 'null' }},{{ $node->is_transversal ? 'true' : 'false' }},{{ $node->sort_order ?? 0 }})"
                    style="font-size:12px;padding:3px 8px;border-radius:6px;border:none;cursor:pointer;
                           background:rgba(255,255,255,0.15);color:#fff;transition:background 0.15s;"
                    onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.15)'">
                ✏️
            </button>
            <form method="POST" action="{{ route('admin.departments.destroy', $node) }}" style="margin:0;"
                  onsubmit="return confirm('Supprimer « {{ addslashes($node->name) }} » ?')">
                @csrf @method('DELETE')
                <button type="submit"
                        style="font-size:12px;padding:3px 8px;border-radius:6px;border:none;cursor:pointer;
                               background:rgba(255,255,255,0.15);color:#fff;transition:background 0.15s;"
                        onmouseover="this.style.background='rgba(231,76,60,0.5)'" onmouseout="this.style.background='rgba(255,255,255,0.15)'">
                    🗑
                </button>
            </form>
        </div>
    </div>

    {{-- Membres --}}
    @if($hasMembers)
    <div class="dept-members"
         style="display:flex;flex-wrap:wrap;gap:5px;padding:8px 14px;
                background:var(--pd-surface);border:1.5px solid var(--pd-border);
                border-top:none;border-radius:0 0 8px 8px;margin-top:-4px;">
        @foreach($node->members as $m)
            <span style="font-size:11.5px;padding:2px 10px;border-radius:20px;font-weight:{{ $m->pivot->is_manager ? '700' : '400' }};
                         background:{{ $m->pivot->is_manager ? 'rgba(59,154,225,0.12)' : 'var(--pd-bg)' }};
                         color:{{ $m->pivot->is_manager ? 'var(--pd-accent)' : 'var(--pd-muted)' }};
                         border:1px solid {{ $m->pivot->is_manager ? 'rgba(59,154,225,0.3)' : 'var(--pd-border)' }};">
                {{ $m->name }}@if($m->pivot->is_manager) ★@endif
            </span>
        @endforeach
    </div>
    @endif

    {{-- Enfants (repliés par défaut) --}}
    @if($hasChildren)
    <div class="dept-children" style="display:none;margin-top:4px;display:none;">
        @foreach($node->allChildren->sortBy(['sort_order', 'name']) as $child)
            @include('admin.departments.partials.dept-node-admin', ['node' => $child, 'depth' => $depth + 1, 'allDepts' => $allDepts])
        @endforeach
    </div>
    @endif

</div>
