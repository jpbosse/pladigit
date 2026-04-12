{{-- resources/views/projects/import.blade.php --}}
@extends('layouts.app')
@section('title', 'Importer un projet')

@section('content')
<div style="max-width:560px;margin:40px auto;padding:0 16px;">

    <div style="margin-bottom:24px;">
        <a href="{{ route('projects.index') }}"
           style="font-size:12px;color:var(--pd-muted);text-decoration:none;display:inline-flex;align-items:center;gap:5px;">
            ← Retour aux projets
        </a>
    </div>

    <div style="background:var(--pd-surface);border:0.5px solid var(--pd-border);border-radius:14px;overflow:hidden;">
        <div style="background:#1E3A5F;padding:20px 24px;">
            <div style="font-size:17px;font-weight:700;color:#fff;">Importer un projet</div>
            <div style="font-size:12px;color:rgba(255,255,255,.7);margin-top:3px;">
                Depuis un fichier JSON exporté par Pladigit
            </div>
        </div>

        <div style="padding:24px;">

            @if($errors->any())
            <div style="padding:12px 14px;background:#FEE2E2;color:#991B1B;border-radius:8px;margin-bottom:16px;font-size:12px;line-height:1.6;">
                @foreach($errors->all() as $e)
                <div>⚠ {{ $e }}</div>
                @endforeach
            </div>
            @endif

            <form method="POST" action="{{ route('projects.import.store') }}" enctype="multipart/form-data">
                @csrf

                <div class="pd-form-group">
                    <label class="pd-label pd-label-req">Fichier JSON</label>
                    <input type="file" name="file" accept=".json,application/json" required
                           class="pd-input" style="width:100%;padding:8px;">
                    <div style="font-size:11px;color:var(--pd-muted);margin-top:4px;">
                        Fichier généré via Export → JSON (transfert) depuis un projet Pladigit.
                    </div>
                </div>

                <div class="pd-form-group">
                    <label class="pd-label">Nom du projet (optionnel)</label>
                    <input type="text" name="project_name" class="pd-input"
                           placeholder="Laissez vide pour conserver le nom original + '(importé)'"
                           value="{{ old('project_name') }}"
                           style="width:100%;">
                </div>

                <div style="background:var(--pd-bg2);border-radius:8px;padding:12px 14px;font-size:12px;color:var(--pd-muted);line-height:1.7;margin-bottom:20px;">
                    <strong style="color:var(--pd-text);">À noter lors de l'import :</strong><br>
                    • Vous devenez automatiquement propriétaire du projet importé.<br>
                    • Les membres sont recherchés par email — ceux introuvables sont ignorés.<br>
                    • Les jalons et tâches sont recréés à l'identique.<br>
                    • Les statuts "atteint" des jalons et "fait" des actions de com ne sont pas importés.<br>
                    • Les fichiers/documents attachés ne sont <em>pas</em> inclus dans l'export JSON.
                </div>

                <div style="display:flex;gap:10px;justify-content:flex-end;">
                    <a href="{{ route('projects.index') }}" class="pd-btn pd-btn-secondary pd-btn-sm">Annuler</a>
                    <button type="submit" class="pd-btn pd-btn-primary">
                        Importer le projet →
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
