@foreach($nodes as $node)
@php
    $hasChildren = $node->children && $node->children->count() > 0;
    $hasMembers  = $node->members && $node->members->count() > 0;
    $label       = strtolower($node->label ?? $node->type ?? 'service');
    $color       = $node->color ?? null;
    $styleAttr   = $color ? "background:{$color};color:white;border-color:{$color}" : '';
@endphp
<li class="tree-item">
    <div class="node-row">
        <div class="node-card {{ $node->is_transversal ? 'transversal' : '' }} {{ !$hasChildren ? 'no-children' : '' }}"
             data-label="{{ $label }}"
             data-name="{{ strtolower($node->name) }}"
             @if($styleAttr) style="{{ $styleAttr }}" @endif
             onclick="toggle(this)">
            <span class="toggle-icon">▶</span>
            <div>
                <div class="node-label">{{ $node->label ?? $node->type ?? 'Service' }}</div>
                <div class="node-name">{{ $node->name }}</div>
                <div class="node-meta">
                    @if($node->managers && $node->managers->count())
                        {{ $node->managers->pluck('name')->join(', ') }} ·
                    @endif
                    {{ $node->members ? $node->members->count() : 0 }}p.
                    @if($hasChildren) · {{ $node->children->count() }} entité(s)@endif
                    @if($node->is_transversal)
                        <span class="badge-transversal">↔ transversal</span>
                    @endif
                </div>
            </div>
        </div>
        @if($hasMembers)
        <button class="btn btn-outline btn-sm no-print"
                style="margin-left:8px;font-size:0.65rem;padding:2px 7px"
                onclick="event.stopPropagation(); this.nextElementSibling.classList.toggle('visible')">
            👥
        </button>
        @endif
    </div>
    @if($hasMembers)
    <div class="node-members">
        @foreach($node->members as $m)
        <span class="chip {{ $m->pivot->is_manager ? 'mgr' : '' }}">
            <span class="chip-av">{{ strtoupper(substr($m->name,0,1)) }}</span>
            {{ $m->name }}@if($m->pivot->is_manager) ★@endif
        </span>
        @endforeach
    </div>
    @endif
    @if($hasChildren)
    <ul class="tree tree-children">
        @include('admin.departments.partials.dept-node', ['nodes' => $node->children, 'depth' => $depth + 1])
    </ul>
    @endif
</li>
@endforeach
