{{-- resources/views/projects/partials/_agenda.blade.php --}}
@php
$agendaEvents = $project->events()
    ->where(function ($q) {
        $q->where('visibility', '!=', 'private')
          ->orWhere('created_by', auth()->id());
    })
    ->orderBy('starts_at')
    ->get();
@endphp

<div x-data="{ showEventForm: false }" @open-new-event.window="showEventForm = true">

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
    <span style="font-size:13px;color:var(--pd-muted);">
        {{ $agendaEvents->count() }} événement{{ $agendaEvents->count() > 1 ? 's' : '' }}
    </span>
    <div style="display:flex;gap:8px;">
        <a href="{{ route('projects.export.ical', $project) }}" class="pd-btn pd-btn-sm pd-btn-secondary">
            Exporter iCal
        </a>
        @if($canEdit)
        <button @click="showEventForm = true" class="pd-btn pd-btn-sm pd-btn-primary">
            + Événement
        </button>
        @endif
    </div>
</div>

{{-- Modal création événement --}}
@if($canEdit)
<div x-show="showEventForm" x-cloak
     style="position:fixed;inset:0;z-index:9000;display:flex;align-items:center;justify-content:center;"
     @keydown.escape.window="showEventForm = false">

    {{-- Overlay --}}
    <div @click="showEventForm = false"
         style="position:absolute;inset:0;background:rgba(0,0,0,.4);"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"></div>

    {{-- Panneau --}}
    <div style="position:relative;background:var(--pd-bg);border-radius:12px;padding:24px;width:100%;max-width:480px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.15);"
         x-transition:enter="transform transition ease-out duration-150"
         x-transition:enter-start="scale-95 opacity-0"
         x-transition:enter-end="scale-100 opacity-100"
         x-transition:leave="transform transition ease-in duration-100"
         x-transition:leave-start="scale-100 opacity-100"
         x-transition:leave-end="scale-95 opacity-0">

        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
            <h3 style="font-size:16px;font-weight:600;margin:0;">Nouvel événement</h3>
            <button @click="showEventForm = false"
                    style="background:none;border:none;cursor:pointer;color:var(--pd-muted);font-size:20px;line-height:1;padding:0 4px;">×</button>
        </div>

        <form method="POST" action="{{ route('projects.events.store', $project) }}">
            @csrf

            <div class="pd-form-group">
                <label class="pd-label">Titre *</label>
                <input type="text" name="title" class="pd-input" required autofocus
                       placeholder="Ex : Réunion de suivi">
            </div>

            <div class="pd-form-group">
                <label class="pd-label">Description</label>
                <textarea name="description" class="pd-input" rows="2"
                          placeholder="Description optionnelle…"></textarea>
            </div>

            <div class="pd-form-group">
                <label class="pd-label">Lieu</label>
                <input type="text" name="location" class="pd-input"
                       placeholder="Salle de réunion, visio…">
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div class="pd-form-group">
                    <label class="pd-label">Début *</label>
                    <input type="datetime-local" name="starts_at" class="pd-input" required
                           value="{{ now()->format('Y-m-d\TH:i') }}">
                </div>
                <div class="pd-form-group">
                    <label class="pd-label">Fin *</label>
                    <input type="datetime-local" name="ends_at" class="pd-input" required
                           value="{{ now()->addHour()->format('Y-m-d\TH:i') }}">
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div class="pd-form-group">
                    <label class="pd-label">Visibilité</label>
                    <select name="visibility" class="pd-input">
                        <option value="private">Privé</option>
                        <option value="restricted" selected>Restreint</option>
                        <option value="public">Public</option>
                    </select>
                </div>
                <div class="pd-form-group">
                    <label class="pd-label">Couleur</label>
                    <input type="color" name="color" class="pd-input"
                           style="height:42px;padding:4px;"
                           value="{{ $project->color }}">
                </div>
            </div>

            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:8px;">
                <button type="button" @click="showEventForm = false"
                        class="pd-btn pd-btn-secondary">Annuler</button>
                <button type="submit" class="pd-btn pd-btn-primary">Créer l'événement</button>
            </div>
        </form>
    </div>
</div>
@endif

@forelse($agendaEvents as $event)
@php
    $visColors = [
        'private'    => ['#E2E8F0','#475569'],
        'restricted' => ['#DBEAFE','#1E40AF'],
        'public'     => ['#D1FAE5','#065F46'],
    ];
@endphp
<div style="display:flex;gap:12px;padding:12px;border:0.5px solid var(--pd-border);border-radius:8px;margin-bottom:8px;border-left:4px solid {{ $event->color }};">
    <div style="flex-shrink:0;text-align:center;width:44px;">
        <div style="font-size:20px;font-weight:700;color:var(--pd-navy);line-height:1;">
            {{ \Carbon\Carbon::parse($event->starts_at)->format('d') }}
        </div>
        <div style="font-size:11px;color:var(--pd-muted);">
            {{ \Carbon\Carbon::parse($event->starts_at)->translatedFormat('M') }}
        </div>
    </div>
    <div style="flex:1;">
        <div style="font-size:14px;font-weight:500;color:var(--pd-text);">{{ $event->title }}</div>
        <div style="font-size:12px;color:var(--pd-muted);">
            {{ \Carbon\Carbon::parse($event->starts_at)->format('H:i') }}
            @if(! $event->all_day)
                → {{ \Carbon\Carbon::parse($event->ends_at)->format('H:i') }}
            @endif
            @if($event->location) · {{ $event->location }} @endif
        </div>
        @if($event->description)
        <div style="font-size:12px;color:var(--pd-muted);margin-top:4px;">
            {{ Str::limit($event->description, 120) }}
        </div>
        @endif
    </div>
    <div>
        <span style="font-size:10px;padding:2px 6px;border-radius:10px;background:{{ $visColors[$event->visibility][0] }};color:{{ $visColors[$event->visibility][1] }};">
            {{ ['private' => 'Privé', 'restricted' => 'Restreint', 'public' => 'Public'][$event->visibility] }}
        </span>
    </div>
</div>
@empty
<div style="text-align:center;padding:32px;color:var(--pd-muted);">
    Aucun événement pour ce projet.
</div>
@endforelse

</div>{{-- /x-data showEventForm --}}
