{{-- resources/views/projects/partials/_task_slideover.blade.php --}}
<div x-data="taskSlideover()"
     @open-task.window="openTask($event.detail.taskId)"
     @open-new-task.window="openNew($event.detail.status)"
     style="position:fixed;top:0;right:0;bottom:0;width:440px;z-index:9999;pointer-events:none;">

    {{-- Overlay sombre --}}
    <div x-show="open" x-cloak
         @click="close()"
         style="position:fixed;inset:0;background:rgba(0,0,0,.3);pointer-events:auto;"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
    </div>

    {{-- Panneau latéral --}}
    <div x-show="open" x-cloak
         style="position:absolute;top:0;right:0;bottom:0;width:440px;background:var(--pd-bg);border-left:0.5px solid var(--pd-border);overflow-y:auto;pointer-events:auto;box-shadow:-4px 0 16px rgba(0,0,0,.08);"
         x-transition:enter="transform transition ease-out duration-200"
         x-transition:enter-start="translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transform transition ease-in duration-150"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="translate-x-full">

        {{-- En-tête --}}
        <div style="padding:16px 20px;border-bottom:0.5px solid var(--pd-border);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:var(--pd-bg);z-index:1;">
            <span style="font-size:14px;font-weight:600;" x-text="taskData?.title || 'Nouvelle tâche'"></span>
            <button @click="close()"
                    style="background:none;border:none;cursor:pointer;color:var(--pd-muted);font-size:20px;line-height:1;padding:0 4px;">
                ×
            </button>
        </div>

        {{-- Corps --}}
        <div style="padding:20px;">

            {{-- Chargement --}}
            <div x-show="loading" style="text-align:center;padding:40px;color:var(--pd-muted);">
                Chargement…
            </div>

            {{-- Erreur de chargement --}}
            <div x-show="loadError && !loading" x-cloak style="text-align:center;padding:40px;">
                <div style="font-size:32px;margin-bottom:12px;">⚠️</div>
                <div style="font-size:14px;font-weight:500;color:var(--pd-text);margin-bottom:8px;">Impossible de charger la tâche</div>
                <div style="font-size:12px;color:var(--pd-muted);margin-bottom:16px;">Vérifiez votre connexion ou rechargez la page.</div>
                <button @click="close()" class="pd-btn pd-btn-secondary pd-btn-sm">Fermer</button>
            </div>

            {{-- Mode création --}}
            <template x-if="!loading && newMode">
                <form @submit.prevent="submitNew()" style="display:flex;flex-direction:column;gap:14px;">
                    <div>
                        <label class="pd-label">Titre *</label>
                        <input type="text" x-model="newTask.title" class="pd-input" required autofocus
                               placeholder="Titre de la tâche">
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                        <div>
                            <label class="pd-label">Statut</label>
                            <select x-model="newTask.status" class="pd-input">
                                <option value="todo">À faire</option>
                                <option value="in_progress">En cours</option>
                                <option value="in_review">En revue</option>
                                <option value="done">Terminé</option>
                            </select>
                        </div>
                        <div>
                            <label class="pd-label">Priorité</label>
                            <select x-model="newTask.priority" class="pd-input">
                                <option value="low">Basse</option>
                                <option value="medium">Moyenne</option>
                                <option value="high">Haute</option>
                                <option value="urgent">Urgente</option>
                            </select>
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                        <div>
                            <label class="pd-label">Date début</label>
                            <input type="date" x-model="newTask.start_date" class="pd-input">
                        </div>
                        <div>
                            <label class="pd-label">Échéance</label>
                            <input type="date" x-model="newTask.due_date" class="pd-input">
                        </div>
                    </div>
                    <div>
                        <label class="pd-label">Description</label>
                        <textarea x-model="newTask.description" class="pd-input" rows="3"
                                  placeholder="Description optionnelle…"></textarea>
                    </div>
                    <div style="display:flex;gap:8px;justify-content:flex-end;padding-top:4px;">
                        <button type="button" @click="close()" class="pd-btn pd-btn-secondary">Annuler</button>
                        <button type="submit" class="pd-btn pd-btn-primary">Créer la tâche</button>
                    </div>
                </form>
            </template>

            {{-- Mode consultation tâche existante --}}
            <template x-if="!loading && !newMode && taskData">
                <div>
                    {{-- Badges statut / priorité --}}
                    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
                        <span style="font-size:12px;padding:3px 10px;border-radius:10px;font-weight:500;"
                              :style="statusStyle(taskData.status)"
                              x-text="statusLabel(taskData.status)"></span>
                        <span style="font-size:12px;padding:3px 10px;border-radius:10px;font-weight:500;"
                              :style="prioStyle(taskData.priority)"
                              x-text="prioLabel(taskData.priority)"></span>
                    </div>

                    {{-- Description --}}
                    <div style="font-size:13px;color:var(--pd-muted);margin-bottom:16px;line-height:1.6;"
                         x-text="taskData.description || 'Aucune description.'"></div>

                    {{-- Méta informations --}}
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:12px;margin-bottom:20px;">
                        <div style="background:var(--pd-surface);padding:10px;border-radius:8px;">
                            <div style="color:var(--pd-muted);margin-bottom:3px;font-size:11px;text-transform:uppercase;letter-spacing:.04em;">Assigné</div>
                            <div style="font-weight:500;" x-text="taskData.assignee?.name || '—'"></div>
                        </div>
                        <div style="background:var(--pd-surface);padding:10px;border-radius:8px;">
                            <div style="color:var(--pd-muted);margin-bottom:3px;font-size:11px;text-transform:uppercase;letter-spacing:.04em;">Échéance</div>
                            <div style="font-weight:500;" x-text="taskData.due_date || '—'"></div>
                        </div>
                        <div style="background:var(--pd-surface);padding:10px;border-radius:8px;">
                            <div style="color:var(--pd-muted);margin-bottom:3px;font-size:11px;text-transform:uppercase;letter-spacing:.04em;">Estimé</div>
                            <div style="font-weight:500;" x-text="(taskData.estimated_hours ?? '—') + 'h'"></div>
                        </div>
                        <div style="background:var(--pd-surface);padding:10px;border-radius:8px;">
                            <div style="color:var(--pd-muted);margin-bottom:3px;font-size:11px;text-transform:uppercase;letter-spacing:.04em;">Réalisé</div>
                            <div style="font-weight:500;" x-text="(taskData.actual_hours ?? '—') + 'h'"></div>
                        </div>
                    </div>

                    {{-- Commentaires --}}
                    <div style="border-top:0.5px solid var(--pd-border);padding-top:16px;">
                        <div style="font-size:11px;font-weight:600;color:var(--pd-muted);text-transform:uppercase;letter-spacing:.04em;margin-bottom:12px;">
                            Commentaires (<span x-text="comments.length"></span>)
                        </div>

                        <template x-for="comment in comments" :key="comment.id">
                            <div style="margin-bottom:10px;padding:10px 12px;background:var(--pd-surface);border-radius:8px;border:0.5px solid var(--pd-border);">
                                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                                    <span style="font-size:12px;font-weight:500;" x-text="comment.author"></span>
                                    <span style="font-size:11px;color:var(--pd-muted);" x-text="comment.created_at"></span>
                                </div>
                                <div style="font-size:13px;line-height:1.5;" x-text="comment.body"></div>
                            </div>
                        </template>

                        <div x-show="comments.length === 0" style="font-size:13px;color:var(--pd-muted);margin-bottom:12px;">
                            Aucun commentaire pour l'instant.
                        </div>

                        @if($canEdit)
                        <form @submit.prevent="submitComment()"
                              style="display:flex;gap:8px;margin-top:12px;">
                            <input type="text"
                                   x-model="newComment"
                                   placeholder="Ajouter un commentaire…"
                                   class="pd-input pd-input-sm"
                                   style="flex:1;">
                            <button type="submit" class="pd-btn pd-btn-sm pd-btn-primary">Envoyer</button>
                        </form>
                        @endif
                    </div>
                </div>
            </template>

        </div>
    </div>
