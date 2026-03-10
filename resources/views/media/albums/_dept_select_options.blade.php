{{--
    Partial récursif — options du select hiérarchique des entités.
    Variables attendues :
        $nodes  : Collection<Department>  — nœuds à afficher
        $depth  : int                     — niveau d'indentation (0 = racine)
--}}
@foreach($nodes as $node)
    @php
        $indent  = str_repeat('　', $depth);   // espace insécable japonais — rendu propre dans les <option>
        $icon    = $node->parent_id ? '📂' : '🏢';
        $typeVal = $node->parent_id ? 'service' : 'direction';
        $lbl     = $node->label ? $node->label.' ' : '';
    @endphp
    <option value="{{ $node->id }}" data-type="{{ $typeVal }}">
        {{ $indent }}{{ $icon }} {{ $lbl }}{{ $node->name }}
    </option>
    @if($node->allChildren && $node->allChildren->isNotEmpty())
        @include('media.albums._dept_select_options', [
            'nodes' => $node->allChildren,
            'depth' => $depth + 1,
        ])
    @endif
@endforeach
