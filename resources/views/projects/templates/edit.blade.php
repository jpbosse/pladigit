@extends('layouts.app')
@section('title', 'Modifier le modèle — ' . $template->name)

@push('styles')
<style>
.tpl-section   { background:var(--pd-surface);border:0.5px solid var(--pd-border);border-radius:10px;padding:18px;margin-bottom:16px; }
.tpl-title     { font-size:14px;font-weight:700;color:var(--pd-navy);margin-bottom:14px;display:flex;align-items:center;justify-content:space-between; }
.tpl-item      { display:flex;align-items:center;gap:10px;padding:9px 12px;border:0.5px solid var(--pd-border);border-radius:8px;margin-bottom:6px;background:var(--pd-surface2); }
.tpl-item-label{ flex:1;font-size:12px;font-weight:500;color:var(--pd-text); }
.tpl-item-meta { font-size:10px;color:var(--pd-muted); }
.tpl-btn-add   { width:100%;padding:8px;font-size:12px;color:var(--pd-navy);background:none;border:0.5px dashed var(--pd-border);border-radius:8px;cursor:pointer;margin-top:4px; }
.tpl-btn-add:hover { border-color:var(--pd-navy);background:var(--pd-bg2); }
.tpl-dot       { width:10px;height:10px;border-radius:50%;flex-shrink:0; }
.tpl-child-indent { margin-left:20px; }
</style>
@endpush

@section('content')
<div style="padding:20px;max-width:800px;" x-data="templateEditor()" x-init="init()">

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;padding-bottom:14px;border-bottom:0.5px solid var(--pd-border);">
    <div>
        <a href="{{ route('projects.templates.index') }}" style="font-size:12px;color:var(--pd-muted);text-decoration:none;">← Modèles</a>
        <h1 style="font-size:20px;font-weight:700;color:var(--pd-navy);margin:6px 0 2px;">{{ $template->name }}</h1>
        <p style="font-size:12px;color:var(--pd-muted);">Définissez la structure type de ce modèle</p>
    </div>
    <div style="display:flex;gap:8px;">
        <button @click="save()" class="pd-btn pd-btn-primary" style="font-size:12px;">💾 Enregistrer</button>
    </div>
</div>

@if(session('success'))
<div style="padding:10px 14px;background:#D1FAE5;color:#065F46;border-radius:8px;margin-bottom:16px;font-size:12px;">{{ session('success') }}</div>
@endif

{{-- ── Infos générales ─────────────────────────────────────────────── --}}
<div class="tpl-section">
    <div class="tpl-title">Informations générales</div>
    <form method="POST" action="{{ route('projects.templates.update', $template) }}" id="form-meta">
        @csrf @method('PUT')
        <input type="hidden" name="milestone_templates" id="input-milestones">
        <input type="hidden" name="task_templates" id="input-tasks">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
            <div>
                <label class="pd-label pd-label-req">Nom</label>
                <input type="text" name="name" class="pd-input" required style="width:100%;"
                       value="{{ $template->name }}">
            </div>
            <div>
                <label class="pd-label">Couleur</label>
                <input type="color" name="color" class="pd-input" style="width:100%;height:38px;"
                       value="{{ $template->color }}">
            </div>
        </div>
        <div>
            <label class="pd-label">Description</label>
            <textarea name="description" class="pd-input" rows="2" style="width:100%;resize:vertical;">{{ $template->description }}</textarea>
        </div>
    </form>
</div>

