{{-- _agenda.blade.php — Agenda événements projet --}}
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

$visColors = [
    'private'    => ['bg'=>'#E2E8F0','text'=>'#475569'],
    'restricted' => ['bg'=>'#DBEAFE','text'=>'#1E40AF'],
    'public'     => ['bg'=>'#D1FAE5','text'=>'#065F46'],
];
@endphp

<div x-data="{ showAll:false, showEventForm:false, showEditForm:false, editEvent:{} }"
@open-new-event.window="showEventForm=true"
@open-edit-event.window="showEditForm=true"
@close-event-slideover.window="showEventForm=false; showEditForm=false">

<div style="font-size:11px;color:var(--pd-muted);margin-bottom:12px;padding:8px 12px;background:var(--pd-bg2);border-radius:7px;border-left:3px solid var(--pd-accent);">
    ℹ️ Cet agenda regroupe les <strong>rendez-vous et réunions</strong> du projet. Il ne reflète pas le planning des tâches (voir <em>Planification</em>).
</div>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:8px;">
    <div style="font-size:12px;color:var(--pd-muted);">
        {{ $upcomingEvents->count() }} à venir · {{ $pastEvents->count() }} passé{{ $pastEvents->count()>1?'s':'' }}
        @if($pastEvents->count() > 0)
        <label style="display:inline-flex;align-items:center;gap:6px;margin-left:12px;cursor:pointer;">
            <input type="checkbox" x-model="showAll" style="accent-color:var(--pd-navy);"> Afficher les passés
        </label>
        @endif
    </div>
    <div style="display:flex;gap:8px;">
        <button onclick="startVisio({{ $project->id }})" class="pd-btn pd-btn-sm" style="background:#0891B2;color:#fff;border:none;">📹 Visio</button>
        <a href="{{ route('projects.export.ical', $project) }}" class="pd-btn pd-btn-sm pd-btn-secondary">⬇ iCal</a>
        @if($canEdit)
        <button @click="showEventForm=true" class="pd-btn pd-btn-sm pd-btn-primary">+ Événement</button>
        @endif
    </div>
</div>

@if($upcomingEvents->isEmpty())
<div style="text-align:center;padding:40px 20px;color:var(--pd-muted);font-size:12px;">
    <div style="font-size:2rem;margin-bottom:8px;">📅</div>
    Aucun événement à venir.
