{{-- _observations.blade.php — Observations & décisions --}}
@php
$obsTypes = \App\Models\Tenant\ProjectObservation::typeConfig();
$allObs   = $project->observations->load('user');
$counts   = $allObs->groupBy('type')->map->count();
@endphp

<div x-data="{ filter: 'all' }">

<div class="section-hdr">
    <div>
        <div class="section-title">Observations &amp; décisions</div>
        <div class="section-sub">
            {{ $allObs->count() }} entrée{{ $allObs->count() > 1 ? 's' : '' }}
            @foreach($obsTypes as $key => $cfg)
                @if(($counts[$key] ?? 0) > 0)
                · <span style="color:{{ $cfg['text'] }};">{{ $counts[$key] }} {{ strtolower($cfg['label']) }}{{ $counts[$key] > 1 ? 's' : '' }}</span>
                @endif
            @endforeach
        </div>
    </div>
</div>

{{-- ── Filtres par type ─────────────────────────────────────────────── --}}
<div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:16px;">
    <button @click="filter='all'"
            :style="filter==='all' ? 'background:var(--pd-navy);color:#fff;border-color:var(--pd-navy);' : 'background:none;color:var(--pd-muted);'"
            style="padding:4px 12px;border-radius:20px;font-size:12px;border:0.5px solid var(--pd-border);cursor:pointer;transition:all .15s;">
        Tous ({{ $allObs->count() }})
    </button>
    @foreach($obsTypes as $key => $cfg)
    <button @click="filter='{{ $key }}'"
            :style="filter==='{{ $key }}' ? 'background:{{ $cfg['bg'] }};color:{{ $cfg['text'] }};border-color:{{ $cfg['text'] }};' : 'background:none;color:var(--pd-muted);'"
            style="padding:4px 12px;border-radius:20px;font-size:12px;border:0.5px solid var(--pd-border);cursor:pointer;transition:all .15s;">
        {{ $cfg['label'] }}s ({{ $counts[$key] ?? 0 }})
    </button>
    @endforeach
</div>

{{-- ── Liste des observations ───────────────────────────────────────── --}}
<div class="pd-card" style="margin-bottom:16px;padding:0;">
    @forelse($allObs as $obs)
    @php $oc = $obsTypes[$obs->type] ?? ['label'=>$obs->type,'bg'=>'#F1F5F9','text'=>'#475569']; @endphp
    <div class="obs-item"
         style="padding:14px 16px;"
         x-show="filter === 'all' || filter === '{{ $obs->type }}'">

        {{-- Avatar --}}
        <div style="width:32px;height:32px;border-radius:50%;background:var(--pd-navy);color:#fff;font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            {{ strtoupper(substr($obs->user->name ?? '?', 0, 2)) }}
        </div>

        {{-- Contenu --}}
        <div style="flex:1;min-width:0;">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:5px;flex-wrap:wrap;">
                <span style="font-size:12px;font-weight:600;color:var(--pd-text);">{{ $obs->user->name ?? '—' }}</span>
                <span style="display:inline-block;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700;background:{{ $oc['bg'] }};color:{{ $oc['text'] }};">
                    {{ $oc['label'] }}
                </span>
                <span style="font-size:11px;color:var(--pd-muted);margin-left:auto;">
                    {{ $obs->created_at->translatedFormat('d M Y à H:i') }}
                </span>
            </div>
            <div style="font-size:13px;color:var(--pd-text);line-height:1.6;white-space:pre-line;">{{ $obs->body }}</div>
        </div>

        {{-- Suppression --}}
        @if(auth()->id() === $obs->user_id || $canManage)
        <form method="POST"
              action="{{ route('projects.observations.destroy', [$project, $obs]) }}"
              style="align-self:flex-start;flex-shrink:0;"
              onsubmit="return confirm('Supprimer ?');">
            @csrf @method('DELETE')
            <button type="submit"
                    style="background:none;border:none;cursor:pointer;color:var(--pd-muted);font-size:16px;padding:2px 4px;line-height:1;">×</button>
        </form>
        @endif
    </div>
    @empty
    <div style="text-align:center;padding:40px 20px;color:var(--pd-muted);font-size:12px;">
        Aucune observation pour le moment.
    </div>
    @endforelse
</div>

{{-- ── Formulaire d'ajout ───────────────────────────────────────────── --}}
<div class="pd-card">
    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--pd-muted);margin-bottom:12px;">
        Ajouter une observation
    </div>
    <form method="POST" action="{{ route('projects.observations.store', $project) }}">
        @csrf
        <div style="display:flex;gap:8px;margin-bottom:10px;flex-wrap:wrap;">
            @foreach($obsTypes as $key => $cfg)
            <label style="display:flex;align-items:center;gap:5px;cursor:pointer;padding:5px 12px;border-radius:20px;border:0.5px solid {{ $cfg['text'] }};background:{{ $cfg['bg'] }};font-size:12px;font-weight:600;color:{{ $cfg['text'] }};">
                <input type="radio" name="type" value="{{ $key }}" {{ $key === 'observation' ? 'checked' : '' }}
                       style="accent-color:{{ $cfg['text'] }};margin:0;">
                {{ $cfg['label'] }}
            </label>
            @endforeach
        </div>
        <textarea name="body" class="pd-input" rows="3"
                  placeholder="Saisissez votre observation, question, décision ou alerte…"
                  required style="width:100%;resize:vertical;margin-bottom:10px;"></textarea>
        <div style="display:flex;justify-content:flex-end;">
            <button type="submit" class="pd-btn pd-btn-primary" style="font-size:12px;">Publier</button>
        </div>
    </form>
</div>

</div>{{-- /x-data --}}