</div>

<script>
function taskSlideover() {
    return {
        open:       false,
        loading:    false,
        loadError:  false,
        newMode:    false,
        taskData:   null,
        comments:   [],
        newComment: '',
        newTask: { title: '', status: 'todo', priority: 'medium', description: '', start_date: '', due_date: '' },

        openTask(taskId) {
            this.open    = true;
            this.newMode = false;
            this.loading = true;
            this.taskData = null;
            this.loadError = false;

            fetch(`{{ url('projects/' . $project->id . '/tasks') }}/${taskId}`, {
                headers: {
                    'Accept':       'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
            })
            .then(r => {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(d => {
                this.taskData = d.task;
                this.comments = d.comments || [];
                this.loading  = false;
            })
            .catch(() => {
                this.loading   = false;
                this.loadError = true;
            });
        },

        openNew(status) {
            this.open      = true;
            this.newMode   = true;
            this.taskData  = null;
            this.loadError = false;
            this.newTask   = { title: '', status: status || 'todo', priority: 'medium', description: '', start_date: '', due_date: '' };
        },

        close() { this.open = false; this.loadError = false; },

        submitNew() {
            fetch(`{{ route('projects.tasks.store', $project) }}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept':       'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify(this.newTask),
            })
            .then(r => r.json())
            .then(d => { if (d.success) { this.close(); window.location.reload(); } });
        },

        submitComment() {
            if (! this.newComment.trim() || ! this.taskData) return;

            fetch(`{{ url('projects/' . $project->id . '/tasks') }}/${this.taskData.id}/comments`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept':       'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ body: this.newComment }),
            })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    this.comments.push(d);
                    this.newComment = '';
                }
            });
        },

        statusLabel: s => ({ todo: 'À faire', in_progress: 'En cours', in_review: 'En revue', done: 'Terminé' }[s] || s),
        prioLabel:   p => ({ low: 'Basse', medium: 'Moyenne', high: 'Haute', urgent: 'Urgente' }[p] || p),
        statusStyle: s => ({
            todo:        'background:#E2E8F0;color:#475569',
            in_progress: 'background:#DBEAFE;color:#1E40AF',
            in_review:   'background:#EDE9FE;color:#5B21B6',
            done:        'background:#D1FAE5;color:#065F46',
        }[s]),
        prioStyle: p => ({
            low:    'background:#D1FAE5;color:#065F46',
            medium: 'background:#DBEAFE;color:#1E40AF',
            high:   'background:#FEF3C7;color:#92400E',
            urgent: 'background:#FEE2E2;color:#991B1B',
        }[p]),
    };
}
</script>
