@extends('layouts.admin')
@section('title', 'Utilisateurs')

@section('admin-content')

{{-- En-tête --}}
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
    <div>
        <h1 style="font-family:'Sora',sans-serif;font-size:20px;font-weight:700;color:var(--pd-text);margin:0 0 2px;">
            Utilisateurs
        </h1>
        <p style="font-size:13px;color:var(--pd-muted);margin:0;">
            {{ $users->total() }} compte{{ $users->total() > 1 ? 's' : '' }} dans l'organisation
        </p>
    </div>
    <a href="{{ route('admin.users.create') }}"
       style="display:inline-flex;align-items:center;gap:7px;
              padding:9px 18px;border-radius:10px;
              background:linear-gradient(135deg,var(--pd-navy-dark),var(--pd-navy-light));
              color:#fff;font-size:13px;font-weight:600;text-decoration:none;
              transition:opacity 0.2s;"
       onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">
        <svg style="width:14px;height:14px;fill:none;stroke:currentColor;stroke-width:2.2;stroke-linecap:round;" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
        Nouvel utilisateur
    </a>
</div>

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

{{-- Tableau --}}
<div style="background:var(--pd-surface);border:1.5px solid var(--pd-border);border-radius:14px;overflow:hidden;box-shadow:var(--pd-shadow);">
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
        <thead>
            <tr style="background:var(--pd-navy-dark);">
                <th style="padding:12px 16px;text-align:left;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.8px;color:rgba(255,255,255,0.7);">Nom</th>
                <th style="padding:12px 16px;text-align:left;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.8px;color:rgba(255,255,255,0.7);">Email</th>
                <th style="padding:12px 16px;text-align:left;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.8px;color:rgba(255,255,255,0.7);">Rôle</th>
                <th style="padding:12px 16px;text-align:left;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.8px;color:rgba(255,255,255,0.7);">Service</th>
                <th style="padding:12px 16px;text-align:left;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.8px;color:rgba(255,255,255,0.7);">Statut</th>
                <th style="padding:12px 16px;text-align:left;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.8px;color:rgba(255,255,255,0.7);">Auth</th>
                <th style="padding:12px 16px;text-align:right;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.8px;color:rgba(255,255,255,0.7);">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($users as $u)
            @php
                $statusColor = match($u->status) {
                    'active'   => ['bg'=>'rgba(46,204,113,0.1)','text'=>'#1a8a4a','dot'=>'#2ecc71'],
                    'locked'   => ['bg'=>'rgba(231,76,60,0.08)','text'=>'#c0392b','dot'=>'#e74c3c'],
                    default    => ['bg'=>'rgba(149,165,166,0.1)','text'=>'#7f8c8d','dot'=>'#95a5a6'],
                };
                $statusLabel = match($u->status) {
                    'active'   => 'Actif',
                    'inactive' => 'Inactif',
                    'locked'   => 'Verrouillé',
                    default    => $u->status,
                };
            @endphp
            <tr style="border-top:1px solid var(--pd-border);transition:background 0.12s;"
                onmouseover="this.style.background='var(--pd-bg)'" onmouseout="this.style.background=''">

                {{-- Nom --}}
                <td style="padding:13px 16px;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div style="width:32px;height:32px;border-radius:8px;flex-shrink:0;
                                    background:linear-gradient(135deg,var(--pd-navy-dark),var(--pd-accent));
                                    display:flex;align-items:center;justify-content:center;
                                    font-family:'Sora',sans-serif;font-size:12px;font-weight:700;color:#fff;">
                            {{ strtoupper(substr($u->name,0,2)) }}
                        </div>
                        <span style="font-weight:600;color:var(--pd-text);">{{ $u->name }}</span>
                    </div>
                </td>

                {{-- Email --}}
                <td style="padding:13px 16px;color:var(--pd-muted);">{{ $u->email }}</td>

                {{-- Rôle --}}
                <td style="padding:13px 16px;">
                    <span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:11.5px;font-weight:600;
                                 background:rgba(59,154,225,0.1);color:var(--pd-accent);">
                        {{ App\Enums\UserRole::tryFrom($u->role)?->label() ?? $u->role }}
                    </span>
                </td>

                {{-- Service --}}
                <td style="padding:13px 16px;color:var(--pd-muted);">
                    {{ $u->department ?? '—' }}
                </td>

                {{-- Statut --}}
                <td style="padding:13px 16px;">
                    <span style="display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;font-size:11.5px;font-weight:600;
                                 background:{{ $statusColor['bg'] }};color:{{ $statusColor['text'] }};">
                        <span style="width:5px;height:5px;border-radius:50%;background:{{ $statusColor['dot'] }};flex-shrink:0;"></span>
                        {{ $statusLabel }}
                    </span>
                </td>

                {{-- Auth --}}
                <td style="padding:13px 16px;">
                    <span style="font-size:11.5px;font-weight:500;
                                 color:{{ $u->ldap_dn ? 'var(--pd-accent)' : 'var(--pd-muted)' }};">
                        {{ $u->ldap_dn ? 'LDAP' : 'Local' }}
                    </span>
                </td>

                {{-- Actions --}}
                <td style="padding:13px 16px;text-align:right;">
                    <div style="display:flex;align-items:center;justify-content:flex-end;gap:6px;">
                        <a href="{{ route('admin.users.edit', $u) }}"
                           style="display:inline-flex;align-items:center;gap:5px;padding:5px 12px;
                                  border-radius:7px;font-size:12px;font-weight:500;
                                  background:var(--pd-bg);border:1.5px solid var(--pd-border);
                                  color:var(--pd-text);text-decoration:none;transition:border-color 0.15s;"
                           onmouseover="this.style.borderColor='var(--pd-accent)'"
                           onmouseout="this.style.borderColor='var(--pd-border)'">
                            <svg style="width:12px;height:12px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            Modifier
                        </a>
                        <form method="POST" action="{{ route('admin.users.reset-password', $u) }}" style="margin:0;">
                            @csrf
                            <button type="submit"
                                    style="display:inline-flex;align-items:center;gap:5px;padding:5px 12px;
                                           border-radius:7px;font-size:12px;font-weight:500;cursor:pointer;
                                           background:var(--pd-bg);border:1.5px solid var(--pd-border);
                                           color:var(--pd-gold);transition:border-color 0.15s;font-family:inherit;"
                                    onmouseover="this.style.borderColor='var(--pd-gold)'"
                                    onmouseout="this.style.borderColor='var(--pd-border)'">
                                <svg style="width:12px;height:12px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                MDP
                            </button>
                        </form>
                        @if($u->id !== auth()->id())
                        <form method="POST" action="{{ route('admin.users.destroy', $u) }}"
                              onsubmit="return confirm('Désactiver {{ addslashes($u->name) }} ?')" style="margin:0;">
                            @csrf @method('DELETE')
                            <button type="submit"
                                    style="display:inline-flex;align-items:center;gap:5px;padding:5px 12px;
                                           border-radius:7px;font-size:12px;font-weight:500;cursor:pointer;
                                           background:var(--pd-bg);border:1.5px solid var(--pd-border);
                                           color:var(--pd-danger);transition:border-color 0.15s;font-family:inherit;"
                                    onmouseover="this.style.borderColor='var(--pd-danger)'"
                                    onmouseout="this.style.borderColor='var(--pd-border)'">
                                <svg style="width:12px;height:12px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                                Désactiver
                            </button>
                        </form>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" style="padding:48px 16px;text-align:center;color:var(--pd-muted);">
                    <svg style="width:32px;height:32px;fill:none;stroke:currentColor;stroke-width:1.5;margin:0 auto 12px;display:block;opacity:0.3;" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                    Aucun utilisateur trouvé.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Pagination --}}
@if($users->hasPages())
<div style="margin-top:20px;">{{ $users->links() }}</div>
@endif

@endsection
