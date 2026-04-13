@extends('layouts.admin')
@section('title', 'Paramètres — Visioconférence')

@section('admin-content')

<div style="margin-bottom:24px;">
    <h1 style="font-family:'Sora',sans-serif;font-size:20px;font-weight:700;color:var(--pd-text);margin:0 0 4px;">
        Visioconférence
    </h1>
    <p style="font-size:13px;color:var(--pd-muted);margin:0;">
        Configuration du serveur Jitsi Meet utilisé pour les réunions de projet
    </p>
</div>

@if(session('success'))
<div class="pd-alert pd-alert-success" style="margin-bottom:20px;">{{ session('success') }}</div>
@endif
@if($errors->any())
<div class="pd-alert pd-alert-danger" style="margin-bottom:20px;">{{ $errors->first() }}</div>
@endif

<div style="max-width:680px;display:flex;flex-direction:column;gap:20px;">

    {{-- ── Info instance publique ───────────────────────────── --}}
    <div style="display:flex;align-items:flex-start;gap:14px;padding:16px 18px;background:rgba(59,154,225,0.07);border:1.5px solid rgba(59,154,225,0.25);border-radius:12px;">
        <span style="font-size:1.5rem;flex-shrink:0;margin-top:2px;">📹</span>
        <div style="font-size:13px;line-height:1.65;color:var(--pd-text);">
            <strong style="display:block;margin-bottom:4px;">Instance par défaut — DINUM (État français)</strong>
            Pladigit utilise <strong>meet.numerique.gouv.fr</strong>, l'instance Jitsi Meet gérée par la Direction interministérielle du numérique.
            Elle est <strong>gratuite, sans inscription, conforme RGPD</strong> et souveraine.
            Les collectivités disposant de leur propre serveur Jitsi peuvent le renseigner ci-dessous.
        </div>
    </div>

    {{-- ── Formulaire ───────────────────────────────────────── --}}
    <div class="pd-card" style="padding:24px;">
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--pd-muted);margin-bottom:18px;padding-bottom:12px;border-bottom:1px solid var(--pd-border);">
            Serveur Jitsi Meet
        </div>

        <form method="POST" action="{{ route('admin.settings.visio.update') }}" id="visio-form">
            @csrf @method('PUT')

            <div class="pd-form-group">
                <label class="pd-label" for="jitsi_base_url">URL du serveur Jitsi</label>
                <div style="position:relative;">
                    <span style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--pd-muted);">
                        <svg style="width:14px;height:14px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                    </span>
                    <input type="url" id="jitsi_base_url" name="jitsi_base_url"
                           class="pd-input" style="padding-left:36px;font-family:monospace;font-size:13px;"
                           value="{{ old('jitsi_base_url', $settings->jitsi_base_url ?? 'https://meet.numerique.gouv.fr') }}"
                           placeholder="https://meet.numerique.gouv.fr"
                           oninput="updatePreview(this.value)">
                </div>
                <div class="pd-hint">
                    URL complète sans slash final. Exemples : <code style="font-family:monospace;background:var(--pd-bg);padding:1px 5px;border-radius:4px;">https://meet.numerique.gouv.fr</code>
                    ou votre instance auto-hébergée.
                </div>
                @error('jitsi_base_url')
                <div class="pd-error">{{ $message }}</div>
                @enderror
            </div>

            {{-- Aperçu live --}}
            <div style="border-radius:10px;border:1px solid var(--pd-border);overflow:hidden;margin-bottom:20px;">
                <div style="display:flex;align-items:center;gap:8px;padding:10px 14px;background:var(--pd-bg);border-bottom:1px solid var(--pd-border);">
                    <span style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--pd-muted);">Aperçu — salle générée</span>
                    <span style="margin-left:auto;font-size:10px;color:var(--pd-muted);">exemple non fonctionnel</span>
                </div>
                <div style="padding:12px 14px;display:flex;align-items:center;gap:10px;">
                    <svg style="width:14px;height:14px;flex-shrink:0;fill:none;stroke:var(--pd-accent);stroke-width:1.8;stroke-linecap:round;" viewBox="0 0 24 24"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                    <code id="url-preview" style="font-size:12.5px;font-family:monospace;color:var(--pd-text);word-break:break-all;">
                        {{ $settings->jitsi_base_url ?? 'https://meet.numerique.gouv.fr' }}/pladigit-mon-projet-a3f9k2
                    </code>
                </div>
            </div>

            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                <button type="submit" class="pd-btn pd-btn-primary">
                    <svg style="width:14px;height:14px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                    Enregistrer
                </button>
                <button type="button" class="pd-btn pd-btn-ghost" id="test-btn" onclick="testJitsi()">
                    <svg style="width:14px;height:14px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                    Tester l'accès
                </button>
                <span id="test-result" style="display:none;font-size:13px;font-weight:500;"></span>
            </div>

            <div style="margin-top:14px;padding:10px 14px;background:var(--pd-bg);border-radius:8px;border:1px solid var(--pd-border);font-size:12px;color:var(--pd-muted);line-height:1.6;">
                ℹ️ <strong style="color:var(--pd-text);">À propos du test :</strong>
                ce bouton sonde le serveur depuis votre navigateur.
                Les instances gouvernementales françaises (<em>DINUM, Éducation nationale</em>) bloquent généralement ce type de sonde réseau par politique CORS,
                même quand elles fonctionnent parfaitement pour les visioconférences Jitsi.
                Un résultat « pas de réponse » ne signifie pas que l'instance est hors service.
            </div>
        </form>
    </div>

    {{-- ── Instances recommandées ───────────────────────────── --}}
    <div class="pd-card" style="padding:24px;">
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--pd-muted);margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid var(--pd-border);">
            Instances de confiance
        </div>
        <div style="display:flex;flex-direction:column;gap:8px;">
            @foreach([
                ['url' => 'https://meet.numerique.gouv.fr', 'label' => 'DINUM — État français', 'badge' => 'Recommandée', 'color' => '#2ECC71'],
                ['url' => 'https://jitsi.apps.education.fr',  'label' => 'Éducation nationale', 'badge' => 'Publique', 'color' => '#3B9AE1'],
                ['url' => 'https://meet.jit.si',              'label' => 'Jitsi officiel (international)', 'badge' => 'Non souveraine', 'color' => '#E8A838'],
            ] as $instance)
            <div style="display:flex;align-items:center;gap:12px;padding:10px 14px;border:1px solid var(--pd-border);border-radius:9px;cursor:pointer;transition:background 0.15s;"
                 onclick="useInstance('{{ $instance['url'] }}')"
                 onmouseover="this.style.background='var(--pd-bg)'" onmouseout="this.style.background=''">
                <svg style="width:13px;height:13px;flex-shrink:0;fill:none;stroke:var(--pd-muted);stroke-width:1.8;stroke-linecap:round;" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                <div style="flex:1;min-width:0;">
                    <div style="font-size:13px;font-weight:500;color:var(--pd-text);">{{ $instance['label'] }}</div>
                    <div style="font-size:11.5px;font-family:monospace;color:var(--pd-muted);margin-top:1px;">{{ $instance['url'] }}</div>
                </div>
                <span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px;white-space:nowrap;background:{{ $instance['color'] }}1a;color:{{ $instance['color'] }};">
                    {{ $instance['badge'] }}
                </span>
                <svg style="width:13px;height:13px;flex-shrink:0;fill:none;stroke:var(--pd-muted);stroke-width:1.8;stroke-linecap:round;" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
            </div>
            @endforeach
        </div>
        <div style="margin-top:12px;font-size:11.5px;color:var(--pd-muted);line-height:1.5;">
            Cliquez sur une instance pour la sélectionner. N'oubliez pas d'enregistrer.
        </div>
    </div>

