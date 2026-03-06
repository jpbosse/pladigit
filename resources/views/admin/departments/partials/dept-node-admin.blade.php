{{--
    Partial récursif — admin/departments/partials/dept-node-admin.blade.php
    Accordéon : enfants repliés par défaut, toggle au clic.
--}}
@php
    $hasChildren = $node->allChildren && $node->allChildren->count() > 0;
    $hasMembers  = $node->members && $node->members->count() > 0;
    $label       = $node->label ?: ($node->parent_id ? 'Service' : 'Direction');
    $color       = $node->color ?: null;
    $indent      = $depth * 1.0;

    $defaultBg = match(true) {
        $color !== null => $color,
        $depth === 0    => '#1E3A5F',
        $depth === 1    => '#7C3AED',
        $depth === 2    => '#0E7490',
        default         => '#374151',
    };
@endphp

<div class="dept-node" style="{{ $depth > 0 ? 'margin-left:' . $indent . 'rem;' : '' }} margin-bottom:3px;">

    {{-- En-tête cliquable --}}
    <div class="dept-header flex justify-between items-center px-3 py-2 rounded-lg cursor-pointer select-none"
         style="background:{{ $defaultBg }}; color:#fff;"
         onclick="(function(el){ var c = el.nextElementSibling; while(c && !c.classList.contains('dept-children')) c = c.nextElementSibling; if(c){ var open = c.style.display!=='none'; c.style.display=open?'none':'block'; var t=el.querySelector('.dept-toggle'); if(t) t.textContent=open?'▶':'▼'; } })(this)">

        <div class="flex items-center gap-2 min-w-0">
            @if($hasChildren)
                <span class="dept-toggle text-xs opacity-70 flex-shrink-0">▶</span>
            @else
                <span class="w-3 flex-shrink-0 text-xs opacity-0">▶</span>
            @endif
            <span class="text-sm flex-shrink-0">{{ $depth === 0 ? '🏢' : ($hasChildren ? '📂' : '📌') }}</span>
            <div class="min-w-0">
                <div class="flex items-center gap-1.5">
                    <span class="text-xs font-bold uppercase tracking-wide opacity-60">{{ $label }}</span>
                    @if($node->is_transversal)
                        <span class="text-xs bg-white/20 px-1.5 rounded">↔ transversal</span>
                    @endif
                </div>
                <p class="text-sm font-semibold leading-tight truncate">{{ $node->name }}</p>
            </div>
        </div>

        <div class="flex items-center gap-1.5 flex-shrink-0 ml-2" onclick="event.stopPropagation()">
            <span class="text-xs opacity-50">
                👥{{ $node->members->count() }}@if($hasChildren) · {{$node->allChildren->count()}}↓@endif
            </span>
            <button onclick="openEditModal({{ $node->id }},'{{ addslashes($node->name) }}','{{ addslashes($node->label ?? '') }}','{{ $node->color ?? '' }}',{{ $node->parent_id ?: 'null' }},{{ $node->is_transversal ? 'true' : 'false' }},{{ $node->sort_order ?? 0 }})"
                    class="text-xs px-2 py-0.5 rounded bg-white/15 hover:bg-white/30 transition">✏️</button>
            <form method="POST" action="{{ route('admin.departments.destroy', $node) }}"
                  onsubmit="return confirm('Supprimer « {{ addslashes($node->name) }} » ?')">
                @csrf @method('DELETE')
                <button class="text-xs px-2 py-0.5 rounded bg-white/15 hover:bg-red-500/70 transition">🗑</button>
            </form>
        </div>
    </div>

    {{-- Membres compacts --}}
    @if($hasMembers)
    <div class="flex flex-wrap gap-1 px-3 py-1 bg-white border border-t-0 border-gray-100 rounded-b-lg">
        @foreach($node->members as $m)
            <span class="text-xs px-2 py-0.5 rounded-full {{ $m->pivot->is_manager ? 'bg-blue-100 text-blue-700 font-semibold' : 'bg-gray-100 text-gray-600' }}">
                {{ $m->name }}@if($m->pivot->is_manager) ★@endif
            </span>
        @endforeach
    </div>
    @endif

    {{-- Enfants repliés par défaut --}}
    @if($hasChildren)
    <div class="dept-children mt-0.5" style="display:none;">
        @foreach($node->allChildren->sortBy(['sort_order', 'name']) as $child)
            @include('admin.departments.partials.dept-node-admin', ['node' => $child, 'depth' => $depth + 1, 'allDepts' => $allDepts])
        @endforeach
    </div>
    @endif

</div>
