{{-- Partial récursif — arbre GED sources démo --}}
@php $indent = $depth * 16; @endphp

@if($node['type'] === 'folder')
<div style="{{ $indent > 0 ? "margin-left:{$indent}px;" : '' }}margin-bottom:2px;">
    <div style="display:flex;align-items:center;gap:6px;padding:5px 8px;border-radius:7px;
                background:rgba(59,154,225,0.07);border:1px solid rgba(59,154,225,0.15);
                font-size:12px;font-weight:600;color:var(--pd-text);">
        <span>📂</span>
        <span style="flex:1;">{{ $node['name'] }}</span>
    </div>
    @foreach($node['children'] as $child)
        @include('admin.demo.partials.ged-node', ['node' => $child, 'depth' => $depth + 1])
    @endforeach
</div>
@else
<div style="{{ $indent > 0 ? "margin-left:{$indent}px;" : '' }}display:flex;justify-content:space-between;align-items:center;
            padding:5px 8px;border-radius:7px;background:var(--pd-bg);border:1px solid var(--pd-border);
            font-size:12px;margin-bottom:2px;">
    <span style="display:flex;align-items:center;gap:5px;overflow:hidden;">
        <span>📄</span>
        <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--pd-text);"
              title="{{ $node['name'] }}">{{ $node['name'] }}</span>
    </span>
    <div style="display:flex;align-items:center;gap:8px;flex-shrink:0;">
        <span style="color:var(--pd-muted);">{{ $node['size'] }}</span>
        <form method="POST" action="{{ route('admin.demo.file.delete') }}" style="margin:0;"
              onsubmit="return confirm('Supprimer {{ addslashes($node['name']) }} ?')">
            @csrf @method('DELETE')
            <input type="hidden" name="type" value="ged">
            <input type="hidden" name="path" value="{{ $node['path'] }}">
            <button type="submit"
                    style="background:none;border:none;cursor:pointer;font-size:13px;padding:2px 4px;color:var(--pd-muted);transition:color 0.15s;"
                    onmouseover="this.style.color='#e74c3c'" onmouseout="this.style.color='var(--pd-muted)'">
                🗑
            </button>
        </form>
    </div>
</div>
@endif
