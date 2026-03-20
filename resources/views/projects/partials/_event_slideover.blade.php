{{-- _event_slideover.blade.php — Visualisation et édition d'un événement projet --}}
{{-- Inclus dans show.blade.php hors des onglets --}}

<div x-data="{
        showView: false,
        viewEvent: {},
        open(detail) { this.viewEvent = detail; this.showView = true; }
     }"
     @open-view-event.window="open($event.detail)"
     @close-event-slideover.window="showView = false">

    <div x-show="showView" x-cloak
         style="position:fixed;inset:0;z-index:8000;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.45);backdrop-filter:blur(2px);padding:20px;"
         @click="if($event.target===$el) showView=false"
         @keydown.escape.window="showView=false"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100">

        <div style="background:var(--pd-surface);border-radius:14px;width:480px;max-width:95vw;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.2);"
             @click.stop
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="transform scale-95 opacity-0"
             x-transition:enter-end="transform scale-100 opacity-100">

            {{-- Header coloré --}}
            <div :style="'background:' + (viewEvent.color || '#1E3A5F')"
                 style="padding:18px 20px;">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;">
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:15px;font-weight:700;color:#fff;line-height:1.3;" x-text="viewEvent.title"></div>
                        <div style="font-size:11px;color:rgba(255,255,255,.75);margin-top:4px;" x-text="viewEvent.starts_at"></div>
                    </div>
                    <button @click="showView=false"
                            style="background:none;border:none;cursor:pointer;color:rgba(255,255,255,.8);font-size:22px;line-height:1;padding:0;margin-left:12px;flex-shrink:0;">×</button>
                </div>
            </div>

            {{-- Corps --}}
            <div style="padding:20px;max-height:60vh;overflow-y:auto;">

                {{-- Horaires --}}
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
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
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
                        <div style="width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;background:var(--pd-surface2);border:0.5px solid var(--pd-border);font-size:16px;flex-shrink:0;">📍</div>
                        <div style="font-size:13px;color:var(--pd-text);" x-text="viewEvent.location"></div>
                    </div>
                </template>

                {{-- Description --}}
                <template x-if="viewEvent.description">
                    <div style="padding:12px 14px;background:var(--pd-surface2);border-radius:8px;border:0.5px solid var(--pd-border);margin-bottom:14px;">
                        <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--pd-muted);margin-bottom:6px;">Description</div>
                        <div style="font-size:13px;color:var(--pd-text);line-height:1.6;white-space:pre-line;" x-text="viewEvent.description"></div>
                    </div>
                </template>

                {{-- Visibilité + créateur --}}
                <div style="font-size:11px;color:var(--pd-muted);line-height:2;">
                    <div>Créé par <strong style="color:var(--pd-text);" x-text="viewEvent.creator"></strong></div>
                    <div>Visibilité : <span x-text="{'private':'🔒 Privé (vous seul)','restricted':'🔵 Restreint (membres)','public':'🟢 Public (tous)'}[viewEvent.visibility]"></span></div>
                </div>
            </div>

            {{-- Footer --}}
            <div style="padding:14px 20px;border-top:0.5px solid var(--pd-border);display:flex;gap:8px;justify-content:flex-end;">
                <button @click="showView=false" class="pd-btn pd-btn-secondary pd-btn-sm">Fermer</button>
                <template x-if="viewEvent.can_edit">
                    <button @click="
                        if (!confirm('Supprimer cet événement ?')) return;
                        const form = document.createElement('form');
                        form.method = 'POST';
                        const pid = document.getElementById('form-event-edit')?.dataset.projectId;
                        form.action = '/projects/' + pid + '/events/' + viewEvent.id;
                        form.innerHTML = '<input name=_token value=\'{{ csrf_token() }}\'><input name=_method value=DELETE>';
                        document.body.appendChild(form);
                        form.submit();
                    " class="pd-btn pd-btn-sm" style="background:#FEE2E2;color:#991B1B;border:0.5px solid #FECACA;">🗑 Supprimer</button>
                </template>
                <template x-if="viewEvent.can_edit">
                    <button @click="
                        showView=false;
                        $nextTick(() => {
                            const form = document.getElementById('form-event-edit');
                            if (!form) return;
                            const pid = form.dataset.projectId;
                            form.action = '/projects/' + pid + '/events/' + viewEvent.id;
                            document.getElementById('edit-event-title').value    = viewEvent.raw_title || '';
                            document.getElementById('edit-event-desc').value     = viewEvent.raw_desc || '';
                            document.getElementById('edit-event-location').value = viewEvent.raw_location || '';
                            document.getElementById('edit-event-start').value    = viewEvent.raw_starts || '';
                            document.getElementById('edit-event-end').value      = viewEvent.raw_ends || '';
                            document.getElementById('edit-event-vis').value      = viewEvent.visibility || 'restricted';
                            document.getElementById('edit-event-color').value    = viewEvent.color || '#1E3A5F';
                            window.dispatchEvent(new CustomEvent('open-edit-event', { detail: viewEvent }));
                        })
                    " class="pd-btn pd-btn-primary pd-btn-sm">✏️ Modifier</button>
                </template>
            </div>

        </div>
    </div>
</div>
