@extends('layouts.app')
@section('title', 'Droits — '.$folder->name)

@push('styles')
@include('ged._ged_styles')
@endpush

@section('content')
<div id="ged-wrap">

    @include('ged._ged_sidebar', [
        'sidebarTree'    => $sidebarTree,
        'activeFolderId' => $folder->id,
        'ancestorIds'    => [],
    ])

    <div id="ged-main" style="overflow-y:auto;">

        {{-- ── En-tête ──────────────────────────────────────────── --}}
        <div id="ged-header">
            <div class="ged-breadcrumb">
                <a href="{{ route('ged.index') }}">GED</a>
                <span class="ged-breadcrumb-sep">›</span>
                <a href="{{ route('ged.folders.show', $folder) }}">{{ $folder->name }}</a>
                <span class="ged-breadcrumb-sep">›</span>
                <span class="ged-breadcrumb-current">Droits d'accès</span>
            </div>
            <div class="ged-header-right">
                <a href="{{ route('ged.folders.show', $folder) }}" class="pd-btn pd-btn-sm">
                    ← Retour au dossier
                </a>
            </div>
        </div>

        <div id="ged-content">

            {{-- Flash --}}
            @if(session('success'))
            <div style="background:rgba(46,204,113,0.08);border:1.5px solid rgba(46,204,113,0.3);border-radius:10px;padding:11px 16px;margin-bottom:20px;font-size:13px;color:#1a8a4a;display:flex;align-items:center;gap:8px;">
                <svg style="width:15px;height:15px;flex-shrink:0;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                {{ session('success') }}
            </div>
            @endif
            @if($errors->any())
            <div style="background:rgba(231,76,60,0.08);border:1.5px solid rgba(231,76,60,0.25);border-radius:10px;padding:11px 16px;margin-bottom:20px;font-size:13px;color:#c0392b;">
                {{ $errors->first() }}
            </div>
            @endif

            {{-- ── Titre section ──────────────────────────────────── --}}
            <div style="margin-bottom:20px;">
                <h2 style="font-family:'Sora',sans-serif;font-size:18px;font-weight:700;color:var(--pd-text);margin:0 0 3px;">
                    🔐 Droits d'accès — {{ $folder->name }}
                </h2>
                <p style="font-size:13px;color:var(--pd-muted);margin:0;">
                    Définissez qui peut consulter, télécharger, déposer des fichiers ou administrer ce dossier.
                    Les droits sont hérités par les sous-dossiers sauf override explicite.
                </p>
            </div>

            {{-- ── Légende des niveaux ─────────────────────────────── --}}
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:24px;">
                @foreach(\App\Enums\GedPermissionLevel::cases() as $lvl)
                @php
                    $colors = [
                        'none'     => ['bg'=>'rgba(107,124,150,0.1)','text'=>'#6b7c96','border'=>'rgba(107,124,150,0.2)'],
                        'view'     => ['bg'=>'rgba(59,154,225,0.08)','text'=>'#2176ae','border'=>'rgba(59,154,225,0.25)'],
                        'download' => ['bg'=>'rgba(46,204,113,0.08)','text'=>'#1a8a4a','border'=>'rgba(46,204,113,0.25)'],
                        'upload'   => ['bg'=>'rgba(232,168,56,0.1)','text'=>'#9a6a00','border'=>'rgba(232,168,56,0.3)'],
                        'admin'    => ['bg'=>'rgba(30,58,95,0.08)','text'=>'var(--pd-navy)','border'=>'rgba(30,58,95,0.2)'],
                    ];
                    $c = $colors[$lvl->value];
                @endphp
                <span style="display:inline-flex;align-items:center;gap:5px;
                             padding:4px 10px;border-radius:20px;font-size:11px;font-weight:600;
                             background:{{ $c['bg'] }};color:{{ $c['text'] }};border:1px solid {{ $c['border'] }};">
                    {{ $lvl->label() }}
                </span>
                @endforeach
                <span style="font-size:11px;color:var(--pd-muted);align-self:center;">— du plus restrictif au plus permissif →</span>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">

            {{-- ══════════════════════════════════════════════════
                 Colonne gauche — droits existants
            ══════════════════════════════════════════════════ --}}
            <div style="display:flex;flex-direction:column;gap:16px;">

                {{-- Par rôle --}}
                <div style="background:var(--pd-surface);border:1.5px solid var(--pd-border);border-radius:14px;overflow:hidden;box-shadow:var(--pd-shadow);">
                    <div style="padding:14px 18px;background:var(--pd-navy-dark);display:flex;align-items:center;justify-content:space-between;">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div style="width:30px;height:30px;border-radius:8px;background:rgba(255,255,255,0.12);display:flex;align-items:center;justify-content:center;font-size:15px;">👥</div>
                            <div>
                                <div style="font-size:13px;font-weight:700;color:#fff;">Par rôle</div>
                                <div style="font-size:11px;color:rgba(255,255,255,0.55);">S'applique à tous les utilisateurs du rôle et au-dessus</div>
                            </div>
                        </div>
                        <span style="font-size:11px;font-weight:600;color:rgba(255,255,255,0.4);background:rgba(255,255,255,0.08);padding:2px 9px;border-radius:10px;">
                            {{ $perms['role']->count() }}
                        </span>
                    </div>
                    <div style="padding:4px 0;">
                        @forelse($perms['role'] as $perm)
                        @php $c = $colors[$perm->level->value] ?? $colors['view']; @endphp
                        <div style="display:flex;align-items:center;gap:12px;padding:10px 18px;border-bottom:1px solid var(--pd-border);">
                            <div style="flex:1;font-size:13px;color:var(--pd-text);font-weight:500;">
                                {{ \App\Enums\UserRole::from($perm->subject_role)->label() }}
                            </div>
                            <span style="padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;
                                         background:{{ $c['bg'] }};color:{{ $c['text'] }};border:1px solid {{ $c['border'] }};">
                                {{ $perm->level->label() }}
                            </span>
                            <form method="POST" action="{{ route('ged.permissions.destroy-subject', $folder) }}" style="margin:0;">
                                @csrf @method('DELETE')
                                <input type="hidden" name="permission_id" value="{{ $perm->id }}">
                                <button type="submit" title="Supprimer"
                                        style="width:24px;height:24px;border:none;background:none;cursor:pointer;border-radius:5px;
                                               color:var(--pd-muted);font-size:14px;display:flex;align-items:center;justify-content:center;transition:background .1s;"
                                        onmouseover="this.style.background='#fee2e2';this.style.color='#e74c3c'"
                                        onmouseout="this.style.background='none';this.style.color='var(--pd-muted)'">✕</button>
                            </form>
                        </div>
                        @empty
                        <div style="padding:20px;text-align:center;font-size:13px;color:var(--pd-muted);">
                            Aucun droit par rôle défini
                        </div>
                        @endforelse
                    </div>
                </div>

                {{-- Par département --}}
                <div style="background:var(--pd-surface);border:1.5px solid var(--pd-border);border-radius:14px;overflow:hidden;box-shadow:var(--pd-shadow);">
                    <div style="padding:14px 18px;background:var(--pd-navy-dark);display:flex;align-items:center;justify-content:space-between;">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div style="width:30px;height:30px;border-radius:8px;background:rgba(255,255,255,0.12);display:flex;align-items:center;justify-content:center;font-size:15px;">🏢</div>
                            <div>
                                <div style="font-size:13px;font-weight:700;color:#fff;">Par direction / service</div>
                                <div style="font-size:11px;color:rgba(255,255,255,0.55);">S'applique à tous les membres du département</div>
                            </div>
                        </div>
                        <span style="font-size:11px;font-weight:600;color:rgba(255,255,255,0.4);background:rgba(255,255,255,0.08);padding:2px 9px;border-radius:10px;">
                            {{ $perms['department']->count() }}
                        </span>
                    </div>
                    <div style="padding:4px 0;">
                        @forelse($perms['department'] as $perm)
                        @php $c = $colors[$perm->level->value] ?? $colors['view']; @endphp
                        <div style="display:flex;align-items:center;gap:12px;padding:10px 18px;border-bottom:1px solid var(--pd-border);">
                            <div style="flex:1;">
                                <div style="font-size:13px;color:var(--pd-text);font-weight:500;">{{ $perm->department?->name ?? '—' }}</div>
                                <div style="font-size:11px;color:var(--pd-muted);">{{ ucfirst($perm->subject_type) }}</div>
                            </div>
                            <span style="padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;
                                         background:{{ $c['bg'] }};color:{{ $c['text'] }};border:1px solid {{ $c['border'] }};">
                                {{ $perm->level->label() }}
                            </span>
                            <form method="POST" action="{{ route('ged.permissions.destroy-subject', $folder) }}" style="margin:0;">
                                @csrf @method('DELETE')
                                <input type="hidden" name="permission_id" value="{{ $perm->id }}">
                                <button type="submit" title="Supprimer"
                                        style="width:24px;height:24px;border:none;background:none;cursor:pointer;border-radius:5px;
                                               color:var(--pd-muted);font-size:14px;display:flex;align-items:center;justify-content:center;transition:background .1s;"
                                        onmouseover="this.style.background='#fee2e2';this.style.color='#e74c3c'"
                                        onmouseout="this.style.background='none';this.style.color='var(--pd-muted)'">✕</button>
                            </form>
                        </div>
                        @empty
                        <div style="padding:20px;text-align:center;font-size:13px;color:var(--pd-muted);">
                            Aucun droit par département défini
                        </div>
                        @endforelse
                    </div>
                </div>

                {{-- Par utilisateur --}}
                <div style="background:var(--pd-surface);border:1.5px solid var(--pd-border);border-radius:14px;overflow:hidden;box-shadow:var(--pd-shadow);">
                    <div style="padding:14px 18px;background:var(--pd-navy-dark);display:flex;align-items:center;justify-content:space-between;">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div style="width:30px;height:30px;border-radius:8px;background:rgba(255,255,255,0.12);display:flex;align-items:center;justify-content:center;font-size:15px;">👤</div>
                            <div>
                                <div style="font-size:13px;font-weight:700;color:#fff;">Par utilisateur</div>
                                <div style="font-size:11px;color:rgba(255,255,255,0.55);">Prioritaire sur tous les autres droits</div>
                            </div>
                        </div>
                        <span style="font-size:11px;font-weight:600;color:rgba(255,255,255,0.4);background:rgba(255,255,255,0.08);padding:2px 9px;border-radius:10px;">
                            {{ $perms['user']->count() }}
                        </span>
                    </div>
                    <div style="padding:4px 0;">
                        @forelse($perms['user'] as $perm)
                        @php $c = $colors[$perm->level->value] ?? $colors['view']; @endphp
                        <div style="display:flex;align-items:center;gap:12px;padding:10px 18px;border-bottom:1px solid var(--pd-border);">
                            <div style="width:30px;height:30px;border-radius:8px;flex-shrink:0;
                                        background:linear-gradient(135deg,var(--pd-navy-dark),var(--pd-accent));
                                        display:flex;align-items:center;justify-content:center;
                                        font-size:11px;font-weight:700;color:#fff;">
                                {{ strtoupper(substr($perm->user?->name ?? '?', 0, 2)) }}
                            </div>
                            <div style="flex:1;font-size:13px;color:var(--pd-text);font-weight:500;">
                                {{ $perm->user?->name ?? '—' }}
                            </div>
                            <span style="padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;
                                         background:{{ $c['bg'] }};color:{{ $c['text'] }};border:1px solid {{ $c['border'] }};">
                                {{ $perm->level->label() }}
                            </span>
                            <form method="POST" action="{{ route('ged.permissions.destroy-user', $folder) }}" style="margin:0;">
                                @csrf @method('DELETE')
                                <input type="hidden" name="permission_id" value="{{ $perm->id }}">
                                <button type="submit" title="Supprimer"
                                        style="width:24px;height:24px;border:none;background:none;cursor:pointer;border-radius:5px;
                                               color:var(--pd-muted);font-size:14px;display:flex;align-items:center;justify-content:center;transition:background .1s;"
                                        onmouseover="this.style.background='#fee2e2';this.style.color='#e74c3c'"
                                        onmouseout="this.style.background='none';this.style.color='var(--pd-muted)'">✕</button>
                            </form>
                        </div>
                        @empty
                        <div style="padding:20px;text-align:center;font-size:13px;color:var(--pd-muted);">
                            Aucun droit individuel défini
                        </div>
                        @endforelse
                    </div>
                </div>

            </div>{{-- /col gauche --}}

            {{-- ══════════════════════════════════════════════════
                 Colonne droite — formulaires d'ajout
            ══════════════════════════════════════════════════ --}}
            <div style="display:flex;flex-direction:column;gap:16px;">

                {{-- Ajouter par rôle --}}
                <div style="background:var(--pd-surface);border:1.5px solid var(--pd-border);border-radius:14px;overflow:hidden;box-shadow:var(--pd-shadow);">
                    <div style="padding:14px 18px;background:var(--pd-surface2);border-bottom:1px solid var(--pd-border);display:flex;align-items:center;gap:10px;">
                        <div style="width:30px;height:30px;border-radius:8px;background:rgba(30,58,95,0.1);display:flex;align-items:center;justify-content:center;font-size:15px;">+</div>
                        <div style="font-size:13px;font-weight:700;color:var(--pd-navy);">Accorder un droit par rôle</div>
                    </div>
                    <form method="POST" action="{{ route('ged.permissions.set-role', $folder) }}" style="padding:18px;display:flex;flex-direction:column;gap:12px;">
                        @csrf
                        <div>
                            <label style="display:block;font-size:11px;font-weight:600;color:var(--pd-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">Rôle</label>
                            <select name="role" class="pd-input" style="width:100%;" required>
                                @foreach($roles as $role)
                                <option value="{{ $role->value }}">{{ $role->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label style="display:block;font-size:11px;font-weight:600;color:var(--pd-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">Niveau d'accès</label>
                            <select name="level" class="pd-input" style="width:100%;" required>
                                @foreach(\App\Enums\GedPermissionLevel::options() as $opt)
                                <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit"
                                style="padding:9px 18px;border-radius:10px;border:none;cursor:pointer;font-size:13px;font-weight:600;color:#fff;
                                       background:linear-gradient(135deg,var(--pd-navy-dark),var(--pd-navy-light));transition:opacity .2s;align-self:flex-end;"
                                onmouseover="this.style.opacity='.88'" onmouseout="this.style.opacity='1'">
                            Appliquer
                        </button>
                    </form>
                </div>

                {{-- Ajouter par département --}}
                <div style="background:var(--pd-surface);border:1.5px solid var(--pd-border);border-radius:14px;overflow:hidden;box-shadow:var(--pd-shadow);">
                    <div style="padding:14px 18px;background:var(--pd-surface2);border-bottom:1px solid var(--pd-border);display:flex;align-items:center;gap:10px;">
                        <div style="width:30px;height:30px;border-radius:8px;background:rgba(30,58,95,0.1);display:flex;align-items:center;justify-content:center;font-size:15px;">+</div>
                        <div style="font-size:13px;font-weight:700;color:var(--pd-navy);">Accorder un droit par département</div>
                    </div>
                    <form method="POST" action="{{ route('ged.permissions.set-department', $folder) }}" style="padding:18px;display:flex;flex-direction:column;gap:12px;">
                        @csrf
                        <div>
                            <label style="display:block;font-size:11px;font-weight:600;color:var(--pd-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">Direction / Service</label>
                            <select name="department_id" class="pd-input" style="width:100%;" required>
                                <option value="">— Choisir —</option>
                                @foreach($departments->groupBy('type') as $type => $depts)
                                <optgroup label="{{ ucfirst($type) }}">
                                    @foreach($depts as $dept)
                                    <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                                    @endforeach
                                </optgroup>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label style="display:block;font-size:11px;font-weight:600;color:var(--pd-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">Niveau d'accès</label>
                            <select name="level" class="pd-input" style="width:100%;" required>
                                @foreach(\App\Enums\GedPermissionLevel::options() as $opt)
                                <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit"
                                style="padding:9px 18px;border-radius:10px;border:none;cursor:pointer;font-size:13px;font-weight:600;color:#fff;
                                       background:linear-gradient(135deg,var(--pd-navy-dark),var(--pd-navy-light));transition:opacity .2s;align-self:flex-end;"
                                onmouseover="this.style.opacity='.88'" onmouseout="this.style.opacity='1'">
                            Appliquer
                        </button>
                    </form>
                </div>

                {{-- Ajouter par utilisateur --}}
                <div style="background:var(--pd-surface);border:1.5px solid var(--pd-border);border-radius:14px;overflow:hidden;box-shadow:var(--pd-shadow);">
                    <div style="padding:14px 18px;background:var(--pd-surface2);border-bottom:1px solid var(--pd-border);display:flex;align-items:center;gap:10px;">
                        <div style="width:30px;height:30px;border-radius:8px;background:rgba(30,58,95,0.1);display:flex;align-items:center;justify-content:center;font-size:15px;">+</div>
                        <div style="font-size:13px;font-weight:700;color:var(--pd-navy);">Accorder un droit individuel</div>
                    </div>
                    <form method="POST" action="{{ route('ged.permissions.set-user', $folder) }}" style="padding:18px;display:flex;flex-direction:column;gap:12px;">
                        @csrf
                        <div>
                            <label style="display:block;font-size:11px;font-weight:600;color:var(--pd-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">Utilisateur</label>
                            <select name="user_id" class="pd-input" style="width:100%;" required>
                                <option value="">— Choisir —</option>
                                @foreach($users as $u)
                                <option value="{{ $u->id }}">{{ $u->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label style="display:block;font-size:11px;font-weight:600;color:var(--pd-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">Niveau d'accès</label>
                            <select name="level" class="pd-input" style="width:100%;" required>
                                @foreach(\App\Enums\GedPermissionLevel::options() as $opt)
                                <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit"
                                style="padding:9px 18px;border-radius:10px;border:none;cursor:pointer;font-size:13px;font-weight:600;color:#fff;
                                       background:linear-gradient(135deg,var(--pd-navy-dark),var(--pd-navy-light));transition:opacity .2s;align-self:flex-end;"
                                onmouseover="this.style.opacity='.88'" onmouseout="this.style.opacity='1'">
                            Appliquer
                        </button>
                    </form>
                </div>

                {{-- Info héritage --}}
                <div style="background:rgba(59,154,225,0.06);border:1.5px solid rgba(59,154,225,0.2);border-radius:12px;padding:14px 16px;">
                    <div style="font-size:12px;font-weight:600;color:var(--pd-navy);margin-bottom:6px;">💡 Comment fonctionne l'héritage</div>
                    <ul style="margin:0;padding-left:16px;font-size:12px;color:var(--pd-muted);line-height:1.7;">
                        <li>Les sous-dossiers héritent automatiquement des droits du dossier parent</li>
                        <li>Un droit sur un sous-dossier remplace l'héritage (<em>override</em>)</li>
                        <li>Le niveau <strong>Aucun droit</strong> bloque explicitement l'accès même si le parent accorde des droits</li>
                        <li>Priorité : utilisateur &gt; service &gt; direction &gt; rôle &gt; héritage parent</li>
                        <li>Admin, Président et DGS ont toujours accès complet</li>
                    </ul>
                </div>

            </div>{{-- /col droite --}}
            </div>{{-- /grid --}}

        </div>{{-- /ged-content --}}
    </div>{{-- /ged-main --}}
</div>{{-- /ged-wrap --}}
@endsection
