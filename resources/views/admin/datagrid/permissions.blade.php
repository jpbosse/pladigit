@extends('layouts.app')
@section('title', 'Droits — ' . $table->label)

@section('content')
<div class="max-w-5xl mx-auto px-4 pb-12">

    {{-- ── Fil d'Ariane ───────────────────────────────────────────────────── --}}
    <div class="flex items-center gap-2 mb-6 text-sm flex-wrap">
        <a href="{{ route('admin.datagrid.index') }}" class="text-gray-400 hover:text-gray-600">← Grilles DataGrid</a>
        <span class="text-gray-300">/</span>
        <a href="{{ route('admin.datagrid.edit', $table) }}" class="text-gray-400 hover:text-gray-600">{{ $table->label }}</a>
        <span class="text-gray-300">/</span>
        <span class="font-semibold text-gray-800">🔐 Droits</span>
    </div>

    <div class="flex items-center justify-between mb-8 flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-800">Gestion des droits d'accès</h1>
            <p class="text-sm text-gray-500 mt-1">Grille : <strong>{{ $table->label }}</strong></p>
        </div>
        <a href="{{ route('admin.datagrid.edit', $table) }}"
           class="text-sm text-gray-500 hover:text-gray-700 border border-gray-200 px-3 py-2 rounded-lg bg-white no-underline">
            ← Retour à la grille
        </a>
    </div>

    {{-- Flash --}}
    @if(session('success'))
    <div class="mb-6 p-3 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm flex items-center gap-2">
        <span>✅</span> {{ session('success') }}
    </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════════════
         BLOC 1 — ACCÈS À LA GRILLE
         Qui peut voir, modifier, supprimer, exporter la grille entière ?
    ══════════════════════════════════════════════════════════════════════════ --}}
    <div class="mb-2">
        <div class="flex items-center gap-3 mb-1">
            <span class="flex items-center justify-center w-8 h-8 rounded-full text-white font-bold text-sm"
                  style="background:var(--color-primary,#1E3A5F);">1</span>
            <h2 class="text-lg font-bold text-gray-800">Accès à la grille</h2>
        </div>
        <p class="text-sm text-gray-500 ml-11">
            Ces règles contrôlent <strong>qui peut accéder à cette grille</strong> et ce qu'il peut y faire.
        </p>
    </div>

    {{-- Règle d'or --}}
    <div class="ml-11 mb-4 p-4 bg-amber-50 border border-amber-200 rounded-xl text-sm">
        <p class="font-bold text-amber-800 mb-2">📌 Règle d'or — priorité décroissante :</p>
        <div class="flex items-center gap-2 flex-wrap text-amber-700">
            <span class="inline-flex items-center gap-1 px-3 py-1 bg-white border border-amber-200 rounded-full text-xs font-semibold">
                👤 Individuel <span class="text-amber-400 ml-1">prime sur tout</span>
            </span>
            <span class="text-amber-400 font-bold">›</span>
            <span class="inline-flex items-center gap-1 px-3 py-1 bg-white border border-amber-200 rounded-full text-xs font-semibold">
                🏢 Département
            </span>
            <span class="text-amber-400 font-bold">›</span>
            <span class="inline-flex items-center gap-1 px-3 py-1 bg-white border border-amber-200 rounded-full text-xs font-semibold">
                👥 Rôle
            </span>
            <span class="text-amber-400 font-bold">›</span>
            <span class="inline-flex items-center gap-1 px-3 py-1 bg-white border border-amber-200 rounded-full text-xs font-semibold">
                🚫 Aucun droit par défaut
            </span>
        </div>
        <p class="text-xs text-amber-600 mt-2">
            ⚠️ <strong>Admin, Président / Maire et DGS</strong> ont toujours tous les droits, quelles que soient les règles ci-dessous.
        </p>
    </div>

    {{-- 3 cartes côte à côte --}}
    <div class="ml-11 grid grid-cols-1 md:grid-cols-3 gap-4 mb-10">

        {{-- Par rôle --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 flex flex-col">
            <div class="px-4 py-3 border-b border-gray-100 bg-gray-50 flex items-center gap-2">
                <span class="text-base">👥</span>
                <div>
                    <h3 class="text-sm font-semibold text-gray-800">Par rôle</h3>
                    <p class="text-xs text-gray-400">S'applique à tous les agents de ce rôle</p>
                </div>
            </div>
            @if($perms['role']->isEmpty())
            <p class="px-4 py-3 text-xs text-gray-400 italic">Aucune règle.</p>
            @else
            <div class="divide-y divide-gray-50">
                @foreach($perms['role'] as $perm)
                <div class="px-4 py-2 flex items-start justify-between gap-2">
                    <div>
                        <div class="text-xs font-semibold text-gray-700">
                            {{ \App\Enums\UserRole::tryFrom($perm->subject_role)?->label() ?? $perm->subject_role }}
                        </div>
                        @if($perm->denied)
                            <span class="inline-flex px-1.5 py-0.5 rounded text-xs font-bold bg-red-100 text-red-600">🚫 Bloqué</span>
                        @else
                            <div class="flex flex-wrap gap-1 mt-0.5">
                                @foreach(['can_read'=>'Lire','can_write'=>'Écrire','can_delete'=>'Suppr.','can_export'=>'Export'] as $f=>$l)
                                @if($perm->$f)<span class="inline-flex px-1 py-0.5 rounded text-xs bg-green-100 text-green-700">{{ $l }}</span>@endif
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <form method="POST" action="{{ route('admin.datagrid.permissions.role.destroy', [$table, $perm]) }}">
                        @csrf @method('DELETE')
                        <button class="text-xs text-red-300 hover:text-red-500 cursor-pointer border-0 bg-transparent mt-1">✕</button>
                    </form>
                </div>
                @endforeach
            </div>
            @endif
            <div class="px-4 py-3 bg-gray-50 border-t border-gray-100 mt-auto">
                <form method="POST" action="{{ route('admin.datagrid.permissions.role.store', $table) }}" class="space-y-2">
                    @csrf
                    <select name="role" required class="w-full border border-gray-200 rounded-lg px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-blue-300">
                        <option value="">— Choisir un rôle</option>
                        @foreach($roles as $role)<option value="{{ $role->value }}">{{ $role->label() }}</option>@endforeach
                    </select>
                    <div class="flex flex-wrap gap-2">
                        @foreach(['can_read'=>'Lire','can_write'=>'Écrire','can_delete'=>'Suppr.','can_export'=>'Export'] as $f=>$l)
                        <label class="flex items-center gap-1 text-xs text-gray-600 cursor-pointer">
                            <input type="checkbox" name="{{ $f }}" value="1" class="w-3 h-3 accent-blue-800"> {{ $l }}
                        </label>
                        @endforeach
                        <label class="flex items-center gap-1 text-xs text-red-600 cursor-pointer">
                            <input type="checkbox" name="denied" value="1" class="w-3 h-3" style="accent-color:#dc2626;"> Bloquer
                        </label>
                    </div>
                    <button type="submit" class="w-full py-1.5 rounded-lg text-white text-xs font-semibold border-0 cursor-pointer" style="background:var(--color-primary,#1E3A5F);">+ Ajouter</button>
                </form>
            </div>
        </div>

        {{-- Par département --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 flex flex-col">
            <div class="px-4 py-3 border-b border-gray-100 bg-gray-50 flex items-center gap-2">
                <span class="text-base">🏢</span>
                <div>
                    <h3 class="text-sm font-semibold text-gray-800">Par département</h3>
                    <p class="text-xs text-gray-400">S'applique à tous les membres du service</p>
                </div>
            </div>
            @if($perms['department']->isEmpty())
            <p class="px-4 py-3 text-xs text-gray-400 italic">Aucune règle.</p>
            @else
            <div class="divide-y divide-gray-50">
                @foreach($perms['department'] as $perm)
                <div class="px-4 py-2 flex items-start justify-between gap-2">
                    <div>
                        <div class="text-xs font-semibold text-gray-700">{{ $perm->department?->name ?? '—' }}</div>
                        @if($perm->denied)
                            <span class="inline-flex px-1.5 py-0.5 rounded text-xs font-bold bg-red-100 text-red-600">🚫 Bloqué</span>
                        @else
                            <div class="flex flex-wrap gap-1 mt-0.5">
                                @foreach(['can_read'=>'Lire','can_write'=>'Écrire','can_delete'=>'Suppr.','can_export'=>'Export'] as $f=>$l)
                                @if($perm->$f)<span class="inline-flex px-1 py-0.5 rounded text-xs bg-green-100 text-green-700">{{ $l }}</span>@endif
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <form method="POST" action="{{ route('admin.datagrid.permissions.dept.destroy', [$table, $perm]) }}">
                        @csrf @method('DELETE')
                        <button class="text-xs text-red-300 hover:text-red-500 cursor-pointer border-0 bg-transparent mt-1">✕</button>
                    </form>
                </div>
                @endforeach
            </div>
            @endif
            <div class="px-4 py-3 bg-gray-50 border-t border-gray-100 mt-auto">
                <form method="POST" action="{{ route('admin.datagrid.permissions.dept.store', $table) }}" class="space-y-2">
                    @csrf
                    <select name="department_id" required class="w-full border border-gray-200 rounded-lg px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-blue-300">
                        <option value="">— Choisir un département</option>
                        @foreach($departments as $dept)<option value="{{ $dept->id }}">{{ $dept->name }}</option>@endforeach
                    </select>
                    <div class="flex flex-wrap gap-2">
                        @foreach(['can_read'=>'Lire','can_write'=>'Écrire','can_delete'=>'Suppr.','can_export'=>'Export'] as $f=>$l)
                        <label class="flex items-center gap-1 text-xs text-gray-600 cursor-pointer">
                            <input type="checkbox" name="{{ $f }}" value="1" class="w-3 h-3 accent-blue-800"> {{ $l }}
                        </label>
                        @endforeach
                        <label class="flex items-center gap-1 text-xs text-red-600 cursor-pointer">
                            <input type="checkbox" name="denied" value="1" class="w-3 h-3" style="accent-color:#dc2626;"> Bloquer
                        </label>
                    </div>
                    <button type="submit" class="w-full py-1.5 rounded-lg text-white text-xs font-semibold border-0 cursor-pointer" style="background:var(--color-primary,#1E3A5F);">+ Ajouter</button>
                </form>
            </div>
        </div>

        {{-- Par utilisateur --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 flex flex-col">
            <div class="px-4 py-3 border-b border-gray-100 bg-gray-50 flex items-center gap-2">
                <span class="text-base">👤</span>
                <div>
                    <h3 class="text-sm font-semibold text-gray-800">Par utilisateur</h3>
                    <p class="text-xs text-gray-400">Override individuel — prime sur tout</p>
                </div>
            </div>
            @if($perms['user']->isEmpty())
            <p class="px-4 py-3 text-xs text-gray-400 italic">Aucune règle.</p>
            @else
            <div class="divide-y divide-gray-50">
                @foreach($perms['user'] as $perm)
                <div class="px-4 py-2 flex items-start justify-between gap-2">
                    <div>
                        <div class="text-xs font-semibold text-gray-700">{{ $perm->user?->name ?? '—' }}</div>
                        @if($perm->denied)
                            <span class="inline-flex px-1.5 py-0.5 rounded text-xs font-bold bg-red-100 text-red-600">🚫 Bloqué</span>
                        @else
                            <div class="flex flex-wrap gap-1 mt-0.5">
                                @foreach(['can_read'=>'Lire','can_write'=>'Écrire','can_delete'=>'Suppr.','can_export'=>'Export'] as $f=>$l)
                                @if($perm->$f)<span class="inline-flex px-1 py-0.5 rounded text-xs bg-green-100 text-green-700">{{ $l }}</span>@endif
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <form method="POST" action="{{ route('admin.datagrid.permissions.user.destroy', [$table, $perm]) }}">
                        @csrf @method('DELETE')
                        <button class="text-xs text-red-300 hover:text-red-500 cursor-pointer border-0 bg-transparent mt-1">✕</button>
                    </form>
                </div>
                @endforeach
            </div>
            @endif
            <div class="px-4 py-3 bg-gray-50 border-t border-gray-100 mt-auto">
                <form method="POST" action="{{ route('admin.datagrid.permissions.user.store', $table) }}" class="space-y-2">
                    @csrf
                    <select name="user_id" required class="w-full border border-gray-200 rounded-lg px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-blue-300">
                        <option value="">— Choisir un utilisateur</option>
                        @foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
                    </select>
                    <div class="flex flex-wrap gap-2">
                        @foreach(['can_read'=>'Lire','can_write'=>'Écrire','can_delete'=>'Suppr.','can_export'=>'Export'] as $f=>$l)
                        <label class="flex items-center gap-1 text-xs text-gray-600 cursor-pointer">
                            <input type="checkbox" name="{{ $f }}" value="1" class="w-3 h-3 accent-blue-800"> {{ $l }}
                        </label>
                        @endforeach
                        <label class="flex items-center gap-1 text-xs text-red-600 cursor-pointer">
                            <input type="checkbox" name="denied" value="1" class="w-3 h-3" style="accent-color:#dc2626;"> Bloquer
                        </label>
                    </div>
                    <button type="submit" class="w-full py-1.5 rounded-lg text-white text-xs font-semibold border-0 cursor-pointer" style="background:var(--color-primary,#1E3A5F);">+ Ajouter</button>
                </form>
            </div>
        </div>

    </div>

    {{-- ══════════════════════════════════════════════════════════════════════
         BLOC 2 — VISIBILITÉ DES COLONNES
         Qui peut voir quelle colonne ?
    ══════════════════════════════════════════════════════════════════════════ --}}
    <div class="mb-2">
        <div class="flex items-center gap-3 mb-1">
            <span class="flex items-center justify-center w-8 h-8 rounded-full text-white font-bold text-sm"
                  style="background:var(--color-primary,#1E3A5F);">2</span>
            <h2 class="text-lg font-bold text-gray-800">Visibilité des colonnes</h2>
        </div>
        <p class="text-sm text-gray-500 ml-11">
            Ces règles contrôlent <strong>quelles colonnes sont visibles</strong> pour qui.
            Utile pour masquer des données sensibles (ex : salaire, coordonnées personnelles).
        </p>
    </div>

    {{-- Règle d'or colonnes --}}
    <div class="ml-11 mb-4 p-4 bg-blue-50 border border-blue-200 rounded-xl text-sm">
        <p class="font-bold text-blue-800 mb-2">📌 Comment ça fonctionne :</p>
        <ul class="space-y-1 text-blue-700 text-xs">
            <li>✅ <strong>Sans règle</strong> → la colonne est <strong>visible</strong> pour tous ceux qui ont accès à la grille.</li>
            <li>🔒 <strong>Règle "Masquée"</strong> → la colonne disparaît complètement pour ce rôle/département/utilisateur.</li>
            <li>👁 <strong>Règle "Visible"</strong> → force la visibilité même si une règle plus générale masque la colonne.</li>
            <li>⭐ <strong>L'individuel prime toujours</strong> : Marcel peut voir "Salaire" même si son service ne le voit pas.</li>
        </ul>
        <p class="text-xs text-blue-600 mt-2">
            ⚠️ <strong>Admin, Président / Maire et DGS</strong> voient toujours toutes les colonnes.
        </p>
    </div>

    <div class="ml-11">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">

            {{-- Règles existantes --}}
            @php
                $allColPerms = collect()
                    ->merge($colPerms['role']->map(fn($p) => ['type'=>'role','perm'=>$p,'label'=> \App\Enums\UserRole::tryFrom($p->subject_role)?->label() ?? $p->subject_role]))
                    ->merge($colPerms['department']->map(fn($p) => ['type'=>'dept','perm'=>$p,'label'=>$p->department?->name ?? '—']))
                    ->merge($colPerms['user']->map(fn($p) => ['type'=>'user','perm'=>$p,'label'=>$p->user?->name ?? '—']))
                    ->sortBy(fn($e) => $e['perm']->column_name);
            @endphp

            @if($allColPerms->isEmpty())
            <div class="px-5 py-6 text-center">
                <p class="text-2xl mb-2">👁</p>
                <p class="text-sm text-gray-500">Toutes les colonnes sont visibles par défaut.</p>
                <p class="text-xs text-gray-400 mt-1">Ajoutez une règle ci-dessous pour restreindre l'accès à une colonne.</p>
            </div>
            @else
            <table style="width:100%;border-collapse:collapse;font-size:12px;">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-400 border-b">Colonne</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-400 border-b">Type de sujet</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-400 border-b">Qui ?</th>
                        <th class="px-4 py-2 text-center text-xs font-semibold text-gray-400 border-b">Visibilité</th>
                        <th class="px-4 py-2 border-b"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($allColPerms as $entry)
                    @php $perm = $entry['perm']; @endphp
                    <tr class="border-b hover:bg-gray-50">
                        <td class="px-4 py-2 font-mono font-semibold text-blue-900 text-xs">{{ $perm->column_name }}</td>
                        <td class="px-4 py-2">
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-500">
                                {{ ['role'=>'👥 Rôle','dept'=>'🏢 Département','user'=>'👤 Utilisateur'][$entry['type']] }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-gray-700 font-medium text-xs">{{ $entry['label'] }}</td>
                        <td class="px-4 py-2 text-center">
                            @if($perm->denied || ! $perm->can_read)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-600">🔒 Masquée</span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold bg-green-100 text-green-600">👁 Visible</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-right">
                            <form method="POST"
                                  action="{{ route('admin.datagrid.permissions.column.destroy', [$table, $entry['type'], $perm->id]) }}">
                                @csrf @method('DELETE')
                                <button class="text-xs text-red-300 hover:text-red-500 cursor-pointer border-0 bg-transparent">✕</button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif

            {{-- Formulaire ajout --}}
            <div class="px-5 py-4 bg-gray-50 border-t border-gray-100">
                <details>
                    <summary class="text-xs font-semibold text-blue-700 cursor-pointer hover:underline">
                        + Ajouter une règle de visibilité pour une colonne
                    </summary>
                    <form method="POST" action="{{ route('admin.datagrid.permissions.column.store', $table) }}"
                          class="mt-4 grid grid-cols-2 gap-4" x-data="{ subjectType: 'role' }">
                        @csrf
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Quelle colonne ?</label>
                            <select name="column_name" required
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
                                <option value="">— Choisir</option>
                                @foreach($columns as $col)
                                <option value="{{ $col->name }}">{{ $col->label }} <span class="text-gray-400">({{ $col->name }})</span></option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Visibilité</label>
                            <div class="flex gap-4 mt-2">
                                <label class="flex items-center gap-2 cursor-pointer text-sm">
                                    <input type="radio" name="can_read" value="1" checked class="accent-blue-800"> 👁 Visible
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer text-sm">
                                    <input type="radio" name="can_read" value="0" class="accent-red-600"> 🔒 Masquée
                                </label>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Appliquer à</label>
                            <select name="subject_type" x-model="subjectType" required
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
                                <option value="role">👥 Un rôle entier</option>
                                <option value="department">🏢 Un département / service</option>
                                <option value="user">👤 Un utilisateur spécifique</option>
                            </select>
                        </div>
                        <div>
                            <div x-show="subjectType === 'role'">
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Quel rôle ?</label>
                                <select name="role" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
                                    <option value="">— Choisir</option>
                                    @foreach($roles as $role)<option value="{{ $role->value }}">{{ $role->label() }}</option>@endforeach
                                </select>
                            </div>
                            <div x-show="subjectType === 'department'">
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Quel département ?</label>
                                <select name="department_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
                                    <option value="">— Choisir</option>
                                    @foreach($departments as $dept)<option value="{{ $dept->id }}">{{ $dept->name }}</option>@endforeach
                                </select>
                            </div>
                            <div x-show="subjectType === 'user'">
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Quel utilisateur ?</label>
                                <select name="user_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
                                    <option value="">— Choisir</option>
                                    @foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-span-2">
                            <button type="submit"
                                    class="px-6 py-2 rounded-lg text-white text-sm font-semibold border-0 cursor-pointer"
                                    style="background:var(--color-primary,#1E3A5F);">
                                Enregistrer la règle
                            </button>
                        </div>
                    </form>
                </details>
            </div>
        </div>
    </div>

</div>
@endsection
