@extends('layouts.super-admin')
@section('title', $organization->name . ' — Super Admin')

@section('content')
<div style="max-width:960px;margin:0 auto;padding:24px 24px 48px;">

    {{-- Breadcrumb --}}
    <div style="font-size:13px;color:#6b7280;margin-bottom:20px;">
        <a href="{{ route('super-admin.organizations.index') }}" style="color:#6b7280;text-decoration:none;">Organisations</a>
        <span style="margin:0 8px;">›</span>
        <span style="color:#1f2937;">{{ $organization->name }}</span>
    </div>

    {{-- Flash --}}
    @if(session('success'))
        <div style="background:#f0fdf4;border:1px solid #86efac;color:#15803d;border-radius:8px;padding:10px 16px;margin-bottom:20px;font-size:13px;">
            ✓ {{ session('success') }}
        </div>
    @endif

    {{-- Header --}}
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:24px;">
        <div>
            <h1 style="font-size:22px;font-weight:700;color:#1f2937;margin:0 0 4px;">{{ $organization->name }}</h1>
            <div style="display:flex;align-items:center;gap:10px;">
                <span style="font-size:12px;font-family:monospace;color:#6b7280;">{{ $organization->slug }}.pladigit.fr</span>
                @php
                    $statusMap = ['active'=>['#dcfce7','#15803d','Actif'],'suspended'=>['#fee2e2','#b91c1c','Suspendu'],'pending'=>['#fef9c3','#92400e','En attente'],'archived'=>['#f3f4f6','#4b5563','Archivé']];
                    [$sbg,$sfg,$slabel] = $statusMap[$organization->status] ?? ['#f3f4f6','#4b5563',ucfirst($organization->status)];
                @endphp
                <span style="font-size:11px;font-weight:600;padding:2px 8px;border-radius:999px;background:{{ $sbg }};color:{{ $sfg }};">{{ $slabel }}</span>
                <span style="font-size:11px;font-weight:500;padding:2px 8px;border-radius:999px;background:#f3f4f6;color:#374151;">
                    {{ ['communautaire'=>'Communautaire','assistance'=>'Assistance','enterprise'=>'Enterprise'][$organization->plan] ?? ucfirst($organization->plan) }}
                </span>
            </div>
        </div>
        <div style="display:flex;gap:8px;">
            <a href="{{ route('super-admin.organizations.edit', $organization) }}"
               style="padding:7px 14px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;color:#374151;text-decoration:none;background:#fff;">
                ✏️ Modifier
            </a>
            @if($organization->status === 'active')
            <form method="POST" action="{{ route('super-admin.organizations.suspend', $organization) }}"
                  onsubmit="return confirm('Suspendre cette organisation ?')">
                @csrf
                <button style="padding:7px 14px;border:none;border-radius:8px;font-size:13px;background:#dc2626;color:#fff;cursor:pointer;">⏸ Suspendre</button>
            </form>
            @else
            <form method="POST" action="{{ route('super-admin.organizations.activate', $organization) }}">
                @csrf
                <button style="padding:7px 14px;border:none;border-radius:8px;font-size:13px;background:#16a34a;color:#fff;cursor:pointer;">▶ Activer</button>
            </form>
            @endif
        </div>
    </div>

    {{-- Métriques --}}
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:28px;">
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:14px;">
            <div style="font-size:11px;text-transform:uppercase;font-weight:600;color:#9ca3af;margin-bottom:6px;letter-spacing:.05em;">Utilisateurs</div>
            <div style="font-size:20px;font-weight:700;color:#1f2937;">{{ $userCount }}</div>
            <div style="font-size:11px;color:#9ca3af;">/ {{ $organization->max_users }} max</div>
        </div>
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:14px;">
            <div style="font-size:11px;text-transform:uppercase;font-weight:600;color:#9ca3af;margin-bottom:6px;letter-spacing:.05em;">Stockage</div>
            <div style="font-size:20px;font-weight:700;color:#1f2937;">{{ $organization->storage_quota_mb >= 1024 ? round($organization->storage_quota_mb/1024,1).' Go' : $organization->storage_quota_mb.' Mo' }}</div>
            <div style="font-size:11px;color:#9ca3af;">quota alloué</div>
        </div>
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:14px;">
            <div style="font-size:11px;text-transform:uppercase;font-weight:600;color:#9ca3af;margin-bottom:6px;letter-spacing:.05em;">Base MySQL</div>
            <div style="font-size:12px;font-family:monospace;color:#374151;word-break:break-all;">{{ $organization->db_name }}</div>
        </div>
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:14px;">
            <div style="font-size:11px;text-transform:uppercase;font-weight:600;color:#9ca3af;margin-bottom:6px;letter-spacing:.05em;">Modules actifs</div>
            <div style="font-size:20px;font-weight:700;color:#1f2937;">{{ count($organization->enabled_modules ?? []) }}</div>
            <div style="font-size:11px;color:#9ca3af;">/ {{ count(\App\Enums\ModuleKey::available()) }} disponibles</div>
        </div>
    </div>

    {{-- Onglets --}}
    <div style="display:flex;gap:4px;border-bottom:2px solid #e5e7eb;margin-bottom:24px;">
        @foreach([['tab-admin','👤 Administrateur'],['tab-modules','🧩 Modules'],['tab-smtp','📧 SMTP'],['tab-ldap','🔗 LDAP']] as [$tid,$tlabel])
        <button onclick="saShowTab('{{ $tid }}')" id="btn-{{ $tid }}" class="sa-tab-btn"
                style="padding:8px 18px;font-size:13px;font-weight:500;border:none;background:none;cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-2px;color:#6b7280;">
            {{ $tlabel }}
        </button>
        @endforeach
    </div>

    {{-- Onglet Administrateur --}}
    <div id="tab-admin" class="sa-tab-panel">
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:24px;">
            <h2 style="font-size:15px;font-weight:600;color:#1f2937;margin:0 0 4px;">Créer un administrateur</h2>
            <p style="font-size:13px;color:#6b7280;margin:0 0 20px;">Crée ou remplace le compte admin de cette organisation.</p>
            <form method="POST" action="{{ route('super-admin.organizations.create-admin', $organization) }}">
                @csrf
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                    <div>
                        <label style="display:block;font-size:13px;font-weight:500;color:#374151;margin-bottom:4px;">Nom</label>
                        <input type="text" name="name" value="{{ old('name') }}" required
                               style="width:100%;border:1px solid #d1d5db;border-radius:8px;padding:8px 12px;font-size:13px;box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block;font-size:13px;font-weight:500;color:#374151;margin-bottom:4px;">Email</label>
                        <input type="email" name="email" value="{{ old('email') }}" required
                               style="width:100%;border:1px solid #d1d5db;border-radius:8px;padding:8px 12px;font-size:13px;box-sizing:border-box;">
                    </div>
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-size:13px;font-weight:500;color:#374151;margin-bottom:4px;">Mot de passe</label>
                    <input type="password" name="password" required
                           style="width:100%;border:1px solid #d1d5db;border-radius:8px;padding:8px 12px;font-size:13px;box-sizing:border-box;">
                </div>
                @if($errors->any())
                    <div style="background:#fef2f2;border:1px solid #fca5a5;color:#b91c1c;border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:13px;">
                        {{ $errors->first() }}
                    </div>
                @endif
                <button type="submit" style="background:#1E3A5F;color:#fff;padding:8px 20px;border:none;border-radius:8px;font-size:13px;font-weight:500;cursor:pointer;">
                    Créer l'administrateur
                </button>
            </form>
        </div>
    </div>

    {{-- Onglet Modules --}}
    <div id="tab-modules" class="sa-tab-panel" style="display:none;">
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:24px;">
            <h2 style="font-size:15px;font-weight:600;color:#1f2937;margin:0 0 4px;">Modules activés</h2>
            <p style="font-size:13px;color:#6b7280;margin:0 0 20px;">Seuls les modules disponibles dans la version actuelle sont affichés.</p>
            <form method="POST" action="{{ route('super-admin.organizations.update-modules', $organization) }}">
                @csrf
                <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin-bottom:20px;">
                    @foreach(\App\Enums\ModuleKey::available() as $module)
                    @php
                        $mods = is_array($organization->enabled_modules) ? $organization->enabled_modules : [];
                        $active = in_array($module->value, $mods, true);
                    @endphp
                    <label style="display:flex;align-items:flex-start;gap:12px;padding:12px;border-radius:8px;cursor:pointer;border:1.5px solid {{ $active ? '#93c5fd' : '#e5e7eb' }};background:{{ $active ? '#eff6ff' : '#f9fafb' }};">
                        <input type="checkbox" name="modules[]" value="{{ $module->value }}"
                               style="margin-top:2px;width:15px;height:15px;flex-shrink:0;accent-color:#1E3A5F;"
                               {{ $active ? 'checked' : '' }}>
                        <div>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <span style="font-size:13px;font-weight:500;color:#1f2937;">{{ $module->label() }}</span>
                                <span style="font-size:11px;padding:1px 6px;border-radius:999px;font-family:monospace;background:#dcfce7;color:#15803d;">Phase {{ $module->phase() }}</span>
                            </div>
                            <p style="font-size:12px;color:#6b7280;margin:3px 0 0;line-height:1.4;">{{ $module->description() }}</p>
                        </div>
                    </label>
                    @endforeach
                </div>
                <button type="submit" style="background:#1E3A5F;color:#fff;padding:8px 20px;border:none;border-radius:8px;font-size:13px;font-weight:500;cursor:pointer;">
                    Enregistrer les modules
                </button>
            </form>
        </div>
    </div>

    {{-- Onglet SMTP --}}
    <div id="tab-smtp" class="sa-tab-panel" style="display:none;">
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:24px;">
            <h2 style="font-size:15px;font-weight:600;color:#1f2937;margin:0 0 4px;">Configuration SMTP</h2>
            <p style="font-size:13px;color:#6b7280;margin:0 0 20px;">Email sortant pour les notifications et invitations de cette organisation.</p>
            <form method="POST" action="{{ route('super-admin.organizations.update-smtp', $organization) }}">
                @csrf
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                    <div>
                        <label style="display:block;font-size:13px;font-weight:500;color:#374151;margin-bottom:4px;">Serveur SMTP</label>
                        <input type="text" name="smtp_host" value="{{ old('smtp_host', $organization->smtp_host) }}"
                               style="width:100%;border:1px solid #d1d5db;border-radius:8px;padding:8px 12px;font-size:13px;box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block;font-size:13px;font-weight:500;color:#374151;margin-bottom:4px;">Port</label>
                        <input type="number" name="smtp_port" value="{{ old('smtp_port', $organization->smtp_port ?? 587) }}"
                               style="width:100%;border:1px solid #d1d5db;border-radius:8px;padding:8px 12px;font-size:13px;box-sizing:border-box;">
                    </div>
                    <div style="grid-column:span 2;">
                        <label style="display:block;font-size:13px;font-weight:500;color:#374151;margin-bottom:4px;">Chiffrement</label>
                        <select name="smtp_encryption" style="width:100%;border:1px solid #d1d5db;border-radius:8px;padding:8px 12px;font-size:13px;">
                            <option value="tls"   {{ ($organization->smtp_encryption ?? 'tls') === 'tls'   ? 'selected' : '' }}>STARTTLS — port 587 (recommandé)</option>
                            <option value="smtps" {{ ($organization->smtp_encryption ?? '') === 'smtps' ? 'selected' : '' }}>SSL/TLS — port 465</option>
                            <option value="none"  {{ ($organization->smtp_encryption ?? '') === 'none'  ? 'selected' : '' }}>Aucun chiffrement (déconseillé)</option>
                        </select>
                    </div>
                    <div>
                        <label style="display:block;font-size:13px;font-weight:500;color:#374151;margin-bottom:4px;">Utilisateur</label>
                        <input type="text" name="smtp_user" value="{{ old('smtp_user', $organization->smtp_user) }}"
                               style="width:100%;border:1px solid #d1d5db;border-radius:8px;padding:8px 12px;font-size:13px;box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block;font-size:13px;font-weight:500;color:#374151;margin-bottom:4px;">Mot de passe</label>
                        <input type="password" name="smtp_password" placeholder="Laisser vide pour ne pas modifier"
                               style="width:100%;border:1px solid #d1d5db;border-radius:8px;padding:8px 12px;font-size:13px;box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block;font-size:13px;font-weight:500;color:#374151;margin-bottom:4px;">Adresse expéditeur</label>
                        <input type="email" name="smtp_from_address" value="{{ old('smtp_from_address', $organization->smtp_from_address) }}"
                               style="width:100%;border:1px solid #d1d5db;border-radius:8px;padding:8px 12px;font-size:13px;box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block;font-size:13px;font-weight:500;color:#374151;margin-bottom:4px;">Nom expéditeur</label>
                        <input type="text" name="smtp_from_name" value="{{ old('smtp_from_name', $organization->smtp_from_name) }}"
                               style="width:100%;border:1px solid #d1d5db;border-radius:8px;padding:8px 12px;font-size:13px;box-sizing:border-box;">
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:12px;margin-top:8px;">
                    <button type="submit" style="background:#1E3A5F;color:#fff;padding:8px 20px;border:none;border-radius:8px;font-size:13px;font-weight:500;cursor:pointer;">
                        Sauvegarder SMTP
                    </button>
                    @if($organization->smtp_host)
                    <button type="button" id="btn-test-smtp-sa"
                            style="padding:8px 16px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;background:#fff;cursor:pointer;color:#374151;">
                        Tester la connexion
                    </button>
                    <span id="smtp-test-result-sa" style="font-size:13px;display:none;"></span>
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- Onglet LDAP --}}
    <div id="tab-ldap" class="sa-tab-panel" style="display:none;">
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:24px;">
            <h2 style="font-size:15px;font-weight:600;color:#1f2937;margin:0 0 4px;">Configuration LDAP / Active Directory</h2>
            <p style="font-size:13px;color:#6b7280;margin:0 0 20px;">Authentification et synchronisation des utilisateurs depuis un annuaire LDAP/AD.</p>
            <form method="POST" action="{{ route('super-admin.organizations.update-ldap', $organization) }}">
                @csrf
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                    <div>
                        <label style="display:block;font-size:13px;font-weight:500;color:#374151;margin-bottom:4px;">Serveur LDAP</label>
                        <input type="text" name="ldap_host" value="{{ old('ldap_host', $ldapSettings?->ldap_host) }}"
                               placeholder="ldap.mondomaine.fr"
                               style="width:100%;border:1px solid #d1d5db;border-radius:8px;padding:8px 12px;font-size:13px;box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block;font-size:13px;font-weight:500;color:#374151;margin-bottom:4px;">Port</label>
                        <input type="number" name="ldap_port" value="{{ old('ldap_port', $ldapSettings?->ldap_port ?? 636) }}"
                               style="width:100%;border:1px solid #d1d5db;border-radius:8px;padding:8px 12px;font-size:13px;box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block;font-size:13px;font-weight:500;color:#374151;margin-bottom:4px;">Base DN</label>
                        <input type="text" name="ldap_base_dn" value="{{ old('ldap_base_dn', $ldapSettings?->ldap_base_dn) }}"
                               placeholder="dc=mondomaine,dc=fr"
                               style="width:100%;border:1px solid #d1d5db;border-radius:8px;padding:8px 12px;font-size:13px;box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block;font-size:13px;font-weight:500;color:#374151;margin-bottom:4px;">Bind DN</label>
                        <input type="text" name="ldap_bind_dn" value="{{ old('ldap_bind_dn', $ldapSettings?->ldap_bind_dn) }}"
                               placeholder="cn=admin,dc=mondomaine,dc=fr"
                               style="width:100%;border:1px solid #d1d5db;border-radius:8px;padding:8px 12px;font-size:13px;box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block;font-size:13px;font-weight:500;color:#374151;margin-bottom:4px;">
                            Mot de passe <span style="font-weight:400;color:#9ca3af;">(vide = inchangé)</span>
                        </label>
                        <input type="password" name="ldap_bind_password" placeholder="Laisser vide pour ne pas modifier"
                               style="width:100%;border:1px solid #d1d5db;border-radius:8px;padding:8px 12px;font-size:13px;box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block;font-size:13px;font-weight:500;color:#374151;margin-bottom:4px;">Intervalle synchro (heures)</label>
                        <input type="number" name="ldap_sync_interval_hours"
                               value="{{ old('ldap_sync_interval_hours', $ldapSettings?->ldap_sync_interval_hours ?? 24) }}"
                               style="width:100%;border:1px solid #d1d5db;border-radius:8px;padding:8px 12px;font-size:13px;box-sizing:border-box;">
                    </div>
                </div>
                <div style="display:flex;gap:24px;margin-bottom:20px;">
                    <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:#374151;cursor:pointer;">
                        <input type="hidden" name="ldap_use_ssl" value="0">
                        <input type="checkbox" name="ldap_use_ssl" value="1"
                               style="width:15px;height:15px;accent-color:#1E3A5F;"
                               {{ ($ldapSettings?->ldap_use_ssl ?? true) ? 'checked' : '' }}>
                        Utiliser SSL (LDAPS port 636)
                    </label>
                    <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:#374151;cursor:pointer;">
                        <input type="hidden" name="ldap_use_tls" value="0">
                        <input type="checkbox" name="ldap_use_tls" value="1"
                               style="width:15px;height:15px;accent-color:#1E3A5F;"
                               {{ ($ldapSettings?->ldap_use_tls ?? false) ? 'checked' : '' }}>
                        Utiliser TLS (STARTTLS port 389)
                    </label>
                </div>
                <div style="display:flex;align-items:center;gap:12px;">
                    <button type="submit" style="background:#1E3A5F;color:#fff;padding:8px 20px;border:none;border-radius:8px;font-size:13px;font-weight:500;cursor:pointer;">
                        Sauvegarder LDAP
                    </button>
                    @if($ldapSettings?->ldap_host)
                    <button type="button" id="btn-test-ldap-sa"
                            style="padding:8px 16px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;background:#fff;cursor:pointer;color:#374151;">
                        Tester la connexion
                    </button>
                    <span id="ldap-test-result-sa" style="font-size:13px;display:none;"></span>
                    @endif
                </div>
            </form>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
