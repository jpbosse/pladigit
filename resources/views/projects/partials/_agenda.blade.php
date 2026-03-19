{{-- _agenda.blade.php — Agenda par jalon, à venir par défaut --}}
@php
$allEvents = $project->events()
    ->where(function ($q) {
        $q->where('visibility', '!=', 'private')
          ->orWhere('created_by', auth()->id());
    })
    ->orderBy('starts_at')
    ->get();

$upcomingEvents = $allEvents->filter(fn($e) => \Carbon\Carbon::parse($e->starts_at)->isFuture() || \Carbon\Carbon::parse($e->starts_at)->isToday());
$pastEvents     = $allEvents->filter(fn($e) => \Carbon\Carbon::parse($e->starts_at)->isPast() && !\Carbon\Carbon::parse($e->starts_at)->isToday());

// Grouper les événements par jalon (via starts_at dans la plage du jalon)
$milestones = $project->milestones->sortBy('due_date');

$visColors = [
    'private'    => ['bg'=>'#E2E8F0','text'=>'#475569'],
    'restricted' => ['bg'=>'#DBEAFE','text'=>'#1E40AF'],
    'public'     => ['bg'=>'#D1FAE5','text'=>'#065F46'],
];

// Jalon actif
$nextActiveMs = null;
foreach ($milestones as $ms) {
    if (!$ms->isReached() && $ms->due_date && $ms->due_date->isFuture()) {
        $nextActiveMs = $ms->id;
        break;
    }
}
@endphp

<div x-data="{ showAll: false, showEventForm: false }" @open-new-event.window="showEventForm = true">

{{-- ── Barre d'outils ── --}}
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap;gap:8px;">
    <div style="display:flex;align-items:center;gap:10px;">
        <span style="font-size:12px;color:var(--pd-muted);">
            {{ $upcomingEvents->count() }} à venir · {{ $pastEvents->count() }} passé{{ $pastEvents->count()>1?'s':'' }}
        </span>
        @if($pastEvents->count() > 0)
        <label style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--pd-muted);cursor:pointer;">
            <input type="checkbox" x-model="showAll" style="accent-color:var(--pd-navy);">
            Afficher les passés
        </label>
        @endif
    </div>
    <div style="display:flex;gap:8px;">
        <a href="{{ route('projects.export.ical', $project) }}"
           class="pd-btn pd-btn-sm pd-btn-secondary">Exporter iCal</a>
        @if($canEdit)
        <button @click="showEventForm = true" class="pd-btn pd-btn-sm pd-btn-primary">
            + Événement
        </button>
        @endif
    </div>
</div>

{{-- ── Événements à venir ── --}}
@if($upcomingEvents->isEmpty())
<div style="text-align:center;padding:32px;color:var(--pd-muted);font-size:12px;">
    Aucun événement à venir. Cliquez sur + Événement pour en créer un.
</div>
@else
<div style="margin-bottom:16px;">
    <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--pd-muted);margin-bottom:8px;">
        À venir
    </div>
    @foreach($upcomingEvents as $event)
    @php $starts = \Carbon\Carbon::parse($event->starts_at); @endphp
    <div style="display:flex;gap:12px;padding:12px;border:0.5px solid var(--pd-border);border-radius:8px;margin-bottom:6px;border-left:4px solid {{ $event->color ?? $project->color }};background:var(--pd-surface);">
        <div style="flex-shrink:0;text-align:center;width:40px;">
            <div style="font-size:20px;font-weight:700;color:var(--pd-navy);line-height:1;">{{ $starts->format('d') }}</div>
            <div style="font-size:10px;color:var(--pd-muted);">{{ $starts->translatedFormat('M') }}</div>
        </div>
        <div style="flex:1;">
            <div style="font-size:13px;font-weight:600;color:var(--pd-text);">{{ $event->title }}</div>
            <div style="font-size:11px;color:var(--pd-muted);margin-top:2px;">
                {{ $starts->format('H:i') }}
                @if(!$event->all_day) → {{ \Carbon\Carbon::parse($event->ends_at)->format('H:i') }} @endif
                @if($event->location) · {{ $event->location }} @endif
            </div>
            @if($event->description)
            <div style="font-size:11px;color:var(--pd-muted);margin-top:3px;">{{ Str::limit($event->description, 100) }}</div>
            @endif
        </div>
        <span style="font-size:10px;padding:2px 7px;border-radius:8px;font-weight:600;align-self:flex-start;background:{{ $visColors[$event->visibility]['bg'] }};color:{{ $visColors[$event->visibility]['text'] }};">
            {{ ['private'=>'Privé','restricted'=>'Restreint','public'=>'Public'][$event->visibility] }}
        </span>
    </div>
    @endforeach
