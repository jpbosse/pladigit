@extends('layouts.app')
@section('title', 'Paramètres — Visioconférence')

@section('content')
<div class="pd-page-header">
    <div>
        <div class="pd-page-title">Visioconférence</div>
        <div class="pd-page-sub">Configuration Jitsi Meet</div>
    </div>
</div>

@if(session('success'))
<div class="pd-alert pd-alert-success">{{ session('success') }}</div>
@endif

<div class="pd-card" style="max-width:600px;">
    <form method="POST" action="{{ route('admin.settings.visio.update') }}">
        @csrf @method('PUT')

        <div class="pd-form-group">
            <label class="pd-label">Serveur Jitsi Meet</label>
            <input type="url" name="jitsi_base_url" class="pd-input"
                   value="{{ old('jitsi_base_url', $settings->jitsi_base_url ?? 'https://meet.numerique.gouv.fr') }}"
                   placeholder="https://meet.numerique.gouv.fr">
            <div class="pd-hint">
                URL de base du serveur Jitsi. Par défaut : <strong>meet.numerique.gouv.fr</strong>
                (instance publique de l'État français — RGPD, gratuite, sans inscription).
                Les collectivités auto-hébergeant Jitsi peuvent indiquer leur propre instance.
            </div>
            @error('jitsi_base_url')
            <div class="pd-error">{{ $message }}</div>
            @enderror
        </div>

        {{-- Aperçu --}}
        <div style="padding:12px 14px;background:var(--pd-surface2);border-radius:8px;border:0.5px solid var(--pd-border);margin-bottom:16px;">
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--pd-muted);margin-bottom:6px;">Exemple de salle générée</div>
            <div style="font-size:12px;color:var(--pd-text);font-family:monospace;">
                {{ $settings->jitsi_base_url ?? 'https://meet.numerique.gouv.fr' }}/pladigit-mon-projet-a3f9k2
            </div>
        </div>

        <div class="pd-form-actions">
            <button type="submit" class="pd-btn pd-btn-primary">Enregistrer</button>
        </div>
    </form>
</div>
@endsection