{{-- ── Builder phases & jalons ─────────────────────────────────────── --}}
<div class="tpl-section">
    <div class="tpl-title">
        <span>Phases &amp; Jalons <span style="font-size:11px;font-weight:400;color:var(--pd-muted);" x-text="'('+milestones.length+' entrée'+(milestones.length>1?'s':'')+')'"></span></span>
    </div>

    <template x-for="(ms, idx) in milestones" :key="idx">
        <div>
            {{-- Phase ou jalon racine --}}
            <div class="tpl-item" x-show="!ms.parent_index && ms.parent_index !== 0">
                <div class="tpl-dot" :style="'background:'+ms.color"></div>
                <div class="tpl-item-label">
                    <span x-text="ms.title"></span>
                    <span class="tpl-item-meta" x-show="ms.is_phase"> · 📦 Phase</span>
                </div>
                <span class="tpl-item-meta" x-text="'J+'+ms.offset_days"></span>
                <button @click="editMilestone(idx)"
                        style="background:none;border:none;cursor:pointer;color:var(--pd-muted);font-size:11px;padding:2px 6px;border:0.5px solid var(--pd-border);border-radius:4px;">✏️</button>
                <button @click="milestones.splice(idx,1)"
                        style="background:none;border:none;cursor:pointer;color:var(--pd-muted);font-size:13px;">×</button>
            </div>
            {{-- Jalon enfant --}}
            <div class="tpl-item tpl-child-indent" x-show="ms.parent_index !== null && ms.parent_index !== undefined">
                <span style="color:var(--pd-muted);font-size:12px;">🏁</span>
                <div class="tpl-item-label" x-text="ms.title"></div>
                <span class="tpl-item-meta" x-text="'J+'+ms.offset_days"></span>
                <button @click="editMilestone(idx)"
                        style="background:none;border:none;cursor:pointer;color:var(--pd-muted);font-size:11px;padding:2px 6px;border:0.5px solid var(--pd-border);border-radius:4px;">✏️</button>
                <button @click="milestones.splice(idx,1)"
                        style="background:none;border:none;cursor:pointer;color:var(--pd-muted);font-size:13px;">×</button>
            </div>
        </div>
    </template>

    <button class="tpl-btn-add" @click="addMilestone(false)">+ Ajouter une phase</button>
    <button class="tpl-btn-add" @click="addMilestone(true)" style="margin-top:4px;">🏁 Ajouter un jalon enfant</button>
</div>

{{-- ── Builder tâches ──────────────────────────────────────────────── --}}
<div class="tpl-section">
    <div class="tpl-title">
        <span>Tâches types <span style="font-size:11px;font-weight:400;color:var(--pd-muted);" x-text="'('+tasks.length+' tâche'+(tasks.length>1?'s':'')+')'"></span></span>
    </div>

    <template x-for="(task, idx) in tasks" :key="idx">
        <div class="tpl-item">
            <div style="width:8px;height:8px;border-radius:50%;flex-shrink:0;"
                 :style="'background:'+({'urgent':'#E24B4A','high':'#D97706','medium':'#3B82F6','low':'#16A34A'}[task.priority]||'#94A3B8')"></div>
            <div class="tpl-item-label" x-text="task.title"></div>
            <span class="tpl-item-meta" x-text="task.estimated_hours ? task.estimated_hours+'h' : ''"></span>
            <span class="tpl-item-meta" x-text="'J+'+task.offset_days"></span>
            <button @click="editTask(idx)"
                    style="background:none;border:none;cursor:pointer;color:var(--pd-muted);font-size:11px;padding:2px 6px;border:0.5px solid var(--pd-border);border-radius:4px;">✏️</button>
            <button @click="tasks.splice(idx,1)"
                    style="background:none;border:none;cursor:pointer;color:var(--pd-muted);font-size:13px;">×</button>
        </div>
    </template>

    <button class="tpl-btn-add" @click="addTask()">+ Ajouter une tâche type</button>
</div>

