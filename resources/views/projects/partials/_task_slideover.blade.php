{{-- resources/views/projects/partials/_task_slideover.blade.php --}}
{{-- Slide-over tâche — refactorisé avec classes pd-slideover-* et pd-form-* --}}

<div x-data="taskSlideover()"
     x-cloak
     @open-task.window="openTask($event.detail.taskId)"
     @open-new-task.window="openNew($event.detail.status, $event.detail.milestoneId)"
     x-show="open"
     class="pd-slideover-overlay">

    {{-- Backdrop --}}
    <div x-show="open" x-cloak
         @click="close()"
         class="pd-slideover-backdrop"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
    </div>

    {{-- Panneau --}}
    <div x-show="open" x-cloak
         class="pd-slideover-panel"
         x-transition:enter="transform transition ease-out duration-200"
         x-transition:enter-start="translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transform transition ease-in duration-150"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="translate-x-full">

        {{-- Header --}}
        <div class="pd-slideover-header">
            <div>
                <div class="pd-slideover-title"
                     x-text="newMode ? 'Nouvelle tâche' : (editMode ? 'Modifier la tâche' : (taskData?.title || 'Chargement…'))">
                </div>
                <div style="font-size:11px;color:var(--pd-muted);margin-top:2px;"
                     x-show="!newMode && taskData"
                     x-text="taskData ? ('Projet : ' + '{{ $project->name }}') : ''">
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:6px;">
                @if(isset($canEdit) && $canEdit)
                <template x-if="!newMode && !loading && taskData && !editMode">
                    <button @click="editMode = true" class="pd-btn pd-btn-sm pd-btn-secondary">
                        Modifier
                    </button>
                </template>
                <template x-if="editMode">
                    <button @click="editMode = false" class="pd-btn pd-btn-sm pd-btn-secondary">
                        Annuler
                    </button>
                </template>
                @endif
                <button @click="close()" class="pd-modal-close" aria-label="Fermer">×</button>
            </div>
        </div>

        {{-- Corps --}}
        <div class="pd-slideover-body">

            {{-- Chargement --}}
            <div x-show="loading"
                 style="text-align:center;padding:50px 20px;color:var(--pd-muted);">
                <div style="font-size:13px;">Chargement…</div>
            </div>

            {{-- Erreur --}}
            <div x-show="loadError && !loading" x-cloak
                 style="text-align:center;padding:40px 20px;">
                <div style="font-size:28px;margin-bottom:12px;opacity:.4;">⚠</div>
                <div style="font-size:14px;font-weight:600;margin-bottom:6px;">Impossible de charger la tâche</div>
                <div style="font-size:12px;color:var(--pd-muted);margin-bottom:16px;">Vérifiez votre connexion ou rechargez la page.</div>
                <button @click="close()" class="pd-btn pd-btn-secondary pd-btn-sm">Fermer</button>
            </div>

            {{-- ── FORMULAIRE CRÉATION ─────────────────────────────── --}}
            <template x-if="!loading && newMode">
                <form @submit.prevent="submitNew()" autocomplete="off">

                    <div class="pd-form-group">
                        <label class="pd-label pd-label-req">Titre</label>
                        <input type="text" x-model="newTask.title" class="pd-input"
                               required autofocus placeholder="Titre de la tâche">
                    </div>

                    <div class="pd-form-row pd-form-row-2">
                        <div class="pd-form-group" style="margin-bottom:0;">
                            <label class="pd-label">Statut</label>
                            <select x-model="newTask.status" class="pd-input">
                                <option value="todo">À faire</option>
                                <option value="in_progress">En cours</option>
                                <option value="in_review">En revue</option>
                                <option value="done">Terminé</option>
                            </select>
                        </div>
                        <div class="pd-form-group" style="margin-bottom:0;">
                            <label class="pd-label">Priorité</label>
                            <select x-model="newTask.priority" class="pd-input">
                                <option value="low">Basse</option>
                                <option value="medium">Moyenne</option>
                                <option value="high">Haute</option>
                                <option value="urgent">Urgente</option>
                            </select>
                        </div>
                    </div>

                    <div class="pd-form-group">
                        <label class="pd-label">Assigné à</label>
                        <select x-model="newTask.assigned_to" class="pd-input">
                            <option value="">— Non assigné —</option>
                            @foreach($project->projectMembers->sortBy('user.name') as $pm)
                            <option value="{{ $pm->user_id }}">{{ $pm->user->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="pd-form-row pd-form-row-2">
                        <div class="pd-form-group" style="margin-bottom:0;">
                            <label class="pd-label">Date de début</label>
                            <input type="date" x-model="newTask.start_date" class="pd-input">
                        </div>
                        <div class="pd-form-group" style="margin-bottom:0;">
                            <label class="pd-label">Échéance</label>
                            <input type="date" x-model="newTask.due_date" class="pd-input">
                        </div>
                    </div>

                    <div class="pd-form-group">
                        <label class="pd-label">Jalon</label>
                        <select x-model="newTask.milestone_id" class="pd-input">
                            <option value="">— Aucun jalon —</option>
                            @foreach($project->milestones as $ms)
                            @if($ms->isPhase() && $ms->children->isNotEmpty())
                            <optgroup label="📦 {{ $ms->title }}">
                                @foreach($ms->children as $child)
                                <option value="{{ $child->id }}">🏁 {{ $child->title }}</option>
                                @endforeach
                            </optgroup>
                            @else
                            <option value="{{ $ms->id }}">🏁 {{ $ms->title }}</option>
                            @endif
                            @endforeach
                        </select>
                    </div>

                    <div class="pd-form-group">
                        <label class="pd-label">Description</label>
                        <textarea x-model="newTask.description" class="pd-input" rows="3"
                                  placeholder="Description optionnelle…"></textarea>
                    </div>

                    <div class="pd-form-row pd-form-row-2">
                        <div class="pd-form-group" style="margin-bottom:0;">
                            <label class="pd-label">Heures estimées</label>
                            <input type="number" x-model.number="newTask.estimated_hours"
                                   class="pd-input" min="0" step="0.5" placeholder="0">
                        </div>
                        <div class="pd-form-group" style="margin-bottom:0;">
                            <label class="pd-label">Heures réalisées</label>
                            <input type="number" x-model.number="newTask.actual_hours"
                                   class="pd-input" min="0" step="0.5" placeholder="0">
                        </div>
                    </div>

                    <span class="pd-form-section">Récurrence</span>
                    <div class="pd-form-row pd-form-row-3">
                        <div class="pd-form-group" style="margin-bottom:0;">
                            <label class="pd-label">Fréquence</label>
                            <select x-model="newTask.recurrence_type" class="pd-input">
                                <option value="">Pas de récurrence</option>
                                <option value="daily">Quotidienne</option>
                                <option value="weekly">Hebdomadaire</option>
                                <option value="monthly">Mensuelle</option>
                            </select>
                        </div>
                        <div class="pd-form-group" style="margin-bottom:0;" x-show="newTask.recurrence_type">
                            <label class="pd-label">Tous les</label>
                            <input type="number" x-model.number="newTask.recurrence_every" class="pd-input" min="1" max="52" value="1">
                        </div>
                        <div class="pd-form-group" style="margin-bottom:0;" x-show="newTask.recurrence_type">
                            <label class="pd-label">Fin de récurrence</label>
                            <input type="date" x-model="newTask.recurrence_ends" class="pd-input">
                        </div>
                    </div>
                        <button type="button" @click="close()"
                                class="pd-btn pd-btn-secondary pd-btn-sm">Annuler</button>
                        <button type="submit"
                                class="pd-btn pd-btn-primary pd-btn-sm">Créer la tâche</button>
                    </div>
                </form>
            </template>

            {{-- ── MODE CONSULTATION ───────────────────────────────── --}}
            <template x-if="!loading && !newMode && !editMode && taskData">
                <div>
                    {{-- Badges --}}
                    <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:16px;">
                        <span class="pd-badge"
                              :style="statusStyle(taskData.status)"
                              x-text="statusLabel(taskData.status)"></span>
                        <span class="pd-badge"
                              :style="prioStyle(taskData.priority)"
                              x-text="prioLabel(taskData.priority)"></span>
                    </div>

                    {{-- Description --}}
                    <div style="font-size:13px;color:var(--pd-text);line-height:1.7;margin-bottom:16px;"
                         x-text="taskData.description || 'Aucune description.'"></div>

                    {{-- Méta grid --}}
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:20px;">
                        @foreach([
                            ['Assigné','assignee?.name'],
                            ['Jalon','milestone?.title'],
                            ['Début','start_date'],
                            ['Échéance','due_date'],
                            ['Estimé','estimated_hours'],
                            ['Réalisé','actual_hours'],
                        ] as [$lbl, $field])
                        <div style="background:var(--pd-surface);padding:10px 12px;border-radius:8px;border:0.5px solid var(--pd-border);">
                            <div class="pd-label" style="margin-bottom:4px;">{{ $lbl }}</div>
                            <div style="font-size:13px;font-weight:500;"
                                 x-text="taskData.{{ $field }} ?? '—'"></div>
                        </div>
                        @endforeach
                    </div>

                    {{-- ── Dépendances Fin→Fin --}}
                    <div style="margin-bottom:20px;border:0.5px solid var(--pd-border);border-radius:10px;overflow:hidden;">
                        <div style="background:var(--pd-surface2);padding:10px 14px;display:flex;align-items:center;justify-content:space-between;">
                            <span style="font-size:12px;font-weight:600;color:var(--pd-navy);">
                                🔗 Dépendances
                                <span style="font-size:11px;font-weight:400;color:var(--pd-muted);"> — doit être terminée après :</span>
                            </span>
                            @if(isset($canEdit) && $canEdit)
                            <button type="button" @click="depPickerOpen = !depPickerOpen"
                                    style="font-size:11px;padding:3px 8px;border:0.5px solid var(--pd-border);border-radius:5px;background:var(--pd-surface);cursor:pointer;color:var(--pd-navy);">
                                + Ajouter
                            </button>
                            @endif
                        </div>

                        <template x-if="taskData.predecessors && taskData.predecessors.length > 0">
                            <div style="padding:8px 14px;">
                                <template x-for="pred in taskData.predecessors" :key="pred.id">
                                    <div style="display:flex;align-items:center;justify-content:space-between;padding:6px 0;border-bottom:0.5px solid var(--pd-border);">
                                        <div style="display:flex;align-items:center;gap:8px;">
                                            <span x-text="pred.status === 'done' ? '✅' : '⏳'"></span>
                                            <span style="font-size:13px;" x-text="pred.title"></span>
                                            <span :style="statusStyle(pred.status)" style="font-size:10px;padding:2px 6px;border-radius:4px;" x-text="statusLabel(pred.status)"></span>
                                        </div>
                                        @if(isset($canEdit) && $canEdit)
                                        <button type="button" @click="removeDependency(pred.id)"
                                                style="font-size:11px;color:var(--pd-danger);background:none;border:none;cursor:pointer;padding:2px 6px;"
                                                title="Supprimer">✕</button>
                                        @endif
                                    </div>
                                </template>
                            </div>
                        </template>

                        <template x-if="!taskData.predecessors || taskData.predecessors.length === 0">
                            <div style="padding:10px 14px;font-size:12px;color:var(--pd-muted);">Aucune dépendance.</div>
                        </template>

                        @if(isset($canEdit) && $canEdit)
                        <template x-if="depPickerOpen">
                            <div style="padding:10px 14px;border-top:0.5px solid var(--pd-border);background:var(--pd-surface);">
                                <div style="font-size:11px;color:var(--pd-muted);margin-bottom:6px;">Tâche prédécesseur :</div>
                                <select x-model="depSelectedId" style="width:100%;padding:7px 8px;border:0.5px solid var(--pd-border);border-radius:6px;font-size:12px;margin-bottom:8px;">
                                    <option value="">— choisir une tâche —</option>
                                    @foreach($project->tasks()->with('milestone')->orderBy('title')->get() as $t)
                                    <option value="{{ $t->id }}">{{ $t->title }}{{ $t->milestone ? ' ('.$t->milestone->title.')' : '' }}</option>
                                    @endforeach
                                </select>
                                <div style="display:flex;gap:6px;">
                                    <button type="button" @click="addDependency()" style="flex:1;padding:7px;background:var(--pd-navy);color:#fff;border:none;border-radius:6px;font-size:12px;cursor:pointer;font-weight:600;">✓ Confirmer</button>
                                    <button type="button" @click="depPickerOpen = false" style="padding:7px 12px;background:var(--pd-surface2);border:0.5px solid var(--pd-border);border-radius:6px;font-size:12px;cursor:pointer;">Annuler</button>
                                </div>
                                <div x-show="depError" x-text="depError" style="margin-top:6px;font-size:12px;color:var(--pd-danger);"></div>
                            </div>
                        </template>
                        @endif
                    </div>

                    {{-- Documents de la tâche --}}
                    <div style="border-top:0.5px solid var(--pd-border);padding-top:14px;margin-bottom:0;"
                         x-data="{
                             taskDocs: [], taskDocsLoaded: false, taskDocsOpen: false,
                             showTaskUpload: false, showTaskLink: false,
                             taskUploadError: '', taskLinkError: '', taskUploading: false,
                             taskLinkForm: { name: '', path: '' },
                             csrfToken() { return document.querySelector('meta[name=csrf-token]').content; },
                             async loadTaskDocs(taskId, projectId) {
                                 if (this.taskDocsLoaded) return;
                                 const r = await fetch('/projects/'+projectId+'/documents?task_id='+taskId, { headers: { 'Accept': 'application/json' } });
                                 const d = await r.json();
                                 this.taskDocs = d.documents ?? [];
                                 this.taskDocsLoaded = true;
                             },
                             async deleteTaskDoc(docId, projectId) {
                                 if (!confirm('Supprimer ?')) return;
                                 await fetch('/projects/'+projectId+'/documents/'+docId, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': this.csrfToken(), 'Accept': 'application/json' } });
                                 this.taskDocs = this.taskDocs.filter(d => d.id !== docId);
                             },
                             async uploadTaskFile(taskId, projectId) {
                                 this.taskUploadError = '';
                                 const fi = this.$refs.taskFileInput;
                                 if (!fi?.files?.length) { this.taskUploadError = 'Fichier requis.'; return; }
                                 this.taskUploading = true;
                                 const fd = new FormData();
                                 fd.append('file', fi.files[0]);
                                 fd.append('task_id', taskId);
                                 fd.append('_token', this.csrfToken());
                                 const r = await fetch('/projects/'+projectId+'/documents/upload', { method: 'POST', body: fd });
                                 const d = await r.json();
                                 if (d.success) { this.taskDocs.unshift(d.document); this.showTaskUpload = false; fi.value = ''; }
                                 else { this.taskUploadError = d.message ?? 'Erreur.'; }
                                 this.taskUploading = false;
                             },
                             async saveTaskLink(taskId, projectId) {
                                 this.taskLinkError = '';
                                 if (!this.taskLinkForm.name || !this.taskLinkForm.path) { this.taskLinkError = 'Nom et URL requis.'; return; }
                                 const body = new URLSearchParams({ _token: this.csrfToken(), task_id: taskId, name: this.taskLinkForm.name, path: this.taskLinkForm.path });
                                 const r = await fetch('/projects/'+projectId+'/documents/link', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() });
                                 const d = await r.json();
                                 if (d.success) { this.taskDocs.unshift(d.document); this.showTaskLink = false; this.taskLinkForm = { name: '', path: '' }; }
                                 else { this.taskLinkError = d.message ?? 'Erreur.'; }
                             },
                         }"
                         x-init="$watch('taskData', async (v) => { if (v?.id) { taskDocsLoaded=false; taskDocs=[]; await loadTaskDocs(v.id, v.project_id); } })">
                        <button @click="taskDocsOpen=!taskDocsOpen"
                                style="width:100%;display:flex;align-items:center;justify-content:space-between;background:none;border:none;cursor:pointer;padding:0 0 10px 0;">
                            <span class="pd-label" style="margin:0;">📎 Documents (<span x-text="taskDocs.length"></span>)</span>
                            <span :style="taskDocsOpen?'':'transform:rotate(-90deg)'" style="transition:transform .2s;color:var(--pd-muted);font-size:13px;">▾</span>
                        </button>
                        <div x-show="taskDocsOpen">
                            <template x-for="doc in taskDocs" :key="doc.id">
                            <div style="display:flex;align-items:center;gap:8px;padding:6px 10px;background:var(--pd-surface2);border-radius:6px;margin-bottom:4px;font-size:12px;">
                                <span x-text="doc.icon"></span>
                                <span x-text="doc.name" style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></span>
                                <a :href="doc.download_url" style="color:var(--pd-accent);font-size:11px;text-decoration:none;" :target="doc.type===\'link\'?\'_blank\':\'_self\'">
                                    <span x-text="doc.type===\'link\' ? \'Ouvrir\' : \'⬇\'"></span>
                                </a>
                                <button @click="deleteTaskDoc(doc.id, taskData.project_id)" style="font-size:11px;background:none;border:none;cursor:pointer;color:var(--pd-danger);">🗑</button>
                            </div>
                            </template>
                            <div x-show="taskDocs.length===0" style="font-size:12px;color:var(--pd-muted);margin-bottom:8px;">Aucun document joint.</div>
                            <div style="display:flex;gap:6px;margin-top:6px;">
                                <button @click="showTaskUpload=!showTaskUpload;showTaskLink=false" style="font-size:11px;padding:3px 8px;border-radius:5px;border:0.5px solid var(--pd-border);background:var(--pd-surface);cursor:pointer;">📎 Fichier</button>
                                <button @click="showTaskLink=!showTaskLink;showTaskUpload=false" style="font-size:11px;padding:3px 8px;border-radius:5px;border:0.5px solid var(--pd-border);background:var(--pd-surface);cursor:pointer;">🔗 Lien</button>
                            </div>
                            <div x-show="showTaskUpload" style="margin-top:8px;display:flex;flex-direction:column;gap:6px;">
                                <input type="file" x-ref="taskFileInput" class="pd-input" style="padding:4px;" accept=".pdf,.doc,.docx,.xls,.xlsx,.odt,.ods,.txt,.csv,.zip,.png,.jpg">
                                <div style="display:flex;gap:6px;align-items:center;">
                                    <button @click="uploadTaskFile(taskData.id, taskData.project_id)" :disabled="taskUploading" class="pd-btn pd-btn-sm pd-btn-primary" x-text="taskUploading ? 'Envoi…' : 'Joindre'"></button>
                                    <span x-show="taskUploadError" x-text="taskUploadError" style="font-size:11px;color:var(--pd-danger);"></span>
                                </div>
                            </div>
                            <div x-show="showTaskLink" style="margin-top:8px;display:flex;flex-direction:column;gap:6px;">
                                <input type="text" x-model="taskLinkForm.name" class="pd-input" placeholder="Nom du lien">
                                <input type="url" x-model="taskLinkForm.path" class="pd-input" placeholder="https://…">
                                <div style="display:flex;gap:6px;align-items:center;">
                                    <button @click="saveTaskLink(taskData.id, taskData.project_id)" class="pd-btn pd-btn-sm pd-btn-primary">Enregistrer</button>
                                    <span x-show="taskLinkError" x-text="taskLinkError" style="font-size:11px;color:var(--pd-danger);"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Commentaires --}}
                    <div style="border-top:0.5px solid var(--pd-border);padding-top:16px;">
                        <div class="pd-label" style="margin-bottom:12px;">
                            Commentaires (<span x-text="comments.length"></span>)
                        </div>

                        <template x-for="comment in comments" :key="comment.id">
                            <div style="margin-bottom:8px;padding:10px 12px;background:var(--pd-surface);border-radius:8px;border:0.5px solid var(--pd-border);">
                                <div style="display:flex;justify-content:space-between;margin-bottom:5px;">
                                    <span style="font-size:12px;font-weight:600;" x-text="comment.author"></span>
                                    <span style="font-size:11px;color:var(--pd-muted);" x-text="comment.created_at"></span>
                                </div>
                                <div style="font-size:13px;line-height:1.5;" x-text="comment.body"></div>
                            </div>
                        </template>

                        <div x-show="comments.length === 0"
                             style="font-size:13px;color:var(--pd-muted);margin-bottom:12px;">
                            Aucun commentaire.
                        </div>

                        @if(isset($canEdit) && $canEdit)
                        <form @submit.prevent="submitComment()"
                              style="display:flex;gap:8px;margin-top:12px;">
                            <input type="text"
                                   x-model="newComment"
                                   placeholder="Ajouter un commentaire…"
                                   class="pd-input"
                                   style="flex:1;">
                            <button type="submit" class="pd-btn pd-btn-sm pd-btn-primary">
                                Envoyer
                            </button>
                        </form>
                        @endif
                    </div>
                </div>
            </template>

            {{-- ── FORMULAIRE MODIFICATION ─────────────────────────── --}}
            <template x-if="!loading && editMode && taskData">
                <form @submit.prevent="submitUpdate()" autocomplete="off">

                    <div class="pd-form-group">
                        <label class="pd-label pd-label-req">Titre</label>
                        <input type="text" x-model="taskData.title" class="pd-input" required>
                    </div>

                    <div class="pd-form-row pd-form-row-2">
                        <div class="pd-form-group" style="margin-bottom:0;">
                            <label class="pd-label">Statut</label>
                            <select x-model="taskData.status" class="pd-input">
                                <option value="todo">À faire</option>
                                <option value="in_progress">En cours</option>
                                <option value="in_review">En revue</option>
                                <option value="done">Terminé</option>
                            </select>
                        </div>
                        <div class="pd-form-group" style="margin-bottom:0;">
                            <label class="pd-label">Priorité</label>
                            <select x-model="taskData.priority" class="pd-input">
                                <option value="low">Basse</option>
                                <option value="medium">Moyenne</option>
                                <option value="high">Haute</option>
                                <option value="urgent">Urgente</option>
                            </select>
                        </div>
                    </div>

                    <div class="pd-form-group">
                        <label class="pd-label">Assigné à</label>
                        <select x-model="taskData.assigned_to" class="pd-input">
                            <option value="">— Non assigné —</option>
                            @foreach($project->projectMembers->sortBy('user.name') as $pm)
                            <option value="{{ $pm->user_id }}">{{ $pm->user->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="pd-form-row pd-form-row-2">
                        <div class="pd-form-group" style="margin-bottom:0;">
                            <label class="pd-label">Date de début</label>
                            <input type="date" x-model="taskData.start_date" class="pd-input">
                        </div>
                        <div class="pd-form-group" style="margin-bottom:0;">
                            <label class="pd-label">Échéance</label>
                            <input type="date" x-model="taskData.due_date" class="pd-input">
                        </div>
                    </div>

                    <div class="pd-form-group">
                        <label class="pd-label">Jalon</label>
                        <select x-model="taskData.milestone_id" class="pd-input">
                            <option value="">— Aucun jalon —</option>
                            @foreach($project->milestones as $ms)
                            @if($ms->isPhase() && $ms->children->isNotEmpty())
                            <optgroup label="📦 {{ $ms->title }}">
                                @foreach($ms->children as $child)
                                <option value="{{ $child->id }}">🏁 {{ $child->title }}</option>
                                @endforeach
                            </optgroup>
                            @else
                            <option value="{{ $ms->id }}">🏁 {{ $ms->title }}</option>
                            @endif
                            @endforeach
                        </select>
                    </div>

                    <div class="pd-form-group">
                        <label class="pd-label">Description</label>
                        <textarea x-model="taskData.description" class="pd-input" rows="3"></textarea>
                    </div>

                    <div class="pd-form-row pd-form-row-2">
                        <div class="pd-form-group" style="margin-bottom:0;">
                            <label class="pd-label">Heures estimées</label>
                            <input type="number" x-model.number="taskData.estimated_hours"
                                   class="pd-input" min="0" step="0.5">
                        </div>
                        <div class="pd-form-group" style="margin-bottom:0;">
                            <label class="pd-label">Heures réalisées</label>
                            <input type="number" x-model.number="taskData.actual_hours"
                                   class="pd-input" min="0" step="0.5">
                        </div>
                    </div>

                    <div class="pd-form-actions" style="margin-top:20px;display:flex;justify-content:space-between;align-items:center;">
                        <button type="button" @click="deleteTask()"
                                class="pd-btn pd-btn-danger pd-btn-sm"
                                style="background:#FEE2E2;color:#991B1B;border-color:#FCA5A5;">
                            🗑 Supprimer
                        </button>
                        <div style="display:flex;gap:8px;">
                            <button type="button" @click="editMode = false"
                                    class="pd-btn pd-btn-secondary pd-btn-sm">Annuler</button>
                            <button type="submit"
                                    class="pd-btn pd-btn-primary pd-btn-sm">Enregistrer</button>
                        </div>
                    </div>
                </form>
            </template>

        </div>{{-- /pd-slideover-body --}}
    </div>{{-- /pd-slideover-panel --}}
</div>{{-- /pd-slideover-overlay --}}

<script>
function taskSlideover() {
    return {
        open: false, loading: false, loadError: false,
        newMode: false, editMode: false,
        taskData: null, comments: [], newComment: '',
        depPickerOpen: false, depSelectedId: '', depError: '',
        newTask: {
            title: '', status: 'todo', priority: 'medium',
            description: '', start_date: '', due_date: '',
            estimated_hours: null, actual_hours: null,
            assigned_to: '', milestone_id: ''
        },

        init() {
            document.addEventListener('keydown', e => {
                if (e.key === 'Escape' && this.open) this.close();
            });
        },

        openTask(taskId) {
            this.open = true; this.newMode = false; this.editMode = false;
            this.loading = true; this.taskData = null; this.loadError = false;
            this.depPickerOpen = false; this.depSelectedId = ''; this.depError = '';

            fetch(`{{ url('projects/' . $project->id . '/tasks') }}/${taskId}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
            })
            .then(r => { if (!r.ok) throw new Error(); return r.json(); })
            .then(d => { this.taskData = d.task; this.comments = d.comments || []; this.loading = false; })
            .catch(() => { this.loading = false; this.loadError = true; });
        },

        addDependency() {
            if (!this.depSelectedId || !this.taskData) return;
            this.depError = '';
            fetch(`{{ url('projects/' . $project->id . '/tasks') }}/${this.taskData.id}/dependencies`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json', 'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ predecessor_id: this.depSelectedId }),
            })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    if (!this.taskData.predecessors) this.taskData.predecessors = [];
                    if (!this.taskData.predecessors.find(p => p.id === d.predecessor.id))
                        this.taskData.predecessors.push(d.predecessor);
                    this.depPickerOpen = false; this.depSelectedId = '';
                } else { this.depError = d.error || "Erreur."; }
            })
            .catch(() => { this.depError = 'Erreur réseau.'; });
        },

        removeDependency(predecessorId) {
            if (!this.taskData) return;
            fetch(`{{ url('projects/' . $project->id . '/tasks') }}/${this.taskData.id}/dependencies/${predecessorId}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json', 'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
            })
            .then(r => r.json())
            .then(d => { if (d.success) this.taskData.predecessors = this.taskData.predecessors.filter(p => p.id !== predecessorId); });
        },

        openNew(status, milestoneId = null) {
            this.open = true; this.newMode = true; this.editMode = false;
            this.taskData = null; this.loadError = false;
            this.newTask = {
                title: '', status: status || 'todo', priority: 'medium',
                description: '', start_date: '', due_date: '',
                estimated_hours: null, actual_hours: null,
                assigned_to: '', milestone_id: milestoneId || '',
                recurrence_type: '', recurrence_every: 1, recurrence_ends: '',
            };
        },

        close() { this.open = false; this.loadError = false; this.editMode = false; },

        submitNew() {
            fetch(`{{ route('projects.tasks.store', $project) }}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json', 'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify(this.newTask),
            })
            .then(r => r.json())
            .then(d => { if (d.success) { this.close(); window.location.reload(); } });
        },

        submitUpdate() {
            fetch(`{{ url('projects/' . $project->id . '/tasks') }}/${this.taskData.id}`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json', 'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({
                    title: this.taskData.title, status: this.taskData.status,
                    priority: this.taskData.priority, description: this.taskData.description,
                    start_date: this.taskData.start_date, due_date: this.taskData.due_date,
                    estimated_hours: this.taskData.estimated_hours,
                    actual_hours: this.taskData.actual_hours,
                    assigned_to: this.taskData.assigned_to || null,
                    milestone_id: this.taskData.milestone_id || null,
                }),
            })
            .then(r => r.json())
            .then(d => { if (d.success) { this.editMode = false; window.location.reload(); } else if (d.error) { alert(d.error); } });
        },

        deleteTask() {
            if (!this.taskData || !confirm(`Supprimer la tâche « ${this.taskData.title} » ?`)) return;
            fetch(`{{ url('projects/' . $project->id . '/tasks') }}/${this.taskData.id}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json', 'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
            })
            .then(r => r.json())
            .then(d => { if (d.success) { this.open = false; window.location.reload(); } })
            .catch(() => alert('Erreur lors de la suppression.'));
        },

        submitComment() {
            if (!this.newComment.trim() || !this.taskData) return;
            fetch(`{{ url('projects/' . $project->id . '/tasks') }}/${this.taskData.id}/comments`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json', 'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ body: this.newComment }),
            })
            .then(r => r.json())
            .then(d => { if (d.success) { this.comments.push(d.comment || d); this.newComment = ''; } });
        },

        statusLabel: s => ({ todo: 'À faire', in_progress: 'En cours', in_review: 'En revue', done: 'Terminé' }[s] || s),
        prioLabel:   p => ({ low: 'Basse', medium: 'Moyenne', high: 'Haute', urgent: 'Urgente' }[p] || p),
        statusStyle: s => ({ todo: 'background:#E2E8F0;color:#475569', in_progress: 'background:#DBEAFE;color:#1E40AF', in_review: 'background:#EDE9FE;color:#5B21B6', done: 'background:#D1FAE5;color:#065F46' }[s]),
        prioStyle:   p => ({ low: 'background:#D1FAE5;color:#065F46', medium: 'background:#DBEAFE;color:#1E40AF', high: 'background:#FEF3C7;color:#92400E', urgent: 'background:#FEE2E2;color:#991B1B' }[p]),
    };
}
</script>