</div>
@else
<div style="margin-bottom:20px;">
    <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--pd-muted);margin-bottom:10px;">À venir</div>
    @foreach($upcomingEvents as $event)
    @php
        $starts=$e = \Carbon\Carbon::parse($event->starts_at);
        $ends=\Carbon\Carbon::parse($event->ends_at);
        $canEditEv=$canEdit && auth()->id()===$event->created_by;
        $viewArgs=json_encode(['id'=>$event->id,'title'=>$event->title,'description'=>$event->description,'location'=>$event->location,'starts_at'=>$starts->translatedFormat('l d M Y à H:i'),'ends_at'=>$ends->format('H:i'),'all_day'=>$event->all_day,'visibility'=>$event->visibility,'color'=>$event->color??$project->color,'creator'=>$event->creator?->name??'—','can_edit'=>$canEditEv,'raw_title'=>$event->title,'raw_desc'=>$event->description??'','raw_location'=>$event->location??'','raw_starts'=>$starts->format('Y-m-d\TH:i'),'raw_ends'=>$ends->format('Y-m-d\TH:i')]);
    @endphp
    <div style="display:flex;border-radius:10px;overflow:hidden;border:0.5px solid var(--pd-border);margin-bottom:8px;cursor:pointer;background:var(--pd-surface);"
         onclick="window.dispatchEvent(new CustomEvent('open-view-event',{detail:{{ $viewArgs }}}))">
        <div style="width:4px;flex-shrink:0;background:{{ $event->color ?? $project->color }};"></div>
        <div style="flex-shrink:0;width:54px;text-align:center;padding:12px 6px;border-right:0.5px solid var(--pd-border);display:flex;flex-direction:column;align-items:center;justify-content:center;">
            <div style="font-size:22px;font-weight:700;color:var(--pd-navy);line-height:1;">{{ $starts->format('d') }}</div>
            <div style="font-size:10px;font-weight:600;text-transform:uppercase;color:var(--pd-muted);">{{ $starts->translatedFormat('M') }}</div>
            <div style="font-size:10px;color:var(--pd-muted);">{{ $starts->format('Y') }}</div>
        </div>
        <div style="flex:1;padding:10px 14px;">
            <div style="font-size:13px;font-weight:700;color:var(--pd-text);margin-bottom:4px;">{{ $event->title }}</div>
            <div style="font-size:11px;color:var(--pd-muted);display:flex;flex-wrap:wrap;gap:10px;">
                <span>🕐 {{ $starts->format('H:i') }}@if(!$event->all_day) → {{ $ends->format('H:i') }}@endif</span>
                @if($event->location)<span>📍 {{ $event->location }}</span>@endif
            </div>
            @if($event->description)<div style="font-size:11px;color:var(--pd-muted);margin-top:5px;line-height:1.4;">{{ Str::limit($event->description,120) }}</div>@endif
        </div>
        <div style="flex-shrink:0;padding:10px 12px;display:flex;align-items:flex-start;">
            <span style="font-size:10px;padding:2px 8px;border-radius:8px;font-weight:600;background:{{ $visColors[$event->visibility]['bg'] }};color:{{ $visColors[$event->visibility]['text'] }};"
                  title="{{ ['private'=>'Visible par vous seul','restricted'=>'Visible par les membres','public'=>'Visible par tous'][$event->visibility] }}">
                {{ ['private'=>'🔒 Privé','restricted'=>'🔵 Restreint','public'=>'🟢 Public'][$event->visibility] }}
            </span>
        </div>
    </div>
    @endforeach
</div>
@endif

@if($pastEvents->count() > 0)
<div x-show="showAll" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
    <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--pd-muted);margin-bottom:10px;">Passés ({{ $pastEvents->count() }})</div>
    @foreach($pastEvents->sortByDesc(fn($e) => $e->starts_at) as $event)
    @php $starts=\Carbon\Carbon::parse($event->starts_at); @endphp
    <div style="display:flex;border-radius:10px;overflow:hidden;border:0.5px solid var(--pd-border);margin-bottom:6px;opacity:.55;background:var(--pd-surface2);">
        <div style="width:4px;flex-shrink:0;background:{{ $event->color ?? $project->color }};opacity:.4;"></div>
        <div style="flex-shrink:0;width:54px;text-align:center;padding:10px 6px;border-right:0.5px solid var(--pd-border);display:flex;flex-direction:column;align-items:center;justify-content:center;">
            <div style="font-size:18px;font-weight:700;color:var(--pd-muted);line-height:1;">{{ $starts->format('d') }}</div>
            <div style="font-size:10px;text-transform:uppercase;color:var(--pd-muted);">{{ $starts->translatedFormat('M') }}</div>
        </div>
        <div style="flex:1;padding:10px 14px;">
            <div style="font-size:12px;font-weight:600;color:var(--pd-muted);text-decoration:line-through;">{{ $event->title }}</div>
            <div style="font-size:11px;color:var(--pd-muted);margin-top:2px;">{{ $starts->translatedFormat('d M Y') }} · {{ $starts->format('H:i') }}</div>
        </div>
    </div>
    @endforeach
</div>
@endif