{{-- ── Modal jalon ─────────────────────────────────────────────────── --}}
<div x-show="msModal.open" x-cloak
     style="position:fixed;inset:0;z-index:9000;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.45);backdrop-filter:blur(2px);"
     @click.self="msModal.open=false">
    <div class="pd-modal pd-modal-sm" style="animation:pd-modal-in .18s ease-out;">
        <div class="pd-modal-header pd-modal-header--colored"
             :class="msModal.isChild ? 'pd-modal-header--orange' : 'pd-modal-header--navy'">
            <div>
                <div class="pd-modal-title" style="font-size:16px;font-weight:700;color:#fff;line-height:1.3;" x-text="msModal.isChild ? '🏁 Jalon enfant' : '📦 Phase / Jalon'"></div>
                <div class="pd-modal-subtitle" style="font-size:12px;color:rgba(255,255,255,.75);margin-top:3px;">Définissez le titre et l'échéance en jours</div>
            </div>
            <button type="button" @click="msModal.open=false" class="pd-modal-close" style="background:none;border:none;cursor:pointer;color:rgba(255,255,255,.8);font-size:22px;line-height:1;padding:0 2px;margin-left:12px;flex-shrink:0;">×</button>
        </div>
        <div class="pd-modal-body">
        <div style="margin-bottom:10px;">
            <label class="pd-label pd-label-req">Titre</label>
            <input type="text" x-model="msModal.title" class="pd-input" style="width:100%;" required>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
            <div>
                <label class="pd-label">Échéance (jours depuis démarrage)</label>
                <input type="number" x-model.number="msModal.offset_days" class="pd-input" min="1" style="width:100%;">
            </div>
            <div>
                <label class="pd-label">Couleur</label>
                <input type="color" x-model="msModal.color" class="pd-input" style="width:100%;height:38px;">
            </div>
        </div>
        <template x-if="!msModal.isChild && milestones.filter(m=>!m.parent_index&&m.parent_index!==0).length > 0">
            <div style="margin-bottom:10px;">
                <label class="pd-label">Phase parente (laisser vide = phase racine)</label>
                <select x-model.number="msModal.parent_index" class="pd-input" style="width:100%;">
                    <option :value="null">— Phase autonome —</option>
                    <template x-for="(ms,i) in milestones.filter(m=>!m.parent_index&&m.parent_index!==0)" :key="i">
                        <option :value="milestones.indexOf(ms)" x-text="ms.title"></option>
                    </template>
                </select>
            </div>
        </template>
        </div>
                <div class="pd-modal-footer">
            <button @click="msModal.open=false" class="pd-btn pd-btn-secondary pd-btn-sm">Annuler</button>
            <button @click="saveMilestone()" class="pd-btn pd-btn-primary pd-btn-sm">Enregistrer</button>
        </div>
    </div>
</div>

{{-- ── Modal tâche ─────────────────────────────────────────────────── --}}
<div x-show="taskModal.open" x-cloak
     style="position:fixed;inset:0;z-index:9000;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.45);backdrop-filter:blur(2px);"
     @click.self="taskModal.open=false">
    <div class="pd-modal pd-modal-md" style="animation:pd-modal-in .18s ease-out;">
        <div class="pd-modal-header" style="background:#0891B2;border-radius:14px 14px 0 0;padding:20px 20px 16px;border-bottom:none;display:flex;align-items:flex-start;justify-content:space-between;">
            <div>
                <div class="pd-modal-title" style="font-size:16px;font-weight:700;color:#fff;line-height:1.3;">✓ Tâche type</div>
                <div class="pd-modal-subtitle" style="font-size:12px;color:rgba(255,255,255,.75);margin-top:3px;">Définissez la tâche et ses paramètres par défaut</div>
            </div>
            <button type="button" @click="taskModal.open=false" class="pd-modal-close" style="background:none;border:none;cursor:pointer;color:rgba(255,255,255,.8);font-size:22px;line-height:1;padding:0 2px;margin-left:12px;flex-shrink:0;">×</button>
        </div>
        <div style="margin-bottom:10px;">
            <label class="pd-label pd-label-req">Titre</label>
            <input type="text" x-model="taskModal.title" class="pd-input" style="width:100%;" required>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:10px;">
            <div>
                <label class="pd-label">Priorité</label>
                <select x-model="taskModal.priority" class="pd-input" style="width:100%;">
                    <option value="urgent">Urgente</option>
                    <option value="high">Haute</option>
                    <option value="medium" selected>Moyenne</option>
                    <option value="low">Basse</option>
                </select>
            </div>
            <div>
                <label class="pd-label">Échéance (J+)</label>
                <input type="number" x-model.number="taskModal.offset_days" class="pd-input" min="1" style="width:100%;">
            </div>
            <div>
                <label class="pd-label">Heures est.</label>
                <input type="number" x-model.number="taskModal.estimated_hours" class="pd-input" min="0" step="0.5" style="width:100%;">
            </div>
        </div>
        <template x-if="milestones.length > 0">
            <div style="margin-bottom:10px;">
                <label class="pd-label">Rattacher à un jalon</label>
                <select x-model.number="taskModal.milestone_index" class="pd-input" style="width:100%;">
                    <option :value="null">— Sans jalon —</option>
                    <template x-for="(ms, i) in milestones" :key="i">
                        <option :value="i" x-text="(ms.parent_index!==null&&ms.parent_index!==undefined?'  🏁 ':'📦 ')+ms.title"></option>
                    </template>
                </select>
            </div>
        </template>
        <div style="margin-bottom:10px;">
            <label class="pd-label">Description</label>
            <textarea x-model="taskModal.description" class="pd-input" rows="2" style="width:100%;resize:vertical;"></textarea>
        </div>
        <div class="pd-modal-footer">
            <button @click="taskModal.open=false" class="pd-btn pd-btn-secondary pd-btn-sm">Annuler</button>
            <button @click="saveTask()" class="pd-btn pd-btn-primary pd-btn-sm">Enregistrer</button>
        </div>
    </div>
