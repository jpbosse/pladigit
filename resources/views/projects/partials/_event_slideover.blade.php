{{-- _event_slideover.blade.php — Visualisation d'un événement projet --}}
{{-- Inclus directement dans show.blade.php, hors des onglets --}}

<div x-data="{
        showView: false,
        viewEvent: {},
        open(detail) { this.viewEvent = detail; this.showView = true; }
     }"
     @open-view-event.window="open($event.detail)"
     @close-event-slideover.window="showView = false">

    {{-- Slideover --}}
    <div x-show="showView" x-cloak
         style="position:fixed;inset:0;z-index:8000;display:flex;justify-content:flex-end;"
         @keydown.escape.window="showView=false">

        {{-- Backdrop --}}
        <div @click="showView=false"
             style="position:absolute;inset:0;background:rgba(0,0,0,.3);"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"></div>

        {{-- Panel --}}
        <div style="position:relative;width:420px;max-width:95vw;height:100%;background:var(--pd-surface);box-shadow:-4px 0 24px rgba(0,0,0,.15);display:flex;flex-direction:column;overflow:hidden;"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="transform translate-x-full"
             x-transition:enter-end="transform translate-x-0">

            {{-- Header coloré --}}
            <div :style="'background:' + (viewEvent.color || '#1E3A5F')"
                 style="padding:18px 20px;flex-shrink:0;">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;">
                    <div>
                        <div style="font-size:15px;font-weight:700;color:#fff;line-height:1.3;" x-text="viewEvent.title"></div>
                        <div style="font-size:11px;color:rgba(255,255,255,.75);margin-top:4px;" x-text="viewEvent.starts_at"></div>
                    </div>
                    <button @click="showView=false"
                            style="background:none;border:none;cursor:pointer;color:rgba(255,255,255,.8);font-size:22px;line-height:1;padding:0;margin-left:12px;flex-shrink:0;">×</button>
                </div>
            </div>

            {{-- Corps --}}
            <div style="flex:1;overflow-y:auto;padding:20px;">

                {{-- Horaires --}}
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
                    <div style="width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;background:var(--pd-surface2);border:0.5px solid var(--pd-border);font-size:16px;flex-shrink:0;">🕐</div>
                    <div>
                        <div style="font-size:13px;font-weight:500;color:var(--pd-text);" x-text="viewEvent.starts_at"></div>
                        <template x-if="!viewEvent.all_day">
                            <div style="font-size:11px;color:var(--pd-muted);">Fin : <span x-text="viewEvent.ends_at"></span></div>
                        </template>
                    </div>
                </div>

                {{-- Lieu --}}
                <template x-if="viewEvent.location">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
                        <div style="width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;background:var(--pd-surface2);border:0.5px solid var(--pd-border);font-size:16px;flex-shrink:0;">📍</div>
                        <div style="font-size:13px;color:var(--pd-text);" x-text="viewEvent.location"></div>
                    </div>
                </template>

                {{-- Description --}}
                <template x-if="viewEvent.description">
                    <div style="padding:12px 14px;background:var(--pd-surface2);border-radius:8px;border:0.5px solid var(--pd-border);margin-bottom:16px;">
                        <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--pd-muted);margin-bottom:6px;">Description</div>
                        <div style="font-size:13px;color:var(--pd-text);line-height:1.6;white-space:pre-line;" x-text="viewEvent.description"></div>
                    </div>
                </template>

                {{-- Visibilité + créateur --}}
                <div style="font-size:11px;color:var(--pd-muted);line-height:2;">
                    <div>Créé par <strong style="color:var(--pd-text);" x-text="viewEvent.creator"></strong></div>
                    <div>Visibilité : <span x-text="{'private':'Privé','restricted':'Restreint','public':'Public'}[viewEvent.visibility]"></span></div>
                </div>
            </div>

            {{-- Footer --}}
            <div style="padding:14px 20px;border-top:0.5px solid var(--pd-border);display:flex;gap:8px;justify-content:flex-end;flex-shrink:0;">
                <button @click="showView=false" class="pd-btn pd-btn-secondary pd-btn-sm">Fermer</button>
                <template x-if="viewEvent.can_edit">
                    <button @click="showView=false; $nextTick(() => openEditEvent(viewEvent.id, viewEvent.raw_title, viewEvent.raw_desc, viewEvent.raw_location, viewEvent.raw_starts, viewEvent.raw_ends, viewEvent.visibility, viewEvent.color))"
                            class="pd-btn pd-btn-primary pd-btn-sm">✏️ Modifier</button>
                </template>
            </div>
        </div>
    </div>
</div>