</div>
@endif

{{-- ── Événements passés (masqués par défaut) ── --}}
@if($pastEvents->count() > 0)
<div x-show="showAll"
     x-transition:enter="transition ease-out duration-150"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100">
    <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--pd-muted);margin-bottom:8px;">
        Passés ({{ $pastEvents->count() }})
    </div>
    @foreach($pastEvents->sortByDesc(fn($e) => $e->starts_at) as $event)
    @php $starts = \Carbon\Carbon::parse($event->starts_at); @endphp
    <div style="display:flex;gap:12px;padding:10px 12px;border:0.5px solid var(--pd-border);border-radius:8px;margin-bottom:6px;border-left:4px solid {{ $event->color ?? $project->color }};opacity:.6;background:var(--pd-surface2);">
        <div style="flex-shrink:0;text-align:center;width:40px;">
            <div style="font-size:18px;font-weight:700;color:var(--pd-muted);line-height:1;">{{ $starts->format('d') }}</div>
            <div style="font-size:10px;color:var(--pd-muted);">{{ $starts->translatedFormat('M') }}</div>
        </div>
        <div style="flex:1;">
            <div style="font-size:12px;font-weight:500;color:var(--pd-muted);text-decoration:line-through;">{{ $event->title }}</div>
            <div style="font-size:11px;color:var(--pd-muted);margin-top:2px;">
                {{ $starts->translatedFormat('d M Y') }} · {{ $starts->format('H:i') }}
                @if($event->location) · {{ $event->location }} @endif
            </div>
        </div>
    </div>
    @endforeach
</div>
@endif

{{-- ── Modal création événement ── --}}
@if($canEdit)
<div id="modal-event" class="pd-modal-overlay" x-show="showEventForm" x-cloak
     @click="if($event.target===$el) showEventForm=false"
     @keydown.escape.window="showEventForm=false">
    <div class="pd-modal pd-modal-md" @click.stop>
        <div class="pd-modal-header pd-modal-header--colored pd-modal-header--navy">
            <div class="pd-modal-title">Nouvel événement</div>
            <button type="button" class="pd-modal-close" @click="showEventForm=false">×</button>
        </div>
        <form method="POST" action="{{ route('projects.events.store', $project) }}">
            @csrf
            <div class="pd-modal-body">
                <div class="pd-form-group">
                    <label class="pd-label pd-label-req">Titre</label>
                    <input type="text" name="title" class="pd-input" required autofocus placeholder="Ex : Réunion de suivi">
                </div>
                <div class="pd-form-group">
                    <label class="pd-label">Description</label>
                    <textarea name="description" class="pd-input" rows="2"></textarea>
                </div>
                <div class="pd-form-group">
                    <label class="pd-label">Lieu</label>
                    <input type="text" name="location" class="pd-input" placeholder="Salle, visio…">
                </div>
                <div class="pd-form-row pd-form-row-2">
                    <div class="pd-form-group" style="margin-bottom:0;">
                        <label class="pd-label pd-label-req">Début</label>
                        <input type="datetime-local" name="starts_at" class="pd-input" required value="{{ now()->format('Y-m-d\TH:i') }}">
                    </div>
                    <div class="pd-form-group" style="margin-bottom:0;">
                        <label class="pd-label pd-label-req">Fin</label>
                        <input type="datetime-local" name="ends_at" class="pd-input" required value="{{ now()->addHour()->format('Y-m-d\TH:i') }}">
                    </div>
                </div>
                <div class="pd-form-row pd-form-row-2">
                    <div class="pd-form-group" style="margin-bottom:0;">
                        <label class="pd-label">Visibilité</label>
                        <select name="visibility" class="pd-input">
                            <option value="private">Privé</option>
                            <option value="restricted" selected>Restreint</option>
                            <option value="public">Public</option>
                        </select>
                    </div>
                    <div class="pd-form-group" style="margin-bottom:0;">
                        <label class="pd-label">Couleur</label>
                        <input type="color" name="color" class="pd-input" value="{{ $project->color }}">
                    </div>
                </div>
            </div>
            <div class="pd-modal-footer">
                <button type="button" class="pd-btn pd-btn-secondary pd-btn-sm" @click="showEventForm=false">Annuler</button>
                <button type="submit" class="pd-btn pd-btn-primary pd-btn-sm">Créer</button>
            </div>
        </form>
    </div>
</div>
@endif

</div>