function saShowTab(id) {
    document.querySelectorAll('.sa-tab-panel').forEach(p => p.style.display = 'none');
    document.querySelectorAll('.sa-tab-btn').forEach(b => {
        b.style.color = '#6b7280';
        b.style.borderBottomColor = 'transparent';
        b.style.fontWeight = '500';
    });
    document.getElementById(id).style.display = 'block';
    const btn = document.getElementById('btn-' + id);
    btn.style.color = '#1E3A5F';
    btn.style.borderBottomColor = '#1E3A5F';
    btn.style.fontWeight = '600';
    const url = new URL(window.location);
    url.searchParams.set('tab', id);
    history.replaceState(null, '', url);
}

const urlTab = new URLSearchParams(window.location.search).get('tab');
saShowTab(urlTab && document.getElementById(urlTab) ? urlTab : 'tab-admin');

document.getElementById('btn-test-smtp-sa')?.addEventListener('click', async function () {
    const btn = this, result = document.getElementById('smtp-test-result-sa');
    btn.disabled = true; btn.textContent = 'Test en cours…';
    result.style.display = 'inline'; result.textContent = '';
    try {
        const res = await fetch('{{ route('super-admin.organizations.test-smtp', $organization) }}', {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json'},
        });
        const data = await res.json();
        result.textContent = data.message;
        result.style.color = data.ok ? '#15803d' : '#b91c1c';
    } catch (e) { result.textContent = 'Erreur réseau.'; result.style.color = '#b91c1c'; }
    finally { btn.disabled = false; btn.textContent = 'Tester la connexion'; }
});

document.getElementById('btn-test-ldap-sa')?.addEventListener('click', async function () {
    const btn = this, result = document.getElementById('ldap-test-result-sa');
    btn.disabled = true; btn.textContent = 'Test en cours…';
    result.style.display = 'inline'; result.textContent = '';
    try {
        const res = await fetch('{{ route('super-admin.organizations.test-ldap', $organization) }}', {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json'},
        });
        const data = await res.json();
        result.textContent = data.message;
        result.style.color = data.ok ? '#15803d' : '#b91c1c';
    } catch (e) { result.textContent = 'Erreur réseau.'; result.style.color = '#b91c1c'; }
    finally { btn.disabled = false; btn.textContent = 'Tester la connexion'; }
});
</script>
@endpush