</div>

<script>
function updatePreview(val) {
    var base = (val || 'https://meet.numerique.gouv.fr').replace(/\/$/, '');
    document.getElementById('url-preview').textContent = base + '/pladigit-mon-projet-a3f9k2';
}

function useInstance(url) {
    var input = document.getElementById('jitsi_base_url');
    input.value = url;
    updatePreview(url);
    input.focus();
}

async function testJitsi() {
    var btn    = document.getElementById('test-btn');
    var result = document.getElementById('test-result');
    var url    = document.getElementById('jitsi_base_url').value.trim();

    if (!url) { return; }

    btn.disabled = true;
    var origHtml = btn.innerHTML;
    btn.innerHTML = '<svg style="width:14px;height:14px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;" viewBox="0 0 24 24"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> Test en cours…';
    result.style.display = 'none';

    try {
        var controller = new AbortController();
        var timeout    = setTimeout(() => controller.abort(), 6000);
        await fetch(url.replace(/\/$/, '') + '/', {
            method: 'HEAD',
            mode: 'no-cors',
            signal: controller.signal
        });
        clearTimeout(timeout);
        // Réponse opaque reçue = serveur joignable depuis le navigateur
        result.innerHTML  = '✅ Serveur joignable depuis le navigateur';
        result.style.color = 'var(--pd-success)';
    } catch (e) {
        if (e.name === 'AbortError') {
            // Les instances gouvernementales (DINUM, Éducation nationale) bloquent
            // souvent les sondes HTTP depuis le navigateur par politique CORS/réseau,
            // même quand elles fonctionnent parfaitement pour Jitsi.
            result.innerHTML  = '⚠ Pas de réponse — l\'instance est peut-être fonctionnelle malgré tout (les serveurs de l\'État bloquent ce type de sonde)';
            result.style.color = 'var(--pd-warning)';
        } else {
            result.innerHTML  = '❌ URL incorrecte ou serveur inaccessible';
            result.style.color = 'var(--pd-danger)';
        }
    }

    result.style.display = 'inline';
    btn.disabled = false;
    btn.innerHTML = origHtml;
}
</script>
@endsection
