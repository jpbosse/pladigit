<!DOCTYPE html>
<html lang="fr" data-theme="light" id="pd-html">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $link->album->name }} — Photothèque partagée</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
    body { margin: 0; background: var(--pd-bg); font-family: 'DM Sans', sans-serif; }
    .sh-header {
        background: var(--pd-surface); border-bottom: 1px solid var(--pd-border);
        padding: 14px 24px; display: flex; align-items: center; gap: 14px;
        position: sticky; top: 0; z-index: 100;
    }
    .sh-title { font-size: 15px; font-weight: 700; color: var(--pd-text); flex: 1; }
    .sh-meta { font-size: 11px; color: var(--pd-muted); }
    .sh-btn {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 7px 14px; border-radius: 8px; font-size: 12px; font-weight: 600;
        text-decoration: none; border: none; cursor: pointer; transition: opacity .15s;
    }
    .sh-btn:hover { opacity: .85; }
    .sh-btn.primary { background: var(--pd-navy); color: #fff; }
    .sh-btn.ghost { background: var(--pd-bg); color: var(--pd-text); border: 1px solid var(--pd-border); }
    .sh-content { padding: 24px; max-width: 1400px; margin: 0 auto; }
    .sh-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 12px;
    }
    .sh-card {
        border-radius: 10px; overflow: hidden; border: 1px solid var(--pd-border);
        background: var(--pd-surface); cursor: pointer;
        transition: box-shadow .15s, transform .15s;
    }
    .sh-card:hover { box-shadow: 0 6px 20px rgba(0,0,0,.12); transform: translateY(-2px); }
    .sh-thumb {
        aspect-ratio: 1; overflow: hidden; background: var(--pd-bg);
        display: flex; align-items: center; justify-content: center; font-size: 32px;
    }
    .sh-thumb img { width: 100%; height: 100%; object-fit: cover; transition: transform .3s; }
    .sh-card:hover .sh-thumb img { transform: scale(1.04); }
    .sh-card-info { padding: 7px 9px; }
    .sh-card-name { font-size: 11px; font-weight: 600; color: var(--pd-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .sh-card-size { font-size: 10px; color: var(--pd-muted); margin-top: 2px; }
    /* Lightbox */
    #sh-lb { position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,.92); display: none; align-items: center; justify-content: center; }
    #sh-lb.open { display: flex; }
    .sh-lb-close { position: absolute; top: 16px; right: 20px; width: 36px; height: 36px; border-radius: 50%; background: rgba(255,255,255,.15); border: none; color: #fff; font-size: 18px; cursor: pointer; display: flex; align-items: center; justify-content: center; }
    .sh-lb-nav { position: absolute; top: 50%; transform: translateY(-50%); width: 44px; height: 44px; border-radius: 50%; background: rgba(255,255,255,.15); border: none; color: #fff; font-size: 22px; cursor: pointer; }
    .sh-lb-nav.prev { left: 20px; }
    .sh-lb-nav.next { right: 20px; }
    .sh-lb-info { position: absolute; bottom: 20px; left: 50%; transform: translateX(-50%); background: rgba(0,0,0,.6); color: #fff; font-size: 12px; padding: 6px 16px; border-radius: 20px; white-space: nowrap; }
    @if($link->allow_download)
    .sh-lb-dl { position: absolute; top: 16px; right: 64px; }
    @endif
    .sh-empty { text-align: center; padding: 60px 0; color: var(--pd-muted); }
    </style>
</head>
<body>

<div class="sh-header">
    <div>
        <div class="sh-title">{{ $link->album->name }}</div>
        <div class="sh-meta">
            {{ $items->count() }} fichier{{ $items->count() > 1 ? 's' : '' }}
            @if($link->expires_at)
             · Lien valide jusqu'au {{ $link->expires_at->format('d/m/Y') }}
            @endif
        </div>
    </div>
    @if($link->allow_download && $items->isNotEmpty())
    <a href="{{ route('media.shared.export-zip', $token) }}" class="sh-btn ghost">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
            <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"/>
        </svg>
        Tout télécharger (ZIP)
    </a>
    @endif
</div>

<div class="sh-content">
    @if($items->isEmpty())
        <div class="sh-empty">
            <div style="font-size:40px;margin-bottom:12px;">🖼️</div>
            <p>Cet album ne contient aucun fichier.</p>
        </div>
    @else
        <div class="sh-grid">
            @foreach($items as $index => $item)
            <div class="sh-card" onclick="openLb({{ $index }})">
                <div class="sh-thumb">
                    @if($item->isImage())
                        <img src="{{ route('media.shared.serve', [$token, $item->id, 'thumb']) }}"
                             alt="{{ $item->caption ?? $item->file_name }}" loading="lazy">
                    @elseif($item->isVideo())
                        <span>🎬</span>
                    @else
                        <span>📄</span>
                    @endif
                </div>
                <div class="sh-card-info">
                    <div class="sh-card-name" title="{{ $item->caption ?? $item->file_name }}">
                        {{ $item->caption ?? $item->file_name }}
                    </div>
                    <div class="sh-card-size">{{ $item->humanSize() }}</div>
                </div>
            </div>
            @endforeach
        </div>
    @endif
</div>

{{-- Lightbox --}}
<div id="sh-lb">
    <button class="sh-lb-close" onclick="closeLb()">✕</button>
    <button class="sh-lb-nav prev" id="lb-prev" onclick="lbGo(-1)">‹</button>
    <div id="lb-content" style="display:flex;align-items:center;justify-content:center;max-width:90vw;max-height:85vh;"></div>
    <button class="sh-lb-nav next" id="lb-next" onclick="lbGo(1)">›</button>
    @if($link->allow_download)
    <div class="sh-lb-dl">
        <a id="lb-dl-btn" href="#" class="sh-btn primary" style="font-size:11px;padding:5px 12px;">↓ Télécharger</a>
    </div>
    @endif
    <div class="sh-lb-info" id="lb-info"></div>
</div>

<script>
const SH_ITEMS = @json($items->map(fn($item) => [
    'id'        => $item->id,
    'name'      => $item->caption ?? $item->file_name,
    'size'      => $item->humanSize(),
    'isImage'   => $item->isImage(),
    'isVideo'   => $item->isVideo(),
    'mime'      => $item->mime_type,
    'thumb'     => route('media.shared.serve', [$token, $item->id, 'thumb']),
    'full'      => route('media.shared.serve', [$token, $item->id, 'full']),
    'download'  => route('media.shared.download', [$token, $item->id]),
]));

let lbIdx = 0;

function openLb(idx) {
    lbIdx = idx;
    renderLb();
    document.getElementById('sh-lb').classList.add('open');
}
function closeLb() { document.getElementById('sh-lb').classList.remove('open'); }
function lbGo(d) { lbIdx = Math.max(0, Math.min(SH_ITEMS.length - 1, lbIdx + d)); renderLb(); }

function renderLb() {
    const item = SH_ITEMS[lbIdx];
    if (!item) return;
    const el = document.getElementById('lb-content');
    if (item.isImage)
        el.innerHTML = `<img src="${item.full}" style="max-width:90vw;max-height:85vh;border-radius:4px;object-fit:contain;" alt="${item.name}">`;
    else if (item.isVideo)
        el.innerHTML = `<video controls autoplay style="max-width:90vw;max-height:85vh;border-radius:4px;"><source src="${item.full}" type="${item.mime}"></video>`;
    else
        el.innerHTML = `<div style="text-align:center;color:#fff;padding:40px;"><div style="font-size:50px;margin-bottom:14px;">📄</div><p>${item.name}</p></div>`;

    document.getElementById('lb-info').textContent = `${item.name} — ${lbIdx + 1} / ${SH_ITEMS.length}`;
    document.getElementById('lb-prev').style.display = lbIdx > 0 ? '' : 'none';
    document.getElementById('lb-next').style.display = lbIdx < SH_ITEMS.length - 1 ? '' : 'none';

    const dlBtn = document.getElementById('lb-dl-btn');
    if (dlBtn) dlBtn.href = item.download;
}

document.getElementById('sh-lb')?.addEventListener('click', e => { if (e.target === e.currentTarget) closeLb(); });
document.addEventListener('keydown', e => {
    if (!document.getElementById('sh-lb').classList.contains('open')) return;
    if (e.key === 'Escape') closeLb();
    if (e.key === 'ArrowLeft') lbGo(-1);
    if (e.key === 'ArrowRight') lbGo(1);
});
</script>
</body>
</html>
