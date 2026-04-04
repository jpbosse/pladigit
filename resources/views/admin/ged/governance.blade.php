@extends('layouts.admin')
@section('title', 'GED — Gouvernance')

@section('admin-content')

<div style="max-width:900px;">

    <div style="margin-bottom:24px;">
        <h1 style="font-size:22px;font-weight:700;color:var(--pd-navy);margin:0 0 4px;">Gouvernance GED</h1>
        <p style="font-size:13px;color:var(--pd-muted);margin:0;">
            Transfert de propriété, ressources orphelines et supervision des droits.
        </p>
    </div>

    @if(session('success'))
    <div style="background:#F0FDF4;border:0.5px solid #86EFAC;color:#065F46;border-radius:8px;padding:10px 16px;margin-bottom:20px;font-size:13px;display:flex;align-items:center;gap:8px;">
        <svg style="width:15px;height:15px;fill:none;stroke:currentColor;stroke-width:2.5;flex-shrink:0;" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
        {{ session('success') }}
    </div>
    @endif

    @if($errors->any())
    <div style="background:#FEF2F2;border:0.5px solid #FCA5A5;color:#991B1B;border-radius:8px;padding:10px 16px;margin-bottom:20px;font-size:13px;">
        @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
    </div>
    @endif

    {{-- ── Transfert de propriété ───────────────────────────────────── --}}
    <div style="background:var(--pd-surface);border:0.5px solid var(--pd-border);border-radius:12px;margin-bottom:20px;overflow:hidden;">

        <div style="padding:14px 20px;background:var(--pd-surface2);border-bottom:0.5px solid var(--pd-border);display:flex;align-items:center;gap:10px;">
            <div style="width:32px;height:32px;border-radius:8px;background:rgba(180,83,9,0.1);display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;">🔄</div>
            <div>
                <div style="font-size:14px;font-weight:700;color:var(--pd-navy);">Transfert de propriété</div>
                <div style="font-size:11px;color:var(--pd-muted);">
                    Transfère tous les dossiers et documents d'un utilisateur (ex. démissionnaire) vers un autre compte.
                    Les permissions individuelles sont également réaffectées.
                </div>
            </div>
        </div>

        <div style="padding:20px;">
            <form method="POST"
                  action="{{ route('admin.ged.transfer-ownership') }}"
                  onsubmit="return confirm('Confirmer le transfert de toutes les ressources GED de cet utilisateur ?')">
                @csrf

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:16px;">

                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:var(--pd-navy);margin-bottom:6px;letter-spacing:0.02em;">
                            De — utilisateur source
                        </label>
                        <select name="from_user_id" class="pd-input" required style="width:100%;">
                            <option value="">Choisir l'utilisateur source…</option>
                            @foreach($users as $u)
                                <option value="{{ $u->id }}" {{ old('from_user_id') == $u->id ? 'selected' : '' }}>
                                    {{ $u->name }}@if($u->role) — {{ \App\Enums\UserRole::tryFrom($u->role)?->label() }}@endif
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:var(--pd-navy);margin-bottom:6px;letter-spacing:0.02em;">
                            Vers — utilisateur cible
                        </label>
                        <select name="to_user_id" class="pd-input" required style="width:100%;">
                            <option value="">Choisir l'utilisateur cible…</option>
                            @foreach($users as $u)
                                <option value="{{ $u->id }}" {{ old('to_user_id') == $u->id ? 'selected' : '' }}>
                                    {{ $u->name }}@if($u->role) — {{ \App\Enums\UserRole::tryFrom($u->role)?->label() }}@endif
                                </option>
                            @endforeach
                        </select>
                    </div>

                </div>

                <div style="display:flex;align-items:center;gap:12px;padding-top:4px;">
                    <button type="submit"
                            style="display:inline-flex;align-items:center;gap:7px;
                                   padding:8px 18px;border-radius:8px;border:none;cursor:pointer;
                                   font-size:13px;font-weight:600;
                                   background:linear-gradient(135deg,#92400E,#B45309);
                                   color:#fff;transition:opacity .15s;"
                            onmouseover="this.style.opacity='.85'"
                            onmouseout="this.style.opacity='1'">
                        <svg style="width:14px;height:14px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;" viewBox="0 0 24 24"><polyline points="16 3 21 3 21 8"/><line x1="4" y1="20" x2="21" y2="3"/><polyline points="21 16 21 21 16 21"/><line x1="15" y1="15" x2="21" y2="21"/></svg>
                        Transférer la propriété
                    </button>
                    <span style="font-size:11px;color:var(--pd-muted);">Cette opération est irréversible — vérifiez les sélections avant de confirmer.</span>
                </div>

            </form>
        </div>
    </div>

    {{-- ── Dossiers orphelins ───────────────────────────────────────── --}}
    <div style="background:var(--pd-surface);border:0.5px solid var(--pd-border);border-radius:12px;margin-bottom:20px;overflow:hidden;">

        <div style="padding:14px 20px;background:var(--pd-surface2);border-bottom:0.5px solid var(--pd-border);display:flex;align-items:center;gap:10px;">
            <div style="width:32px;height:32px;border-radius:8px;background:rgba(30,58,95,0.1);display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;">📁</div>
            <div style="flex:1;">
                <div style="font-size:14px;font-weight:700;color:var(--pd-navy);display:flex;align-items:center;gap:8px;">
                    Dossiers orphelins
                    <span style="display:inline-flex;align-items:center;justify-content:center;
                                 min-width:20px;height:20px;padding:0 6px;border-radius:10px;
                                 font-size:11px;font-weight:700;
                                 background:{{ $orphanFolders->isEmpty() ? 'rgba(5,150,105,0.12)' : 'rgba(220,38,38,0.12)' }};
                                 color:{{ $orphanFolders->isEmpty() ? '#059669' : '#DC2626' }};">
                        {{ $orphanFolders->count() }}
                    </span>
                </div>
                <div style="font-size:11px;color:var(--pd-muted);">
                    Dossiers dont le créateur n'a plus de compte actif — utilisez le transfert ci-dessus pour les réattribuer.
                </div>
            </div>
        </div>

        @if($orphanFolders->isEmpty())
        <div style="padding:20px;display:flex;align-items:center;gap:10px;color:var(--pd-muted);font-size:13px;">
            <svg style="width:16px;height:16px;fill:none;stroke:#059669;stroke-width:2;" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
            <span style="color:#059669;">Aucun dossier orphelin.</span>
        </div>
        @else
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <thead>
                    <tr style="background:var(--pd-navy);">
                        <th style="padding:10px 16px;text-align:left;font-size:11px;font-weight:700;color:rgba(255,255,255,0.7);letter-spacing:0.06em;text-transform:uppercase;">Dossier</th>
                        <th style="padding:10px 16px;text-align:left;font-size:11px;font-weight:700;color:rgba(255,255,255,0.7);letter-spacing:0.06em;text-transform:uppercase;">Chemin</th>
                        <th style="padding:10px 16px;text-align:center;font-size:11px;font-weight:700;color:rgba(255,255,255,0.7);letter-spacing:0.06em;text-transform:uppercase;">Sous-dossiers</th>
                        <th style="padding:10px 16px;text-align:center;font-size:11px;font-weight:700;color:rgba(255,255,255,0.7);letter-spacing:0.06em;text-transform:uppercase;">Documents</th>
                        <th style="padding:10px 16px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($orphanFolders as $i => $folder)
                    <tr style="border-bottom:0.5px solid var(--pd-border);{{ $i % 2 === 1 ? 'background:var(--pd-surface2);' : '' }}">
                        <td style="padding:10px 16px;font-weight:500;color:var(--pd-navy);">
                            <div style="display:flex;align-items:center;gap:7px;">
                                <svg style="width:14px;height:14px;fill:none;stroke:var(--pd-muted);stroke-width:1.5;flex-shrink:0;" viewBox="0 0 24 24"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                                {{ $folder->name }}
                            </div>
                        </td>
                        <td style="padding:10px 16px;">
                            <code style="font-size:11px;background:rgba(30,58,95,0.06);padding:2px 6px;border-radius:4px;color:var(--pd-muted);">{{ $folder->path }}</code>
                        </td>
                        <td style="padding:10px 16px;text-align:center;color:var(--pd-muted);">{{ $folder->children_count }}</td>
                        <td style="padding:10px 16px;text-align:center;color:var(--pd-muted);">{{ $folder->documents_count }}</td>
                        <td style="padding:10px 16px;text-align:right;">
                            <a href="{{ route('ged.folders.show', $folder) }}"
                               style="display:inline-flex;align-items:center;gap:5px;
                                      font-size:12px;font-weight:500;color:var(--pd-navy);
                                      text-decoration:none;padding:5px 10px;border-radius:6px;
                                      border:0.5px solid var(--pd-border);background:var(--pd-surface);
                                      transition:background .15s;"
                               onmouseover="this.style.background='var(--pd-surface2)'"
                               onmouseout="this.style.background='var(--pd-surface)'">
                                <svg style="width:12px;height:12px;fill:none;stroke:currentColor;stroke-width:2;" viewBox="0 0 24 24"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                                Ouvrir
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

    {{-- ── Documents orphelins ──────────────────────────────────────── --}}
    <div style="background:var(--pd-surface);border:0.5px solid var(--pd-border);border-radius:12px;overflow:hidden;">

        <div style="padding:14px 20px;background:var(--pd-surface2);border-bottom:0.5px solid var(--pd-border);display:flex;align-items:center;gap:10px;">
            <div style="width:32px;height:32px;border-radius:8px;background:rgba(30,58,95,0.1);display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;">📄</div>
            <div style="flex:1;">
                <div style="font-size:14px;font-weight:700;color:var(--pd-navy);display:flex;align-items:center;gap:8px;">
                    Documents orphelins
                    <span style="display:inline-flex;align-items:center;justify-content:center;
                                 min-width:20px;height:20px;padding:0 6px;border-radius:10px;
                                 font-size:11px;font-weight:700;
                                 background:{{ $orphanDocs->isEmpty() ? 'rgba(5,150,105,0.12)' : 'rgba(220,38,38,0.12)' }};
                                 color:{{ $orphanDocs->isEmpty() ? '#059669' : '#DC2626' }};">
                        {{ $orphanDocs->count() }}
                    </span>
                </div>
                <div style="font-size:11px;color:var(--pd-muted);">
                    Documents dont le créateur n'a plus de compte actif.
                </div>
            </div>
        </div>

        @if($orphanDocs->isEmpty())
        <div style="padding:20px;display:flex;align-items:center;gap:10px;color:var(--pd-muted);font-size:13px;">
            <svg style="width:16px;height:16px;fill:none;stroke:#059669;stroke-width:2;" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
            <span style="color:#059669;">Aucun document orphelin.</span>
        </div>
        @else
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <thead>
                    <tr style="background:var(--pd-navy);">
                        <th style="padding:10px 16px;text-align:left;font-size:11px;font-weight:700;color:rgba(255,255,255,0.7);letter-spacing:0.06em;text-transform:uppercase;">Document</th>
                        <th style="padding:10px 16px;text-align:left;font-size:11px;font-weight:700;color:rgba(255,255,255,0.7);letter-spacing:0.06em;text-transform:uppercase;">Dossier</th>
                        <th style="padding:10px 16px;text-align:right;font-size:11px;font-weight:700;color:rgba(255,255,255,0.7);letter-spacing:0.06em;text-transform:uppercase;">Taille</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($orphanDocs as $i => $doc)
                    <tr style="border-bottom:0.5px solid var(--pd-border);{{ $i % 2 === 1 ? 'background:var(--pd-surface2);' : '' }}">
                        <td style="padding:10px 16px;font-weight:500;color:var(--pd-navy);">
                            <div style="display:flex;align-items:center;gap:7px;">
                                <svg style="width:14px;height:14px;fill:none;stroke:var(--pd-muted);stroke-width:1.5;flex-shrink:0;" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                {{ $doc->name }}
                            </div>
                        </td>
                        <td style="padding:10px 16px;">
                            @if($doc->folder)
                                <a href="{{ route('ged.folders.show', $doc->folder) }}"
                                   style="color:var(--pd-navy);text-decoration:none;font-size:12px;"
                                   onmouseover="this.style.textDecoration='underline'"
                                   onmouseout="this.style.textDecoration='none'">
                                    <code style="font-size:11px;background:rgba(30,58,95,0.06);padding:2px 6px;border-radius:4px;color:var(--pd-muted);">{{ $doc->folder->path }}</code>
                                </a>
                            @else
                                <span style="color:var(--pd-muted);font-size:12px;">—</span>
                            @endif
                        </td>
                        <td style="padding:10px 16px;text-align:right;color:var(--pd-muted);font-size:12px;">{{ $doc->humanSize() }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

</div>

@endsection
