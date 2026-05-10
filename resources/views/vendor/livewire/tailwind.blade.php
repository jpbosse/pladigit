@php
if (! isset($scrollTo)) {
    $scrollTo = 'body';
}
$scrollIntoViewJsSnippet = ($scrollTo !== false)
    ? <<<JS
       (\$el.closest('{$scrollTo}') || document.querySelector('{$scrollTo}')).scrollIntoView()
    JS
    : '';

$btnBase   = "relative inline-flex items-center px-2 py-1 -ml-px text-xs font-medium text-gray-600 bg-white border border-gray-300 leading-5 hover:bg-gray-50 focus:z-10 focus:outline-none active:bg-gray-100 transition ease-in-out duration-150 cursor-pointer";
$btnDis    = "relative inline-flex items-center px-2 py-1 -ml-px text-xs font-medium text-gray-300 bg-white border border-gray-300 cursor-default leading-5";
$btnActive = "relative inline-flex items-center px-2 py-1 -ml-px text-xs font-bold text-gray-900 bg-gray-100 border border-gray-400 cursor-default leading-5";

$cur  = $paginator->currentPage();
$last = $paginator->lastPage();

if ($cur <= 3) {
    $from = 1;
    $to   = min(5, $last);
} elseif ($cur >= $last - 2) {
    $from = max(1, $last - 4);
    $to   = $last;
} else {
    $from = $cur - 2;
    $to   = $cur + 2;
}
@endphp

<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">

    {{-- Sélecteur nombre de lignes (gauche) --}}
    <div style="display:flex;align-items:center;gap:6px;">
        <span style="font-size:12px;color:var(--pd-muted);">Lignes :</span>
        <select wire:model.live="perPage"
                style="padding:3px 6px;border:1px solid var(--pd-border);border-radius:7px;font-size:12px;color:var(--pd-text);background:var(--pd-bg);">
            <option value="10">10</option>
            <option value="20">20</option>
            <option value="50">50</option>
        </select>
    </div>

    @if ($paginator->hasPages())
        {{-- Navigation (droite) --}}
        <nav role="navigation" aria-label="Pagination Navigation" style="display:flex;align-items:center;gap:8px;">

            {{-- Total pages --}}
            <span style="font-size:12px;color:var(--pd-muted);white-space:nowrap;">{{ $last }} pages</span>

            <span class="relative z-0 inline-flex rtl:flex-row-reverse rounded-md shadow-sm flex-wrap gap-px">

                {{-- « Première page --}}
                @if ($paginator->onFirstPage())
                    <span class="{{ $btnDis }} rounded-l-md">&laquo;</span>
                @else
                    <button type="button"
                            wire:click="gotoPage(1, '{{ $paginator->getPageName() }}')"
                            x-on:click="{{ $scrollIntoViewJsSnippet }}"
                            class="{{ $btnBase }} rounded-l-md">&laquo;</button>
                @endif

                {{-- ‹ Page précédente --}}
                @if ($paginator->onFirstPage())
                    <span class="{{ $btnDis }}">&lsaquo;</span>
                @else
                    <button type="button"
                            wire:click="previousPage('{{ $paginator->getPageName() }}')"
                            x-on:click="{{ $scrollIntoViewJsSnippet }}"
                            wire:loading.attr="disabled"
                            class="{{ $btnBase }}">&lsaquo;</button>
                @endif

                {{-- Numéros de pages --}}
                @for ($page = $from; $page <= $to; $page++)
                    <span wire:key="paginator-{{ $paginator->getPageName() }}-page{{ $page }}">
                        @if ($page == $cur)
                            <span class="{{ $btnActive }}">{{ $page }}</span>
                        @else
                            <button type="button"
                                    wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')"
                                    x-on:click="{{ $scrollIntoViewJsSnippet }}"
                                    class="{{ $btnBase }}"
                                    aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</button>
                        @endif
                    </span>
                @endfor

                {{-- › Page suivante --}}
                @if ($paginator->hasMorePages())
                    <button type="button"
                            wire:click="nextPage('{{ $paginator->getPageName() }}')"
                            x-on:click="{{ $scrollIntoViewJsSnippet }}"
                            wire:loading.attr="disabled"
                            class="{{ $btnBase }}">&rsaquo;</button>
                @else
                    <span class="{{ $btnDis }}">&rsaquo;</span>
                @endif

                {{-- » Dernière page --}}
                @if ($paginator->hasMorePages())
                    <button type="button"
                            wire:click="gotoPage({{ $paginator->lastPage() }}, '{{ $paginator->getPageName() }}')"
                            x-on:click="{{ $scrollIntoViewJsSnippet }}"
                            class="{{ $btnBase }} rounded-r-md">&raquo;</button>
                @else
                    <span class="{{ $btnDis }} rounded-r-md">&raquo;</span>
                @endif

            </span>
        </nav>
    @endif

</div>
