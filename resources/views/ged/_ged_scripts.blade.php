{{--
    JS Alpine pour les modales CRUD dossiers (index + show).
    Variable attendue :
      $parentId — int|null (ID du dossier courant, null = racine)
--}}
@php $parentId ??= null; @endphp

<script>
function gedFolderPage() {
    return {
        _modal: null,
        _loading: false,

        // Création
        _createName: '',
        _createPrivate: false,
        _createError: '',

        // Renommer
        _renameId: null,
        _renameName: '',
        _renamePrivate: false,
        _renameError: '',

        // Suppression
        _deleteId: null,
        _deleteName: '',
        _deleteError: '',

        openCreate() {
            this._createName = '';
            this._createPrivate = false;
            this._createError = '';
            this._modal = 'create';
            this.$nextTick(() => this.$refs.createName?.focus());
        },

        openRename(id, name, isPrivate) {
            this._renameId = id;
            this._renameName = name;
            this._renamePrivate = isPrivate;
            this._renameError = '';
            this._modal = 'rename';
            this.$nextTick(() => this.$refs.renameName?.focus());
        },

        openDelete(id, name) {
            this._deleteId = id;
            this._deleteName = name;
            this._deleteError = '';
            this._modal = 'delete';
        },

        async submitCreate() {
            if (!this._createName.trim()) return;
            this._loading = true;
            this._createError = '';
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
                const body = {
                    name: this._createName.trim(),
                    is_private: this._createPrivate ? 1 : 0,
                };
                @if($parentId)
                body.parent_id = {{ $parentId }};
                @endif

                const resp = await fetch('{{ route('ged.folders.store') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(body),
                });
                const data = await resp.json();
                if (!resp.ok) {
                    this._createError = data.message || data.errors?.name?.[0] || 'Erreur';
                    return;
                }
                location.reload();
            } catch {
                this._createError = 'Erreur réseau.';
            } finally {
                this._loading = false;
            }
        },

        async submitRename() {
            if (!this._renameName.trim()) return;
            this._loading = true;
            this._renameError = '';
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
                const resp = await fetch(`{{ url('ged/folders') }}/${this._renameId}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        name: this._renameName.trim(),
                        is_private: this._renamePrivate ? 1 : 0,
                    }),
                });
                const data = await resp.json();
                if (!resp.ok) {
                    this._renameError = data.message || data.errors?.name?.[0] || 'Erreur';
                    return;
                }
                location.reload();
            } catch {
                this._renameError = 'Erreur réseau.';
            } finally {
                this._loading = false;
            }
        },

        async submitDelete() {
            this._loading = true;
            this._deleteError = '';
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
                const resp = await fetch(`{{ url('ged/folders') }}/${this._deleteId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                    },
                });
                const data = await resp.json();
                if (!resp.ok) {
                    this._deleteError = data.error || 'Erreur';
                    return;
                }
                location.reload();
            } catch {
                this._deleteError = 'Erreur réseau.';
            } finally {
                this._loading = false;
            }
        },
    };
}
</script>
