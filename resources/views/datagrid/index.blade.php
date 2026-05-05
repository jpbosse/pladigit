@extends('layouts.app')
@section('title', 'DataGrid')

@section('content')
<div style="padding:32px 40px;">

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
        <div>
            <h1 style="font-size:22px;font-weight:700;color:var(--pd-text);margin:0 0 4px;">Grilles DataGrid</h1>
            <p style="font-size:13px;color:var(--pd-muted);margin:0;">
                {{ $tables->count() }} grille{{ $tables->count() !== 1 ? 's' : '' }} disponible{{ $tables->count() !== 1 ? 's' : '' }}
            </p>
        </div>
        @if(session('super_admin_logged_in'))
        <a href="{{ route('datagrid.import') }}"
           style="padding:9px 18px;background:var(--pd-navy);color:#fff;border-radius:9px;
                  font-size:13px;font-weight:600;text-decoration:none;">
            + Importer une grille
        </a>
        @endif
    </div>

    @if($tables->isEmpty())
    <div style="text-align:center;padding:64px 24px;color:var(--pd-muted);">
        <div style="font-size:48px;margin-bottom:16px;">📇</div>
        <p style="font-size:15px;font-weight:600;color:var(--pd-text);margin:0 0 8px;">Aucune grille configurée</p>
        <p style="font-size:13px;margin:0;">Demandez à votre administrateur d'importer un fichier Excel.</p>
    </div>
    @else
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;">
        @foreach($tables as $table)
        <div style="background:var(--pd-bg);border:1px solid var(--pd-border);border-radius:12px;padding:20px;">
            <div style="font-size:15px;font-weight:600;color:var(--pd-text);margin-bottom:4px;">
                {{ $table->label }}
            </div>
            @if($table->description)
            <div style="font-size:12px;color:var(--pd-muted);margin-bottom:12px;">{{ $table->description }}</div>
            @endif
            <div style="font-size:11px;color:var(--pd-muted);">
                {{ $table->columns_count }} colonne{{ $table->columns_count !== 1 ? 's' : '' }}
            </div>
        </div>
        @endforeach
    </div>
    @endif

</div>
@endsection
