{{--
    Partial récursif — checkboxes hiérarchie complète des départements.
    Variables attendues :
        $nodes       : Collection<Department>  — nœuds à afficher
        $depth       : int                     — niveau d'indentation (0 = racine)
        $checkedIds  : array<int>              — IDs déjà cochés (old() ou userDeptIds)
        $searchId    : string                  — id de l'input recherche (pour JS)
--}}
@foreach($nodes as $node)
@php
    $indent  = $depth * 18; // px de padding-left
    $isRoot  = $node->parent_id === null;
    $icon    = $isRoot ? '🏢' : ($node->allChildren && $node->allChildren->isNotEmpty() ? '📁' : '📂');
    $checked = in_array($node->id, $checkedIds);
@endphp
<label class="dept-item"
       style="display:flex;align-items:center;gap:10px;padding:7px 10px;padding-left:{{ 10 + $indent }}px;border-radius:8px;cursor:pointer;transition:background .1s;"
       onmouseover="this.style.background='var(--pd-surface)'" onmouseout="this.style.background=''">
    <input type="checkbox"
           name="department_ids[]"
           value="{{ $node->id }}"
           data-name="{{ strtolower($node->name) }} {{ strtolower($node->label ?? '') }}"
           style="accent-color:var(--pd-accent);width:15px;height:15px;flex-shrink:0;"
           {{ $checked ? 'checked' : '' }}>
    <span style="font-size:13px;color:var(--pd-text);line-height:1.3;">
        {{ $icon }}
        @if($node->label)
            <span style="font-size:11px;font-weight:600;color:var(--pd-muted);text-transform:uppercase;letter-spacing:.04em;margin-right:4px;">{{ $node->label }}</span>
        @endif
        <span style="{{ $isRoot ? 'font-weight:600;' : '' }}">{{ $node->name }}</span>
    </span>
</label>

@if($node->allChildren && $node->allChildren->isNotEmpty())
    @include('admin.users._dept_checkboxes', [
        'nodes'      => $node->allChildren,
        'depth'      => $depth + 1,
        'checkedIds' => $checkedIds,
    ])
@endif
@endforeach