@if($canEdit)
<div id="modal-event" x-show="showEventForm" x-cloak
     style="position:fixed;inset:0;z-index:900;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.45);backdrop-filter:blur(2px);padding:20px;"
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
                    <input type="text" name="location" class="pd-input" placeholder="Salle, adresse…">
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="pd-form-group" style="margin-bottom:0;">
                        <label class="pd-label pd-label-req">Début</label>
                        <input type="datetime-local" name="starts_at" class="pd-input" required value="{{ now()->format('Y-m-d\TH:i') }}">
                    </div>
                    <div class="pd-form-group" style="margin-bottom:0;">
                        <label class="pd-label pd-label-req">Fin</label>
                        <input type="datetime-local" name="ends_at" class="pd-input" required value="{{ now()->addHour()->format('Y-m-d\TH:i') }}">
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px;">
                    <div class="pd-form-group" style="margin-bottom:0;">
                        <label class="pd-label">Visibilité</label>
                        <select name="visibility" class="pd-input">
                            <option value="private">🔒 Privé (vous seul)</option>
                            <option value="restricted" selected>🔵 Restreint (membres)</option>
                            <option value="public">🟢 Public (tous)</option>
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

@if($canEdit)
<div id="modal-event-edit" x-show="showEditForm" x-cloak
     style="position:fixed;inset:0;z-index:900;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.45);backdrop-filter:blur(2px);padding:20px;"
     @click="if($event.target===$el) showEditForm=false"
     @keydown.escape.window="showEditForm=false">
    <div class="pd-modal pd-modal-md" @click.stop>
        <div style="background:#0891B2;border-radius:14px 14px 0 0;padding:18px 20px;display:flex;align-items:flex-start;justify-content:space-between;">
            <div>
                <div style="font-size:15px;font-weight:700;color:#fff;">Modifier l'événement</div>
                <div style="font-size:11px;color:rgba(255,255,255,.7);margin-top:2px;" x-text="editEvent.title"></div>
            </div>
            <button type="button" @click="showEditForm=false" style="background:none;border:none;cursor:pointer;color:rgba(255,255,255,.8);font-size:20px;line-height:1;">×</button>
        </div>
        <form id="form-event-edit" method="POST" data-project-id="{{ $project->id }}">
            @csrf @method('PATCH')
            <div class="pd-modal-body">
                <div class="pd-form-group">
                    <label class="pd-label pd-label-req">Titre</label>
                    <input type="text" name="title" id="edit-event-title" class="pd-input" required>
                </div>
                <div class="pd-form-group">
                    <label class="pd-label">Description</label>
                    <textarea name="description" id="edit-event-desc" class="pd-input" rows="2"></textarea>
                </div>
                <div class="pd-form-group">
                    <label class="pd-label">Lieu</label>
                    <input type="text" id="edit-event-location" name="location" class="pd-input">
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="pd-form-group" style="margin-bottom:0;">
                        <label class="pd-label pd-label-req">Début</label>
                        <input type="datetime-local" name="starts_at" id="edit-event-start" class="pd-input" required>
                    </div>
                    <div class="pd-form-group" style="margin-bottom:0;">
                        <label class="pd-label pd-label-req">Fin</label>
                        <input type="datetime-local" name="ends_at" id="edit-event-end" class="pd-input" required>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px;">
                    <div class="pd-form-group" style="margin-bottom:0;">
                        <label class="pd-label">Visibilité</label>
                        <select name="visibility" id="edit-event-vis" class="pd-input">
                            <option value="private">🔒 Privé</option>
                            <option value="restricted">🔵 Restreint</option>
                            <option value="public">🟢 Public</option>
                        </select>
                    </div>
                    <div class="pd-form-group" style="margin-bottom:0;">
                        <label class="pd-label">Couleur</label>
                        <input type="color" name="color" id="edit-event-color" class="pd-input">
                    </div>
                </div>
            </div>
            <div class="pd-modal-footer">
                <button type="button" @click="showEditForm=false" class="pd-btn pd-btn-secondary pd-btn-sm">Annuler</button>
                <button type="submit" class="pd-btn pd-btn-primary pd-btn-sm">Enregistrer</button>
            </div>
        </form>
    </div>
</div>
@endif

</div>
