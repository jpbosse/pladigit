<?php

namespace App\Enums;

/**
 * Types de documents officiels des collectivités territoriales.
 *
 * Source unique de vérité pour la classification documentaire Pladigit.
 * Utilisé par GedDocument::document_type et GedDocumentTemplate.
 *
 * Convention nommage automatique (ADR-038) :
 *   {CODE}-{AAAA}-{NNN}-{slug-objet}
 *   Ex : DEL-2026-042-budget-primitif
 *        ARR-2026-015-nomination-dgs
 *        CR-2026-007-conseil-municipal-juin
 */
enum GedDocumentType: string
{
    // ── Actes réglementaires ──────────────────────────────────
    case DELIBERATION   = 'deliberation';
    case ARRETE         = 'arrete';
    case DECISION       = 'decision';

    // ── Comptes rendus & procès-verbaux ───────────────────────
    case COMPTE_RENDU   = 'compte_rendu';
    case PROCES_VERBAL  = 'proces_verbal';

    // ── Correspondance & communications ───────────────────────
    case COURRIER       = 'courrier';
    case NOTE_SERVICE   = 'note_service';
    case RAPPORT        = 'rapport';

    // ── Marchés & contrats ───────────────────────────────────
    case MARCHE         = 'marche';
    case CONVENTION     = 'convention';
    case CONTRAT        = 'contrat';

    // ── Budgétaire ───────────────────────────────────────────
    case BUDGET         = 'budget';

    // ── Divers ───────────────────────────────────────────────
    case AUTRE          = 'autre';

    // ── Labels affichés ──────────────────────────────────────

    public function label(): string
    {
        return match ($this) {
            self::DELIBERATION  => 'Délibération',
            self::ARRETE        => 'Arrêté',
            self::DECISION      => 'Décision',
            self::COMPTE_RENDU  => 'Compte-rendu',
            self::PROCES_VERBAL => 'Procès-verbal',
            self::COURRIER      => 'Courrier',
            self::NOTE_SERVICE  => 'Note de service',
            self::RAPPORT       => 'Rapport',
            self::MARCHE        => 'Marché public',
            self::CONVENTION    => 'Convention',
            self::CONTRAT       => 'Contrat',
            self::BUDGET        => 'Budget',
            self::AUTRE         => 'Autre',
        };
    }

    /**
     * Préfixe utilisé dans le numérotation automatique.
     * Ex : DEL → DEL-2026-042
     */
    public function prefix(): string
    {
        return match ($this) {
            self::DELIBERATION  => 'DEL',
            self::ARRETE        => 'ARR',
            self::DECISION      => 'DEC',
            self::COMPTE_RENDU  => 'CR',
            self::PROCES_VERBAL => 'PV',
            self::COURRIER      => 'COUR',
            self::NOTE_SERVICE  => 'NS',
            self::RAPPORT       => 'RAP',
            self::MARCHE        => 'MP',
            self::CONVENTION    => 'CONV',
            self::CONTRAT       => 'CTR',
            self::BUDGET        => 'BUD',
            self::AUTRE         => 'DOC',
        };
    }

    /**
     * Icône Heroicon (outline) associée au type.
     * Utilisée dans les listes et badges GED.
     */
    public function icon(): string
    {
        return match ($this) {
            self::DELIBERATION  => 'document-text',
            self::ARRETE        => 'shield-check',
            self::DECISION      => 'check-badge',
            self::COMPTE_RENDU  => 'clipboard-document-list',
            self::PROCES_VERBAL => 'clipboard-document',
            self::COURRIER      => 'envelope',
            self::NOTE_SERVICE  => 'document',
            self::RAPPORT       => 'chart-bar',
            self::MARCHE        => 'briefcase',
            self::CONVENTION    => 'handshake',  // custom — fallback 'document-check'
            self::CONTRAT       => 'document-check',
            self::BUDGET        => 'banknotes',
            self::AUTRE         => 'paper-clip',
        };
    }

    /**
     * Couleur Tailwind (classe bg-*) pour le badge de type.
     */
    public function badgeColor(): string
    {
        return match ($this) {
            self::DELIBERATION  => 'bg-blue-100 text-blue-800',
            self::ARRETE        => 'bg-red-100 text-red-800',
            self::DECISION      => 'bg-purple-100 text-purple-800',
            self::COMPTE_RENDU  => 'bg-green-100 text-green-800',
            self::PROCES_VERBAL => 'bg-teal-100 text-teal-800',
            self::COURRIER      => 'bg-yellow-100 text-yellow-800',
            self::NOTE_SERVICE  => 'bg-orange-100 text-orange-800',
            self::RAPPORT       => 'bg-indigo-100 text-indigo-800',
            self::MARCHE        => 'bg-slate-100 text-slate-800',
            self::CONVENTION    => 'bg-cyan-100 text-cyan-800',
            self::CONTRAT       => 'bg-sky-100 text-sky-800',
            self::BUDGET        => 'bg-emerald-100 text-emerald-800',
            self::AUTRE         => 'bg-gray-100 text-gray-600',
        };
    }

    /**
     * Indique si ce type est un acte réglementaire soumis à numérotation
     * officielle (délibération, arrêté, décision).
     */
    public function isOfficialAct(): bool
    {
        return in_array($this, [
            self::DELIBERATION,
            self::ARRETE,
            self::DECISION,
        ], true);
    }

    /**
     * Toutes les valeurs pour les selects Livewire / Blade.
     *
     * @return array<string, string>  ['deliberation' => 'Délibération', ...]
     */
    public static function selectOptions(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /**
     * Types regroupés par catégorie pour un <optgroup> dans les selects.
     *
     * @return array<string, array<string, string>>
     */
    public static function groupedOptions(): array
    {
        return [
            'Actes réglementaires' => [
                self::DELIBERATION->value  => self::DELIBERATION->label(),
                self::ARRETE->value        => self::ARRETE->label(),
                self::DECISION->value      => self::DECISION->label(),
            ],
            'Comptes rendus' => [
                self::COMPTE_RENDU->value  => self::COMPTE_RENDU->label(),
                self::PROCES_VERBAL->value => self::PROCES_VERBAL->label(),
            ],
            'Correspondance' => [
                self::COURRIER->value      => self::COURRIER->label(),
                self::NOTE_SERVICE->value  => self::NOTE_SERVICE->label(),
                self::RAPPORT->value       => self::RAPPORT->label(),
            ],
            'Marchés & contrats' => [
                self::MARCHE->value        => self::MARCHE->label(),
                self::CONVENTION->value    => self::CONVENTION->label(),
                self::CONTRAT->value       => self::CONTRAT->label(),
            ],
            'Budgétaire' => [
                self::BUDGET->value        => self::BUDGET->label(),
            ],
            'Divers' => [
                self::AUTRE->value         => self::AUTRE->label(),
            ],
        ];
    }
}
