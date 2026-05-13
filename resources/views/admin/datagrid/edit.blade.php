@extends('layouts.admin')
@section('title', 'Modifier — ' . $table->label)

@section('admin-content')
<div style="padding:32px 40px;max-width:1200px;">

    {{-- ── En-tête ──────────────────────────────────────────────────────── --}}
    <div style="margin-bottom:24px;">
        <a href="{{ route('admin.datagrid.index') }}"
           style="font-size:12px;color:var(--pd-muted);text-decoration:none;">
            ← Grilles DataGrid
        </a>
        <h1 style="font-size:20px;font-weight:700;color:var(--pd-text);margin:8px 0 0;">
            {{ $table->label }}
        </h1>
        <div style="font-family:monospace;font-size:12px;color:var(--pd-muted);margin-top:2px;">
            {{ $table->mysql_table }}
        </div>
    </div>

    @if(session('success'))
    <div style="padding:10px 16px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;
                color:#166534;font-size:13px;font-weight:600;margin-bottom:16px;">
        ✓ {{ session('success') }}
    </div>
    @endif

    {{-- ── Paramètres de la grille ──────────────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm mb-5">
        <h2 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-4 pb-2 border-b">① Paramètres</h2>
        <form id="form-table"
              @submit.prevent="
                fetch('{{ route('admin.datagrid.update', $table) }}', {
                    method: 'PATCH',
                    headers: {'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'},
                    body: JSON.stringify({
                        label: $el.label.value,
                        description: $el.description.value,
                        has_rgpd: $el.has_rgpd.checked,
                    })
                }).then(r => r.json()).then(d => {
                    if (d.success) { $el.querySelector('[data-saved]').style.display = 'inline'; setTimeout(() => $el.querySelector('[data-saved]').style.display = 'none', 2000); }
                })
              " x-data="{}">
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Label</label>
                    <input name="label" type="text" value="{{ $table->label }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Description</label>
                    <input name="description" type="text" value="{{ $table->description }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
                </div>
            </div>
            <div class="flex items-center gap-4 flex-wrap">
                <label class="flex items-center gap-2 text-sm cursor-pointer">
                    <input name="has_rgpd" type="checkbox" {{ $table->has_rgpd ? 'checked' : '' }}
                           class="w-4 h-4 accent-blue-800">
                    Données RGPD sensibles (active le journal d'audit)
                </label>
                <button type="submit"
                        style="background:var(--pd-navy);"
                        class="px-4 py-2 text-white rounded-lg text-sm font-semibold border-0 cursor-pointer">
                    Enregistrer
                </button>
                <span data-saved style="display:none;font-size:12px;color:#16a34a;font-weight:600;">✓ Sauvegardé</span>
            </div>
        </form>
    </div>

    {{-- ── Colonnes ────────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-5 overflow-hidden">
        <div class="px-5 py-3 border-b bg-gray-50 flex items-center justify-between">
            <h2 class="text-xs font-bold text-gray-400 uppercase tracking-wider">② Colonnes ({{ $columns->count() }})</h2>
        </div>

        @if($columns->isEmpty())
        <p class="text-sm text-gray-400 p-5">Aucune colonne.</p>
        @else
        <table style="width:100%;border-collapse:collapse;font-size:12px;">
            <thead>
                <tr class="bg-gray-50">
                    <th class="px-4 py-2 text-left text-xs font-semibold text-gray-400 border-b">#</th>
                    <th class="px-4 py-2 text-left text-xs font-semibold text-gray-400 border-b">Nom technique</th>
                    <th class="px-4 py-2 text-left text-xs font-semibold text-gray-400 border-b">Label</th>
                    <th class="px-4 py-2 text-left text-xs font-semibold text-gray-400 border-b">Type</th>
                    <th class="px-4 py-2 text-center text-xs font-semibold text-gray-400 border-b">Onglet</th>
                    <th class="px-4 py-2 text-center text-xs font-semibold text-gray-400 border-b">Requis</th>
                    <th class="px-4 py-2 text-center text-xs font-semibold text-gray-400 border-b">RGPD</th>
                    <th class="px-4 py-2 border-b"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($columns as $col)
                @php $tab = $col->tab ?? 'main'; @endphp
                <tr class="border-b hover:bg-gray-50 transition-colors">
                    <td class="px-4 py-2 text-gray-400">{{ $col->sort_order }}</td>
                    <td class="px-4 py-2 font-mono font-semibold text-blue-900">{{ $col->name }}</td>
                    <td class="px-4 py-2 font-medium text-gray-800">{{ $col->label }}</td>
                    <td class="px-4 py-2">
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">
                            {{ $col->type->label() }}
                        </span>
                    </td>
                    <td class="px-4 py-2 text-center">
                        @if($tab === 'extra')
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-purple-100 text-purple-700">Complémentaires</span>
                        @else
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-600">Données</span>
                        @endif
                    </td>
                    <td class="px-4 py-2 text-center text-{{ $col->required ? 'green' : 'gray' }}-400">
                        {{ $col->required ? '✓' : '—' }}
                    </td>
                    <td class="px-4 py-2 text-center text-{{ $col->is_rgpd_sensitive ? 'amber' : 'gray' }}-400">
                        {{ $col->is_rgpd_sensitive ? '🔒' : '—' }}
                    </td>
                    <td class="px-4 py-2 text-right whitespace-nowrap">
                        <a href="{{ route('admin.datagrid.columns.edit', [$table, $col]) }}"
                           style="background:var(--pd-navy);"
                           class="inline-flex px-3 py-1 text-white rounded text-xs font-semibold no-underline mr-1">
                            ✎ Modifier
                        </a>
                        <form method="POST"
                              action="{{ route('datagrid.columns.destroy', [$table, $col]) }}"
                              style="display:inline;"
                              onsubmit="return confirm('Supprimer la colonne « {{ $col->name }} » et ses données ?')">
                            @csrf @method('DELETE')
                            <button type="submit"
                                    class="px-2 py-1 border border-red-200 rounded text-xs text-red-500 bg-red-50 cursor-pointer">
                                🗑
                            </button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

    {{-- ── Droits d'accès ──────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-5 overflow-hidden">
        <div class="px-5 py-4 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-semibold text-gray-700">③ Droits d'accès</h2>
                <p class="text-xs text-gray-400 mt-1">Gérer qui peut lire, écrire, supprimer et exporter les données.</p>
            </div>
            <a href="{{ route('admin.datagrid.permissions', $table) }}"
               style="background:var(--pd-navy);"
               class="px-4 py-2 text-white rounded-lg text-sm font-semibold no-underline inline-flex items-center gap-2">
                🔐 Gérer les droits
            </a>
        </div>
    </div>

</div>

<script>
(function () {
    var token   = '{{ csrf_token() }}';
    var tableId = {{ $table->id }};
    var baseUrl = '{{ url('/admin/datagrid') }}/' + tableId;

    function post(url, data) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
            body: JSON.stringify(data),
        }).then(function (r) {
            if (r.ok) { location.reload(); }
            else { r.json().then(function (e) { alert(e.message || 'Erreur'); }); }
        });
    }

    function del(url) {
        return fetch(url, {
            method: 'DELETE',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
        }).then(function (r) {
            if (r.ok) { location.reload(); }
            else { alert('Erreur lors de la suppression'); }
        });
    }

    window.addRolePerm = function () {
        post(baseUrl + '/permissions/role', {
            role:       document.getElementById('new-role-role').value,
            can_read:   document.getElementById('new-role-can_read').checked,
            can_write:  document.getElementById('new-role-can_write').checked,
            can_delete: document.getElementById('new-role-can_delete').checked,
            can_export: document.getElementById('new-role-can_export').checked,
            denied:     document.getElementById('new-role-denied').checked,
        });
    };

    window.addDeptPerm = function () {
        post(baseUrl + '/permissions/department', {
            department_id: parseInt(document.getElementById('new-dept-dept').value),
            can_read:      document.getElementById('new-dept-can_read').checked,
            can_write:     document.getElementById('new-dept-can_write').checked,
            can_delete:    document.getElementById('new-dept-can_delete').checked,
            can_export:    document.getElementById('new-dept-can_export').checked,
            denied:        document.getElementById('new-dept-denied').checked,
        });
    };

    window.addUserPerm = function () {
        post(baseUrl + '/permissions/user', {
            user_id:    parseInt(document.getElementById('new-user-user').value),
            can_read:   document.getElementById('new-user-can_read').checked,
            can_write:  document.getElementById('new-user-can_write').checked,
            can_delete: document.getElementById('new-user-can_delete').checked,
            can_export: document.getElementById('new-user-can_export').checked,
            denied:     document.getElementById('new-user-denied').checked,
        });
    };

    window.deletePerm = function (type, id) {
        if (! confirm('Supprimer cette règle ?')) { return; }
        var map = { role: 'role', dept: 'department', user: 'user' };
        del(baseUrl + '/permissions/' + map[type] + '/' + id);
    };
}());
</script>
@endsection
