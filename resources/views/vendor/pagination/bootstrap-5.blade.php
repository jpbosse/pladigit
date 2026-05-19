@if ($paginator->hasPages())
<nav style="display:flex;align-items:center;justify-content:flex-end;gap:3px;flex-wrap:wrap;margin-top:16px;">

    {{-- Précédent --}}
    @if ($paginator->onFirstPage())
        <span style="padding:4px 10px;font-size:12px;border:0.5px solid var(--pd-border,#e2e8f0);border-radius:6px;color:var(--pd-muted,#9ca3af);cursor:default;">‹</span>
    @else
        <a href="{{ $paginator->previousPageUrl() }}" rel="prev"
           style="padding:4px 10px;font-size:12px;border:0.5px solid var(--pd-border,#e2e8f0);border-radius:6px;color:var(--pd-text,#374151);text-decoration:none;">‹</a>
    @endif

    {{-- Pages --}}
    @foreach ($elements as $element)
        @if (is_string($element))
            <span style="padding:4px 6px;font-size:12px;color:var(--pd-muted,#9ca3af);">…</span>
        @endif
        @if (is_array($element))
            @foreach ($element as $page => $url)
                @if ($page == $paginator->currentPage())
                    <span style="padding:4px 10px;font-size:12px;border-radius:6px;background:var(--pd-navy,#1e3a5f);color:#fff;font-weight:600;">{{ $page }}</span>
                @else
                    <a href="{{ $url }}"
                       style="padding:4px 10px;font-size:12px;border:0.5px solid var(--pd-border,#e2e8f0);border-radius:6px;color:var(--pd-text,#374151);text-decoration:none;">{{ $page }}</a>
                @endif
            @endforeach
        @endif
    @endforeach

    {{-- Suivant --}}
    @if ($paginator->hasMorePages())
        <a href="{{ $paginator->nextPageUrl() }}" rel="next"
           style="padding:4px 10px;font-size:12px;border:0.5px solid var(--pd-border,#e2e8f0);border-radius:6px;color:var(--pd-text,#374151);text-decoration:none;">›</a>
    @else
        <span style="padding:4px 10px;font-size:12px;border:0.5px solid var(--pd-border,#e2e8f0);border-radius:6px;color:var(--pd-muted,#9ca3af);cursor:default;">›</span>
    @endif

</nav>
@endif
