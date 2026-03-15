<?php

namespace App\Enums;

/**
 * Modules fonctionnels activables par organisation.
 *
 * Source unique de vérité — utilisé par :
 *   - Organization::hasModule()
 *   - Middleware RequireModule
 *   - Interface Super Admin (checkboxes)
 *
 * Convention de phases :
 *   Phase 3–4  : media
 *   Phase 5    : ged
 *   Phase 6    : collabora
 *   Phase 7    : erp
 *   Phase 8    : projects
 *   Phase 9    : chat
 *   Phase 10   : news
 *   Phase 11   : surveys
 *
 * Usage :
 *   ModuleKey::MEDIA->label()             // 'Photothèque'
 *   ModuleKey::MEDIA->phase()             // 3
 *   ModuleKey::MEDIA->isAvailable()       // true (Phase 3 en cours)
 *   ModuleKey::values()                   // ['media', 'ged', ...]
 *   ModuleKey::available()                // cases dont la phase est livrée
 */
enum ModuleKey: string
{
    case MEDIA = 'media';
    case GED = 'ged';
    case COLLABORA = 'collabora';
    case ERP = 'erp';
    case PROJECTS = 'projects';
    case CHAT = 'chat';
    case NEWS = 'news';
    case SURVEYS = 'surveys';

    /**
     * Libellé français pour l'affichage.
     */
    public function label(): string
    {
        return match ($this) {
            self::MEDIA => 'Photothèque',
            self::GED => 'Gestion documentaire',
            self::COLLABORA => 'Édition collaborative (Collabora)',
            self::ERP => 'ERP DataGrid',
            self::PROJECTS => 'Gestion de projet',
            self::CHAT => 'Chat temps réel',
            self::NEWS => 'Fil d\'actualités',
            self::SURVEYS => 'Sondages & questionnaires',
        };
    }

    /**
     * Description courte affichée sous le label.
     */
    public function description(): string
    {
        return match ($this) {
            self::MEDIA => 'Galerie connectée au NAS, albums, droits, watermark',
            self::GED => 'Arborescence documentaire, versionning, recherche plein texte',
            self::COLLABORA => 'Édition ODT/ODS/ODP en ligne — alternative à Microsoft Office',
            self::ERP => 'Tables no-code, audit trail, export CSV/Excel',
            self::PROJECTS => 'Kanban, Gantt simplifié, agenda partagé',
            self::CHAT => 'Canaux, messagerie 1:1, WebSocket Soketi',
            self::NEWS => 'Agrégateur RSS, widget dashboard',
            self::SURVEYS => 'Formulaires, résultats en temps réel',
        };
    }

    /**
     * Phase de livraison prévue au CDC.
     */
    public function phase(): int
    {
        return match ($this) {
            self::MEDIA => 3,
            self::GED => 5,
            self::COLLABORA => 6,
            self::ERP => 7,
            self::PROJECTS => 8,
            self::CHAT => 9,
            self::NEWS => 10,
            self::SURVEYS => 11,
        };
    }

    /**
     * Indique si le module est déployé (phase livrée ou en cours).
     * Phase 3 en cours → media = disponible, ged = pas encore.
     */
    public function isAvailable(): bool
    {
        return $this->phase() <= 3;
    }

    /**
     * Icône SVG Heroicons inline (outline) pour l'interface.
     */
    public function icon(): string
    {
        return match ($this) {
            self::MEDIA => 'photograph',
            self::GED => 'folder-open',
            self::COLLABORA => 'document-text',
            self::ERP => 'table',
            self::PROJECTS => 'view-boards',
            self::CHAT => 'chat-alt-2',
            self::NEWS => 'rss',
            self::SURVEYS => 'clipboard-list',
        };
    }

    /**
     * Toutes les valeurs string — utile pour la validation.
     * Ex : ['required', 'in:' . implode(',', ModuleKey::values())]
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Modules disponibles dans la version actuelle (phase livrée).
     *
     * @return list<self>
     */
    public static function available(): array
    {
        return array_values(
            array_filter(self::cases(), fn (self $m) => $m->isAvailable())
        );
    }

    /**
     * Options pour select HTML — [value => label].
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
