@extends('layouts.app')

@section('title', 'Droits — ' . $album->name)

@section('content')
<div class="max-w-7xl mx-auto px-4">

    {{-- Fil d'Ariane --}}
    <div class="flex items-center gap-2 mb-6 text-sm flex-wrap">
        <a href="{{ route('media.albums.index') }}" class="text-gray-400 hover:text-gray-600">← Photothèque</a>
        @foreach($album->ancestors() as $ancestor)
            <span class="text-gray-300">/</span>
            <a href="{{ route('media.albums.show', $ancestor) }}" class="text-gray-400 hover:text-gray-600">{{ $ancestor->name }}</a>
        @endforeach
        <span class="text-gray-300">/</span>
        <a href="{{ route('media.albums.show', $album) }}" class="text-gray-400 hover:text-gray-600">{{ $album->name }}</a>
        <span class="text-gray-300">/</span>
        <span class="font-semibold text-gray-800">🔐 Droits</span>
    </div>

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-800">Gestion des droits</h1>
            <p class="text-sm text-gray-500 mt-1">Album : <strong>{{ $album->name }}</strong></p>
        </div>
        <a href="{{ route('media.albums.show', $album) }}"
           class="text-sm text-gray-500 hover:text-gray-700 border border-gray-200 px-3 py-2 rounded-lg bg-white">
            ← Retour à l'album
        </a>
    </div>

    {{-- Flash --}}
    @if(session('success'))
        <div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">
            ✅ {{ session('success') }}
        </div>
    @endif

    {{-- Info héritage parent --}}
    @if(isset($inheritedFrom) && $inheritedFrom)
        <div class="mb-6 p-4 bg-blue-50 border border-blue-100 rounded-xl text-sm text-blue-700 flex items-start gap-3">
            <span class="text-lg">ℹ️</span>
            <div>
                <p class="font-semibold">Héritage actif</p>
                <p class="mt-0.5">Cet album hérite des droits de
                    <a href="{{ route('media.albums.permissions.edit', $inheritedFrom) }}" class="underline font-medium">
                        {{ $inheritedFrom->name }}
                    </a>
                    sauf si une permission explicite est définie ci-dessous.
                    Une permission explicite sur cet album <strong>prime sur l'héritage</strong>.
                </p>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

        {{-- ── Section 1 : Permissions par rôle ─────────────────────────── --}}
        <div class="bg-white rounded-xl shadow-md border border-gray-100 overflow-visible flex flex-col">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h2 class="font-semibold text-gray-800">👥 Par rôle</h2>
                <span class="text-xs text-gray-400">Niveau minimum d'accès requis</span>
            </div>

            {{-- Encart rôles avec accès total garanti --}}
            <div class="mx-5 mt-4 mb-1 flex items-start gap-2 p-3 bg-amber-50 border border-amber-100 rounded-lg text-xs text-amber-700">
                <span class="mt-0.5">🔒</span>
                <span>
                    <strong>Admin, Président et DGS</strong> ont toujours accès total à tous les albums,
                    quel que soit le paramétrage ci-dessous. Leurs droits ne peuvent pas être restreints.
                </span>
            </div>

            {{-- Permissions existantes --}}
            @if($permissions['role']->isNotEmpty())
                <div class="divide-y divide-gray-50 mt-3">
                    @foreach($permissions['role'] as $perm)
                        @php
                            $pivotRole = \App\Enums\UserRole::tryFrom($perm->subject_role);
                            $label = match($perm->subject_role) {
                                'resp_direction' => 'Resp. Direction et au-dessus',
                                'resp_service'   => 'Resp. Service et au-dessus',
                                'user'           => 'Tous les agents',
                                default          => $pivotRole?->label() ?? $perm->subject_role,
                            };
                        @endphp
                        <div class="px-5 py-3 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium text-gray-700">{{ $label }}</span>
                                @if($perm->subject_role === 'user')
                                    <span class="text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full">inclut Resp. Service + Resp. Direction</span>
                                @elseif($perm->subject_role === 'resp_service')
                                    <span class="text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full">inclut Resp. Direction</span>
                                @endif
                            </div>
                            <div class="flex items-center gap-3">
                                @include('media.albums._permission_level_badge', ['level' => $perm->level])
                                <form method="POST" action="{{ route('media.albums.permissions.destroy-subject', [$album, $perm]) }}">
                                    @csrf @method('DELETE')
                                    <button class="text-xs text-red-400 hover:text-red-600" title="Supprimer">✕</button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="px-5 py-4 text-sm text-gray-400 italic">Aucune permission par rôle définie.</p>
            @endif

            {{-- Formulaire ajout rôle minimum --}}
            <div class="px-5 py-4 bg-gray-50 border-t border-gray-100 mt-auto">
                <form method="POST" action="{{ route('media.albums.permissions.store-subject', $album) }}"
                      class="flex items-center gap-3 flex-wrap">
                    @csrf
                    <input type="hidden" name="subject_type" value="role">
                    <select name="subject_role" required
                            class="border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
                        <option value="">— Niveau minimum</option>
                        <option value="resp_direction">Resp. Direction et au-dessus</option>
                        <option value="resp_service">Resp. Service et au-dessus</option>
                        <option value="user">Tous les agents</option>
                    </select>
                    <select name="level" required
                            class="border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
                        @foreach($levels as $level)
                            @if($level['value'] !== 'none')
                                <option value="{{ $level['value'] }}">{{ $level['label'] }}</option>
                            @endif
                        @endforeach
                    </select>
                    <button type="submit"
                            class="px-4 py-1.5 rounded-lg text-white text-sm font-medium"
                            style="background-color: var(--color-primary, #1E3A5F);">
                        + Ajouter
                    </button>
                </form>
            </div>
        </div>

        {{-- ── Section 2 : Permissions par entité organisationnelle ──── --}}
        <div class="bg-white rounded-xl shadow-md border border-gray-100 overflow-visible flex flex-col">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h2 class="font-semibold text-gray-800">🏢 Par entité</h2>
                <span class="text-xs text-gray-400">Choisir une entité = tous ses membres</span>
            </div>
            <p class="mx-5 mt-3 text-xs text-gray-400 italic">
                💡 Pour partager uniquement avec le responsable, utilisez le bloc <strong>Par utilisateur</strong>.
            </p>

            @php
                // Tri hiérarchique : racines d'abord, puis leurs enfants indentés
                // On construit une liste à plat ordonnée depuis $deptTree
                $deptPermsById = $permissions['department']->keyBy(fn($p) => $p->subject_id);

                $flattenDeptPerms = function($nodes, $deptPermsById, $depth = 0) use (&$flattenDeptPerms): array {
                    $result = [];
                    foreach ($nodes as $node) {
                        if ($deptPermsById->has($node->id)) {
                            $result[] = ['perm' => $deptPermsById->get($node->id), 'depth' => $depth, 'dept' => $node];
                        }
                        if ($node->allChildren && $node->allChildren->isNotEmpty()) {
                            $result = array_merge($result, $flattenDeptPerms($node->allChildren, $deptPermsById, $depth + 1));
                        }
                    }
                    return $result;
                };

                $sortedDeptPerms = $flattenDeptPerms($deptTree, $deptPermsById);
            @endphp

            @if(count($sortedDeptPerms) > 0)
                <div class="mt-3">
                    @foreach($sortedDeptPerms as $entry)
                        @php
                            $perm  = $entry['perm'];
                            $dept  = $entry['dept'];
                            $depth = $entry['depth'];
                            $indent = $depth * 20; // px
                            $parentName = $depth > 0 ? ($dept->parentDept?->name ?? null) : null;
                        @endphp
                        <div class="py-3 pr-5 flex items-center justify-between border-b border-gray-50 last:border-0"
                             style="padding-left: {{ 20 + $indent }}px; {{ $depth > 0 ? 'background: rgba(249,250,251,0.6);' : '' }}">
                            <div class="flex items-center gap-2">
                                <span class="text-base">{{ $depth === 0 ? '🏢' : '📂' }}</span>
                                <div>
                                    <span class="text-sm font-medium text-gray-700">
                                        @if($dept->label)<span class="text-xs font-normal text-gray-400">{{ $dept->label }}</span> @endif
                                        {{ $dept->name }}
                                    </span>
                                    @if($parentName)
                                        <span class="block text-xs text-gray-400 mt-0.5">↳ {{ $parentName }}</span>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center gap-3 flex-wrap justify-end">
                                @if(in_array($dept->id, $redundancies))
                                    <span class="text-xs text-amber-600 bg-amber-50 border border-amber-100 px-2 py-0.5 rounded-full" title="Ce niveau est déjà accordé par une entité parente">⚠ redondant</span>
                                @endif
                                @if($perm->level->value === 'none')
                                    <span class="text-xs text-red-600 bg-red-50 border border-red-100 px-2 py-0.5 rounded-full" title="Bloque l'héritage — les membres de cette entité n'ont aucun accès">🚫 héritage bloqué</span>
                                @endif
                                @include('media.albums._permission_level_badge', ['level' => $perm->level])
                                <form method="POST" action="{{ route('media.albums.permissions.destroy-subject', [$album, $perm]) }}">
                                    @csrf @method('DELETE')
                                    <button class="text-xs text-red-400 hover:text-red-600">✕</button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="px-5 py-4 text-sm text-gray-400 italic">Aucune permission par entité définie.</p>
            @endif

            {{-- Select hiérarchique avec filtre Alpine.js --}}
            <div class="px-5 py-4 bg-gray-50 border-t border-gray-100 mt-auto">
                <form method="POST" action="{{ route('media.albums.permissions.store-subject', $album) }}"
                      x-data="{
                          search: '',
                          selectedId: '',
                          selectedType: '',
                          open: false,
                          items: @js($deptTreeFlat),
                          get filtered() {
                              if (!this.search) return this.items;
                              const q = this.search.toLowerCase();
                              return this.items.filter(i => i.label.toLowerCase().includes(q));
                          },
                          select(item) {
                              this.selectedId   = item.id;
                              this.selectedType = item.type;
                              this.search       = item.label;
                              this.open         = false;
                          },
                          clear() {
                              this.search = ''; this.selectedId = ''; this.selectedType = ''; this.open = false;
                          },
                          dropdownStyle: 'top:0;left:0;width:280px;',
                          positionDropdown() {
                              this.$nextTick(() => {
                                  const input = this.$refs.searchInput;
                                  if (!input) return;
                                  const rect = input.getBoundingClientRect();
                                  const spaceBelow = window.innerHeight - rect.bottom;
                                  const openUp = spaceBelow < 280 && rect.top > 280;
                                  this.dropdownStyle = [
                                      'position:fixed',
                                      'left:'       + Math.round(rect.left)  + 'px',
                                      'width:'      + Math.round(rect.width) + 'px',
                                      'max-height:196px',
                                      'overflow-y:scroll',
                                      'overscroll-behavior:contain',
                                      openUp
                                          ? 'bottom:' + Math.round(window.innerHeight - rect.top + 4) + 'px'
                                          : 'top:'    + Math.round(rect.bottom + 4) + 'px',
                                      'z-index:9999',
                                  ].join(';');
                              });
                          }
                      }"
                      class="space-y-3">
                    @csrf

                    {{-- Champ recherche --}}
                    <div class="relative" x-ref="searchWrapper">
                        <input type="text"
                               x-model="search"
                               x-ref="searchInput"
                               @focus="open = true; positionDropdown()"
                               @click.outside="open = false"
                               @input="selectedId = ''; selectedType = ''; positionDropdown()"
                               @keydown.escape="open = false"
                               placeholder="🔍 Rechercher une entité…"
                               autocomplete="off"
                               class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">

                        {{-- Dropdown en fixed pour échapper à overflow-hidden des parents --}}
                        <div x-show="open && filtered.length > 0"
                             x-transition:enter="transition ease-out duration-100"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-bind:style="dropdownStyle"
                             class="fixed z-50 bg-white border border-gray-200 rounded-lg shadow-xl overflow-y-scroll">
                            <template x-for="item in filtered" :key="item.id">
                                <div @click="select(item)"
                                     class="px-3 py-1.5 text-xs cursor-pointer hover:bg-blue-50 flex items-center gap-1.5"
                                     :class="{ 'bg-blue-50': selectedId == item.id }">
                                    <span x-text="'　'.repeat(item.depth)"></span>
                                    <span x-text="item.icon"></span>
                                    <span>
                                        <span x-show="item.typeLabel" x-text="item.typeLabel"
                                              class="text-xs text-gray-400 mr-1"></span>
                                        <span x-text="item.name" class="font-medium text-gray-700"></span>
                                    </span>
                                    <span x-show="item.parent" x-text="'↳ ' + item.parent"
                                          class="text-xs text-gray-400 ml-auto"></span>
                                </div>
                            </template>
                            <div x-show="filtered.length === 0"
                                 class="px-3 py-2 text-xs text-gray-400 italic">
                                Aucune entité trouvée.
                            </div>
                        </div>
                    </div>

                    <input type="hidden" name="subject_id"   x-bind:value="selectedId">
                    <input type="hidden" name="subject_type" x-bind:value="selectedType">

                    <div class="flex gap-2">
                        <select name="level" required
                                class="flex-1 border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
                            @foreach($levels as $level)
                                <option value="{{ $level['value'] }}">{{ $level['label'] }}</option>
                            @endforeach
                        </select>
                        <button type="submit"
                                :disabled="!selectedId"
                                class="px-4 py-1.5 rounded-lg text-white text-sm font-medium disabled:opacity-40"
                                style="background-color: var(--color-primary, #1E3A5F);">
                            + Ajouter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ── Section 4 : Permissions par utilisateur ─────────────────────── --}}
        <div class="bg-white rounded-xl shadow-md border border-gray-100 overflow-visible flex flex-col">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h2 class="font-semibold text-gray-800">👤 Par utilisateur</h2>
                <span class="text-xs text-gray-400">Override individuel — prime sur tout le reste</span>
            </div>

            @if($permissions['user']->isNotEmpty())
                <div class="divide-y divide-gray-50">
                    @foreach($permissions['user'] as $perm)
                        <div class="px-5 py-3 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold text-white"
                                     style="background-color: var(--color-primary, #1E3A5F);">
                                    {{ strtoupper(substr($perm->user?->name ?? '?', 0, 1)) }}
                                </div>
                                <span class="text-sm font-medium text-gray-700">{{ $perm->user?->name ?? '—' }}</span>
                                <span class="text-xs text-gray-400">
                                    {{ $perm->user?->role instanceof \App\Enums\UserRole ? $perm->user->role->label() : ($perm->user?->role ?? '') }}
                                </span>
                            </div>
                            <div class="flex items-center gap-3">
                                @include('media.albums._permission_level_badge', ['level' => $perm->level])
                                <form method="POST" action="{{ route('media.albums.permissions.destroy-user', [$album, $perm]) }}">
                                    @csrf @method('DELETE')
                                    <button class="text-xs text-red-400 hover:text-red-600">✕</button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="px-5 py-4 text-sm text-gray-400 italic">Aucune permission individuelle définie.</p>
            @endif

            <div class="px-5 py-4 bg-gray-50 border-t border-gray-100 mt-auto">
                <form method="POST" action="{{ route('media.albums.permissions.store-user', $album) }}"
                      class="flex items-center gap-3 flex-wrap">
                    @csrf
                    <select name="user_id" required
                            class="border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
                        <option value="">— Choisir un utilisateur</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                    <select name="level" required
                            class="border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
                        @foreach($levels as $level)
                            <option value="{{ $level['value'] }}">{{ $level['label'] }}</option>
                        @endforeach
                    </select>
                    <button type="submit"
                            class="px-4 py-1.5 rounded-lg text-white text-sm font-medium"
                            style="background-color: var(--color-primary, #1E3A5F);">
                        + Ajouter
                    </button>
                </form>
            </div>
        </div>

    </div>
</div>
@endsection