</div>

</div>

<script>
function templateEditor() {
    return {
        milestones: @json($template->milestone_templates ?? []),
        tasks:      @json($template->task_templates ?? []),

        msModal: { open:false, idx:null, isChild:false, title:'', color:'#EA580C', offset_days:30, parent_index:null },
        taskModal: { open:false, idx:null, title:'', priority:'medium', offset_days:7, estimated_hours:null, milestone_index:null, description:'' },

        init() {},

        addMilestone(isChild) {
            this.msModal = { open:true, idx:null, isChild, title:'', color: isChild ? '#EA580C' : '#1E3A5F', offset_days:30, parent_index:null };
        },
        editMilestone(idx) {
            const ms = this.milestones[idx];
            this.msModal = { open:true, idx, isChild: ms.parent_index !== null && ms.parent_index !== undefined,
                title:ms.title, color:ms.color||'#EA580C', offset_days:ms.offset_days||30, parent_index:ms.parent_index??null };
        },
        saveMilestone() {
            if (!this.msModal.title.trim()) return;
            const entry = {
                title: this.msModal.title,
                color: this.msModal.color,
                offset_days: this.msModal.offset_days,
                parent_index: this.msModal.parent_index !== '' ? this.msModal.parent_index : null,
            };
            if (this.msModal.idx !== null) {
                this.milestones[this.msModal.idx] = entry;
            } else {
                this.milestones.push(entry);
            }
            this.msModal.open = false;
        },

        addTask() {
            this.taskModal = { open:true, idx:null, title:'', priority:'medium', offset_days:7, estimated_hours:null, milestone_index:null, description:'' };
        },
        editTask(idx) {
            const t = this.tasks[idx];
            this.taskModal = { open:true, idx, title:t.title, priority:t.priority||'medium',
                offset_days:t.offset_days||7, estimated_hours:t.estimated_hours||null,
                milestone_index:t.milestone_index??null, description:t.description||'' };
        },
        saveTask() {
            if (!this.taskModal.title.trim()) return;
            const entry = {
                title: this.taskModal.title,
                priority: this.taskModal.priority,
                offset_days: this.taskModal.offset_days,
                estimated_hours: this.taskModal.estimated_hours || null,
                milestone_index: this.taskModal.milestone_index !== '' ? this.taskModal.milestone_index : null,
                description: this.taskModal.description || null,
            };
            if (this.taskModal.idx !== null) {
                this.tasks[this.taskModal.idx] = entry;
            } else {
                this.tasks.push(entry);
            }
            this.taskModal.open = false;
        },

        save() {
            document.getElementById('input-milestones').value = JSON.stringify(this.milestones);
            document.getElementById('input-tasks').value = JSON.stringify(this.tasks);
            document.getElementById('form-meta').submit();
        },
    };
}
</script>
@endsection
