@extends('layouts.app')
@section('title', 'Droits — ' . $table->label)

@section('content')
<div class="max-w-7xl mx-auto px-4">

    {{-- ── Fil d'Ariane ───────────────────────────────────────────────────── --}}
    <div class="flex items-center gap-2 mb-6 text-sm flex-wrap">
        <a href="{{ route('admin.datagrid.index') }}" class="text-gray-400 hover:text-gray-600">← Grilles DataGrid</a>
        <span class="text-gray-300">/</span>
        <a href="{{ route('admin.datagrid.edit', $table) }}" class="text-gray-400 hover:text-gray-600">{{ $table->label }}</a>
        <span class="text-gray-300">/</span>
        <span class="font-semibold text-gray-800">🔐 Droits</span>
    </div>

    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-800">Gestion des droits</h1>
            <p class="text-sm text-gray-500 mt-1">Grille : <strong>{{ $table->label }}</strong></p>
        </div>
        <a href="{{ route('admin.datagrid.edit', $table) }}"
           class="text-sm text-gray-500 hover:text-gray-700 border border-gray-200 px-3 py-2 rounded-lg bg-white no-underline">
            ← Retour à la grille
        </a>
    </div>

    {{-- Flash --}}
    @if(session('success'))
    <div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">
        ✅ {{ session('success') }}
    </div>
    @endif

    {{-- Info priorité --}}
    <div class="mb-6 p-4 bg-blue-50 border border-blue-100 rounded-xl text-sm text-blue-800 flex items-start gap-3">
        <span class="text-lg mt-0.5">ℹ️</span>
        <div>
            <p class="font-semibold">Règle de priorité (décroissante)</p>
            <p class="mt-1 text-blue-700">
                <strong>Individuel</strong> (prime sur tout)
                &nbsp;›&nbsp; <strong>Département</strong> (remonte la hiérarchie)
                &nbsp;›&nbsp; <strong>Rôle</strong>
                &nbsp;›&nbsp; Aucun droit par défaut.
                <br>Admin, Président et DGS ont toujours tous les droits.
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

        {{-- ── Par rôle ─────────────────────────────────────────────────────── --}}
        <div class="bg-white rounded-xl shadow-md border border-gray-100 overflow-visible flex flex-col">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h2 class="font-semibold text-gray-800">👥 Par rôle</h2>
                <span class="text-xs text-gray-400">Niveau minimum requis</span>
            </div>

            <div class="mx-5 mt-4 mb-1 flex items-start gap-2 p-3 bg-amber-50 border border-amber-100 rounded-lg text-xs text-amber-700">
                <span class="mt-0.5">🔒</span>
                <span><strong>Admin, Président et DGS</strong> ont toujours tous les droits, quel que soit le paramétrage.</span>
            </div>

            @if($perms['role']->isNotEmpty())
            <div class="divide-y divide-gray-50 mt-3">
                @foreach($perms['role'] as $perm)
                <div class="px-5 py-3 flex items-center justify-between gap-2">
                    <div>
                        <div class="text-sm font-medium text-gray-700">
                            {{ \App\Enums\UserRole::tryFrom($perm->subject_role)?->label() ?? $perm->subject_role }}
                        </div>
                        @if($perm->denied)
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-600">Bloqué</span>
                        @else
                            <div class="flex flex-wrap gap-1 mt-1">
                                @foreach(['can_read' => 'Lire', 'can_write' => 'Écrire', 'can_delete' => 'Supprimer', 'can_export' => 'Exporter'] as $flag => $lbl)
                                @if($perm->$flag)
                                <span class="inline-flex px-1.5 py-0.5 rounded text-xs bg-green-100 text-green-700">{{ $lbl }}</span>
                                @endif
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <form method="POST" action="{{ route('admin.datagrid.permissions.role.destroy', [$table, $perm]) }}">
                        @csrf @method('DELETE')
                        <button class="text-xs text-red-400 hover:text-red-600 flex-shrink-0">✕</button>
                    </form>
                </div>
                @endforeach
            </div>
            @else
            <p class="px-5 py-4 text-sm text-gray-400 italic">Aucune règle par rôle.</p>
            @endif

            <div class="px-5 py-4 bg-gray-50 border-t border-gray-100 mt-auto">
                <form method="POST" action="{{ route('admin.datagrid.permissions.role.store', $table) }}"
                      class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Rôle</label>
                        <select name="role" required
                                class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
                            <option value="">— Choisir</option>
                            @foreach($roles as $role)
                            <option value="{{ $role->value }}">{{ $role->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        @foreach(['can_read' => 'Lire', 'can_write' => 'Écrire', 'can_delete' => 'Suppr.', 'can_export' => 'Export'] as $flag => $lbl)
                        <label class="flex items-center gap-1.5 text-xs text-gray-600 cursor-pointer">
                            <input type="checkbox" name="{{ $flag }}" value="1" class="w-3.5 h-3.5 accent-blue-800"> {{ $lbl }}
                        </label>
                        @endforeach
                        <label class="flex items-center gap-1.5 text-xs text-red-600 cursor-pointer">
                            <input type="checkbox" name="denied" value="1" class="w-3.5 h-3.5" style="accent-color:#dc2626;"> Bloquer
                        </label>
                    </div>
                    <button type="submit"
                            class="w-full px-4 py-1.5 rounded-lg text-white text-sm font-medium"
                            style="background-color:var(--color-primary,#1E3A5F);">
                        + Ajouter
                    </button>
                </form>
            </div>
        </div>

        {{-- ── Par département ──────────────────────────────────────────────── --}}
        <div class="bg-white rounded-xl shadow-md border border-gray-100 overflow-visible flex flex-col">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h2 class="font-semibold text-gray-800">🏢 Par département</h2>
                <span class="text-xs text-gray-400">Tous ses membres</span>
            </div>

            @if($perms['department']->isNotEmpty())
            <div class="divide-y divide-gray-50 mt-3">
                @foreach($perms['department'] as $perm)
                <div class="px-5 py-3 flex items-center justify-between gap-2">
                    <div>
                        <div class="text-sm font-medium text-gray-700">{{ $perm->department?->name ?? '—' }}</div>
                        @if($perm->denied)
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-600">Bloqué</span>
                        @else
                            <div class="flex flex-wrap gap-1 mt-1">
                                @foreach(['can_read' => 'Lire', 'can_write' => 'Écrire', 'can_delete' => 'Supprimer', 'can_export' => 'Exporter'] as $flag => $lbl)
                                @if($perm->$flag)
                                <span class="inline-flex px-1.5 py-0.5 rounded text-xs bg-green-100 text-green-700">{{ $lbl }}</span>
                                @endif
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <form method="POST" action="{{ route('admin.datagrid.permissions.dept.destroy', [$table, $perm]) }}">
                        @csrf @method('DELETE')
                        <button class="text-xs text-red-400 hover:text-red-600 flex-shrink-0">✕</button>
                    </form>
                </div>
                @endforeach
            </div>
            @else
            <p class="px-5 py-4 text-sm text-gray-400 italic">Aucune règle par département.</p>
            @endif

            <div class="px-5 py-4 bg-gray-50 border-t border-gray-100 mt-auto">
                <form method="POST" action="{{ route('admin.datagrid.permissions.dept.store', $table) }}"
                      class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Département / Service</label>
                        <select name="department_id" required
                                class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
                            <option value="">— Choisir</option>
                            @foreach($departments as $dept)
                            <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        @foreach(['can_read' => 'Lire', 'can_write' => 'Écrire', 'can_delete' => 'Suppr.', 'can_export' => 'Export'] as $flag => $lbl)
                        <label class="flex items-center gap-1.5 text-xs text-gray-600 cursor-pointer">
                            <input type="checkbox" name="{{ $flag }}" value="1" class="w-3.5 h-3.5 accent-blue-800"> {{ $lbl }}
                        </label>
                        @endforeach
                        <label class="flex items-center gap-1.5 text-xs text-red-600 cursor-pointer">
                            <input type="checkbox" name="denied" value="1" class="w-3.5 h-3.5" style="accent-color:#dc2626;"> Bloquer
                        </label>
                    </div>
                    <button type="submit"
                            class="w-full px-4 py-1.5 rounded-lg text-white text-sm font-medium"
                            style="background-color:var(--color-primary,#1E3A5F);">
                        + Ajouter
                    </button>
                </form>
            </div>
        </div>

        {{-- ── Par utilisateur ─────────────────────────────────────────────── --}}
        <div class="bg-white rounded-xl shadow-md border border-gray-100 overflow-visible flex flex-col">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h2 class="font-semibold text-gray-800">👤 Par utilisateur</h2>
                <span class="text-xs text-gray-400">Override individuel — prime sur tout</span>
            </div>

            @if($perms['user']->isNotEmpty())
            <div class="divide-y divide-gray-50">
                @foreach($perms['user'] as $perm)
                <div class="px-5 py-3 flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2 min-w-0">
                        <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold text-white flex-shrink-0"
                             style="background-color:var(--color-primary,#1E3A5F);">
                            {{ strtoupper(substr($perm->user?->name ?? '?', 0, 1)) }}
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm font-medium text-gray-700 truncate">{{ $perm->user?->name ?? '—' }}</div>
                            @if($perm->denied)
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-600">Bloqué</span>
                            @else
                                <div class="flex flex-wrap gap-1 mt-1">
                                    @foreach(['can_read' => 'Lire', 'can_write' => 'Écrire', 'can_delete' => 'Supprimer', 'can_export' => 'Exporter'] as $flag => $lbl)
                                    @if($perm->$flag)
                                    <span class="inline-flex px-1.5 py-0.5 rounded text-xs bg-green-100 text-green-700">{{ $lbl }}</span>
                                    @endif
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                    <form method="POST" action="{{ route('admin.datagrid.permissions.user.destroy', [$table, $perm]) }}">
                        @csrf @method('DELETE')
                        <button class="text-xs text-red-400 hover:text-red-600 flex-shrink-0">✕</button>
                    </form>
                </div>
                @endforeach
            </div>
            @else
            <p class="px-5 py-4 text-sm text-gray-400 italic">Aucune permission individuelle.</p>
            @endif

            <div class="px-5 py-4 bg-gray-50 border-t border-gray-100 mt-auto">
                <form method="POST" action="{{ route('admin.datagrid.permissions.user.store', $table) }}"
                      class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Utilisateur</label>
                        <select name="user_id" required
                                class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
                            <option value="">— Choisir un utilisateur</option>
                            @foreach($users as $u)
                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        @foreach(['can_read' => 'Lire', 'can_write' => 'Écrire', 'can_delete' => 'Suppr.', 'can_export' => 'Export'] as $flag => $lbl)
                        <label class="flex items-center gap-1.5 text-xs text-gray-600 cursor-pointer">
                            <input type="checkbox" name="{{ $flag }}" value="1" class="w-3.5 h-3.5 accent-blue-800"> {{ $lbl }}
                        </label>
                        @endforeach
                        <label class="flex items-center gap-1.5 text-xs text-red-600 cursor-pointer">
                            <input type="checkbox" name="denied" value="1" class="w-3.5 h-3.5" style="accent-color:#dc2626;"> Bloquer
                        </label>
                    </div>
                    <button type="submit"
                            class="w-full px-4 py-1.5 rounded-lg text-white text-sm font-medium"
                            style="background-color:var(--color-primary,#1E3A5F);">
                        + Ajouter
                    </button>
                </form>
            </div>
        </div>

    </div>
</div>
@endsection
