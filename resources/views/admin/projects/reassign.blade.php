@extends('layouts.admin')

@section('admin-content')

<div style="max-width:780px;">

    <div style="margin-bottom:24px;">
        <div style="font-size:20px;font-weight:700;color:var(--pd-navy);">Réaffectation des projets</div>
    </div>

    @if(session('success'))
    <div style="padding:12px 16px;background:#F0FDF4;border:1px solid #86EFAC;border-radius:8px;margin-bottom:20px;font-size:13px;color:#065F46;">
        {{ session('success') }}
    </div>
    @endif

    @if($errors->any())
    <div style="padding:12px 16px;background:#FEF2F2;border:1px solid #FCA5A5;border-radius:8px;margin-bottom:20px;font-size:13px;color:#991B1B;">
        @foreach($errors->all() as $err)<div>{{ $err }}</div>@endforeach
    </div>
    @endif

    {{-- ════════════════════════════════════════════════════════════════════
         BLOC 1 — Éléments sans propriétaire dans un projet
         ════════════════════════════════════════════════════════════════════ --}}
    <div class="pd-card" style="margin-bottom:20px;padding:20px 24px;">

        {{-- Sélecteur de projet --}}
        <div style="margin-bottom:16px;">
            <label style="font-size:12px;font-weight:600;color:var(--pd-text);display:block;margin-bottom:6px;">
                Nom du projet
            </label>
            <form method="GET" action="{{ route('admin.projects.reassign.index') }}"
                  style="display:flex;gap:8px;align-items:center;">
                <select name="project_id" class="pd-input" style="font-size:12px;flex:1;max-width:400px;"
                        onchange="this.form.submit()">
                    <option value="">— Choisir un projet —</option>
                    @foreach($projects as $p)
                    <option value="{{ $p->id }}" {{ $selectedProjectId === $p->id ? 'selected' : '' }}>
                        {{ $p->name }}
                    </option>
                    @endforeach
                </select>
                @if($selectedProjectId)
                <a href="{{ route('admin.projects.reassign.index') }}"
                   style="font-size:12px;color:var(--pd-muted);text-decoration:none;">✕ Effacer</a>
                @endif
            </form>
        </div>

        {{-- Résultats --}}
        @if($selectedProject)

            @php $totalUnowned = $unownedTasks->count() + $unownedComms->count(); @endphp

            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--pd-muted);margin-bottom:10px;">
                Éléments sans propriétaire — {{ $selectedProject->name }}
            </div>

            @if($totalUnowned === 0)
            <div style="padding:12px;background:#F0FDF4;border-radius:8px;font-size:13px;color:#065F46;">
                Tous les éléments de ce projet ont un propriétaire.
            </div>
            @else

            <div style="padding:10px 12px;background:#FEF9C3;border:0.5px solid #FCD34D;border-radius:8px;margin-bottom:14px;font-size:12px;color:#78350F;">
                {{ $totalUnowned }} élément{{ $totalUnowned > 1 ? 's' : '' }} sans affectation :
                @if($unownedTasks->count()) {{ $unownedTasks->count() }} tâche{{ $unownedTasks->count() > 1 ? 's' : '' }} @endif
                @if($unownedTasks->count() && $unownedComms->count()) · @endif
                @if($unownedComms->count()) {{ $unownedComms->count() }} action{{ $unownedComms->count() > 1 ? 's' : '' }} comm @endif
            </div>

            @if($unownedTasks->isNotEmpty())
            <div style="margin-bottom:10px;">
                <div style="font-size:11px;font-weight:600;color:var(--pd-muted);margin-bottom:4px;">Tâches</div>
                @foreach($unownedTasks as $t)
                <div style="font-size:12px;padding:3px 0 3px 10px;border-left:2px solid #FCD34D;margin-bottom:3px;color:var(--pd-text);">
                    {{ $t->title }}
                </div>
                @endforeach
            </div>
            @endif

            @if($unownedComms->isNotEmpty())
            <div style="margin-bottom:10px;">
                <div style="font-size:11px;font-weight:600;color:var(--pd-muted);margin-bottom:4px;">Actions de communication</div>
                @foreach($unownedComms as $c)
                <div style="font-size:12px;padding:3px 0 3px 10px;border-left:2px solid #FCD34D;margin-bottom:3px;color:var(--pd-text);">
                    {{ $c->title }}
                </div>
                @endforeach
            </div>
            @endif

            <form method="POST" action="{{ route('admin.projects.reassign.unowned') }}"
                  style="margin-top:14px;padding-top:14px;border-top:1px solid var(--pd-border);">
                @csrf
                <input type="hidden" name="project_id" value="{{ $selectedProjectId }}">

                <div style="font-size:12px;color:var(--pd-muted);margin-bottom:8px;">
                    Ces éléments n'ont pas de propriétaire — choisissez à qui les attribuer :
                </div>

                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                    <select name="to_user_id" class="pd-input" required style="font-size:12px;min-width:220px;flex:1;max-width:320px;">
                        <option value="">— Choisir —</option>
                        @foreach($activeUsers as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="pd-btn pd-btn-primary pd-btn-sm"
                            onclick="return confirm('Attribuer tous les éléments sans propriétaire ?')">
                        Attribuer
                    </button>
                </div>

            </form>

            @endif {{-- totalUnowned --}}

        @elseif(!$selectedProjectId)
        <div style="font-size:12px;color:var(--pd-muted);padding:10px 0;">
            Sélectionnez un projet ci-dessus pour voir les tâches et actions comm sans propriétaire.
        </div>
        @endif

    </div>

    {{-- ════════════════════════════════════════════════════════════════════
         BLOC 2 — Réaffectation d'un compte vers un autre
         ════════════════════════════════════════════════════════════════════ --}}
    <div class="pd-card" style="padding:20px 24px;">

        <div style="font-size:14px;font-weight:700;color:var(--pd-navy);margin-bottom:4px;">Effectuer une réaffectation</div>
        <div style="font-size:12px;color:var(--pd-muted);margin-bottom:16px;">
            Transfère tout le travail d'un compte vers un autre (départ, décès, changement de compte…).
        </div>

        <form method="POST" action="{{ route('admin.projects.reassign.store') }}"
              style="display:flex;flex-direction:column;gap:14px;">
            @csrf

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">

                {{-- Compte de l'ancien proprio --}}
                <div>
                    <label style="font-size:12px;font-weight:600;color:var(--pd-text);display:block;margin-bottom:6px;">
                        Compte de l'ancien proprio
                    </label>
                    <select id="from-select" class="pd-input" style="font-size:12px;width:100%;margin-bottom:6px;"
                            onchange="var inp=document.getElementById('from-id');inp.value=this.value||'';">
                        <option value="">— Choisir —</option>
                        @foreach($activeUsers as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                    <div style="display:flex;align-items:center;gap:6px;">
                        <input type="number" id="from-id" name="from_user_id" class="pd-input"
                               placeholder="ou forcer l'ID" min="1" style="font-size:12px;width:110px;"
                               oninput="if(!this.value)document.getElementById('from-select').value='';"
                               required>
                        <span id="from-label" style="font-size:11px;color:var(--pd-muted);font-style:italic;"></span>
                    </div>
                </div>

                {{-- Compte du nouveau proprio --}}
                <div>
                    <label style="font-size:12px;font-weight:600;color:var(--pd-text);display:block;margin-bottom:6px;">
                        Compte du nouveau proprio
                    </label>
                    <select name="to_user_id" class="pd-input" required style="font-size:12px;width:100%;">
                        <option value="">— Choisir —</option>
                        @foreach($activeUsers as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>

            </div>

            {{-- Cases à cocher --}}
            <div style="display:flex;gap:20px;flex-wrap:wrap;padding:10px 14px;background:var(--pd-bg2);border-radius:8px;">
                <label style="font-size:12px;display:flex;align-items:center;gap:7px;cursor:pointer;">
                    <input type="checkbox" name="transfer_membership" value="1" checked style="accent-color:var(--pd-navy);">
                    Rôle de membre
                </label>
                <label style="font-size:12px;display:flex;align-items:center;gap:7px;cursor:pointer;">
                    <input type="checkbox" name="transfer_tasks" value="1" checked style="accent-color:var(--pd-navy);">
                    Tâches assignées
                </label>
                <label style="font-size:12px;display:flex;align-items:center;gap:7px;cursor:pointer;">
                    <input type="checkbox" name="transfer_comm" value="1" checked style="accent-color:var(--pd-navy);">
                    Responsabilités comm
                </label>
            </div>

            <div style="display:flex;justify-content:flex-end;">
                <button type="submit" class="pd-btn pd-btn-primary"
                        onclick="return confirm('Confirmer la réaffectation ?')">
                    Réaffecter
                </button>
            </div>

        </form>
    </div>

</div>

@push('scripts')
<script>
function fillFrom(id, name) {
    var sel = document.getElementById('from-select');
    var inp = document.getElementById('from-id');
    var lbl = document.getElementById('from-label');
    inp.value = id;
    var opt = Array.from(sel.options).find(function(o) { return parseInt(o.value) === id; });
    sel.value = opt ? opt.value : '';
    lbl.textContent = opt ? '' : name + ' (inactif)';
    document.querySelector('[name="to_user_id"]').focus();
}
</script>
@endpush

@endsection
