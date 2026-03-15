<?php

namespace App\Services;

use App\Models\Tenant\TenantSettings;
use App\Services\TenantManager;

/**
 * WatermarkService — Appose un watermark sur une image JPEG/PNG en mémoire.
 *
 * Principes :
 *   - Le fichier NAS original n'est JAMAIS modifié.
 *   - Le watermark est appliqué à la volée lors du téléchargement uniquement.
 *   - Utilise GD (natif PHP 8.x) — aucune dépendance externe.
 *   - Supporte JPEG, PNG, WEBP.
 *   - Type text  : texte libre rendu avec la police GD embarquée.
 *   - Type logo  : logo de l'organisation superposé en transparence.
 *
 * Usage :
 *   $service = app(WatermarkService::class);
 *   $watermarked = $service->apply($imageContents, $mimeType, $settings);
 *   // $watermarked = string binaire JPEG à envoyer au client
 */
class WatermarkService
{
    /**
     * Applique le watermark configuré sur l'image.
     *
     * @param  string          $contents   Contenu binaire de l'image originale
     * @param  string          $mimeType   MIME type (image/jpeg, image/png, image/webp)
     * @param  TenantSettings  $settings   Configuration watermark du tenant
     * @return string                      Contenu binaire de l'image avec watermark (JPEG)
     *
     * @throws \RuntimeException Si GD ne peut pas lire l'image
     */
    public function apply(string $contents, string $mimeType, TenantSettings $settings): string
    {
        if (! $this->isSupportedMime($mimeType)) {
            return $contents;
        }

        $image = imagecreatefromstring($contents);

        if ($image === false) {
            throw new \RuntimeException('WatermarkService : impossible de décoder l\'image.');
        }

        // Convertir en vraies couleurs pour pouvoir appliquer alpha
        if (imageistruecolor($image) === false) {
            $trueColor = imagecreatetruecolor(imagesx($image), imagesy($image));
            if ($trueColor !== false) {
                imagecopy($trueColor, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
                imagedestroy($image);
                $image = $trueColor;
            }
        }

        $type = $settings->wm_type ?? 'text';

        if ($type === 'logo') {
            $this->applyLogoWatermark($image, $settings);
        } else {
            $this->applyTextWatermark($image, $settings);
        }

        // Sortie en JPEG (qualité 92)
        ob_start();
        imagejpeg($image, null, 92);
        $result = ob_get_clean();
        imagedestroy($image);

        return $result ?: $contents;
    }

    /**
     * Indique si le watermark doit être appliqué pour ce fichier.
     * Retourne false pour les vidéos, PDF, et types non supportés.
     */
    public function shouldApply(string $mimeType, TenantSettings $settings): bool
    {
        if (! ($settings->wm_enabled ?? false)) {
            return false;
        }

        return $this->isSupportedMime($mimeType);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Watermark texte
    // ─────────────────────────────────────────────────────────────────────────

    private function applyTextWatermark(\GdImage $image, TenantSettings $settings): void
    {
        $text = $settings->wm_text ?? '© Pladigit';

        if (trim($text) === '') {
            return;
        }

        $imgW    = imagesx($image);
        $imgH    = imagesy($image);
        $padding = (int) ($imgW * 0.015);
        $padding = max(8, $padding);

        $fontPath = $this->resolveFontPath();
        $fontSize = $this->resolveFontSizePt($settings->wm_size ?? 'medium', $imgW);
        $opacity  = max(10, min(100, $settings->wm_opacity ?? 60));

        if ($fontPath !== null && function_exists('imagettfbbox')) {
            // ── TTF : accents + fond rectangle ──────────────────────────
            $bbox = imagettfbbox($fontSize, 0, $fontPath, $text);
            if ($bbox === false) {
                $this->applyTextWatermarkLegacy($image, $text, $settings);
                return;
            }

            $textW = abs($bbox[4] - $bbox[0]);
            $textH = abs($bbox[5] - $bbox[1]);

            $padH = (int) ($fontSize * 0.4);
            $padV = (int) ($fontSize * 0.25);

            $boxW = $textW + $padH * 2;
            $boxH = $textH + $padV * 2;

            [$boxX, $boxY] = $this->resolvePosition(
                $settings->wm_position ?? 'bottom-right',
                $imgW, $imgH, $boxW, $boxH, $padding
            );

            // Fond rectangle noir semi-transparent
            $bgAlpha  = (int) round(127 - ($opacity / 100 * 90));
            $bgColor  = imagecolorallocatealpha($image, 0, 0, 0, $bgAlpha);
            if ($bgColor !== false) {
                imagefilledrectangle($image, $boxX, $boxY, $boxX + $boxW, $boxY + $boxH, $bgColor);
            }

            // Texte blanc
            $textAlpha = (int) round((100 - $opacity) * 30 / 90); // très peu transparent
            $textColor = imagecolorallocatealpha($image, 255, 255, 255, $textAlpha);
            if ($textColor === false) {
                return;
            }

            $textX = $boxX + $padH;
            $textY = $boxY + $padV + $textH; // baseline TTF

            imagettftext($image, $fontSize, 0, $textX, $textY, $textColor, $fontPath, $text);
        } else {
            // ── Fallback : police interne GD (sans accents) ──────────────
            $this->applyTextWatermarkLegacy($image, $text, $settings);
        }
    }

    /**
     * Fallback police GD interne — utilisé si FreeType non disponible.
     * Ne supporte pas les accents mais reste fonctionnel.
     */
    private function applyTextWatermarkLegacy(\GdImage $image, string $text, TenantSettings $settings): void
    {
        // Translittération basique des accents courants
        $map = [
            'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
            'à'=>'a','â'=>'a','ä'=>'a',
            'ù'=>'u','û'=>'u','ü'=>'u',
            'î'=>'i','ï'=>'i',
            'ô'=>'o','ö'=>'o',
            'ç'=>'c','œ'=>'oe','æ'=>'ae',
            'É'=>'E','È'=>'E','Ê'=>'E',
            'À'=>'A','Â'=>'A',
            'Ù'=>'U','Û'=>'U',
            'Î'=>'I','Ô'=>'O','Ç'=>'C',
        ];
        $text = strtr($text, $map);

        $imgW    = imagesx($image);
        $imgH    = imagesy($image);
        $gdSize  = $this->resolveFontSizeGd($settings->wm_size ?? 'medium', $imgW);
        $padding = max(8, (int) ($imgW * 0.015));
        $opacity = max(10, min(100, $settings->wm_opacity ?? 60));

        $charW = (int) imagefontwidth($gdSize);
        $charH = (int) imagefontheight($gdSize);
        $textW = $charW * mb_strlen($text);
        $textH = $charH;

        $padH = 8;
        $padV = 5;
        $boxW = $textW + $padH * 2;
        $boxH = $textH + $padV * 2;

        [$boxX, $boxY] = $this->resolvePosition(
            $settings->wm_position ?? 'bottom-right',
            $imgW, $imgH, $boxW, $boxH, $padding
        );

        $bgAlpha = (int) round(127 - ($opacity / 100 * 90));
        $bgColor = imagecolorallocatealpha($image, 0, 0, 0, $bgAlpha);
        if ($bgColor !== false) {
            imagefilledrectangle($image, $boxX, $boxY, $boxX + $boxW, $boxY + $boxH, $bgColor);
        }

        $textAlpha = (int) round((100 - $opacity) * 30 / 90);
        $textColor = imagecolorallocatealpha($image, 255, 255, 255, $textAlpha);
        if ($textColor !== false) {
            imagestring($image, $gdSize, $boxX + $padH, $boxY + $padV, $text, $textColor);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Watermark logo
    // ─────────────────────────────────────────────────────────────────────────

    private function applyLogoWatermark(\GdImage $image, TenantSettings $settings): void
    {
        // Récupérer le logo de l'organisation depuis l'org courante
        $org = app(TenantManager::class)->current();

        if ($org === null || empty($org->logo_path)) {
            // Fallback texte si pas de logo configuré
            $this->applyTextWatermark($image, $settings);
            return;
        }

        $logoPath = public_path($org->logo_path);

        if (! file_exists($logoPath)) {
            $this->applyTextWatermark($image, $settings);
            return;
        }

        $logoContents = file_get_contents($logoPath);
        if ($logoContents === false) {
            $this->applyTextWatermark($image, $settings);
            return;
        }

        $logo = imagecreatefromstring($logoContents);
        if ($logo === false) {
            $this->applyTextWatermark($image, $settings);
            return;
        }

        $imgW = imagesx($image);
        $imgH = imagesy($image);

        // Redimensionner le logo à ~20% de la largeur de l'image
        $targetW   = (int) ($imgW * $this->resolveLogoRatio($settings->wm_size ?? 'medium'));
        $logoOrigW = imagesx($logo);
        $logoOrigH = imagesy($logo);
        $ratio     = $logoOrigW / $logoOrigH;
        $targetH   = (int) ($targetW / $ratio);

        $logoResized = imagecreatetruecolor($targetW, $targetH);
        if ($logoResized === false) {
            imagedestroy($logo);
            $this->applyTextWatermark($image, $settings);
            return;
        }

        // Préserver la transparence PNG
        imagealphablending($logoResized, false);
        imagesavealpha($logoResized, true);
        $transparent = imagecolorallocatealpha($logoResized, 0, 0, 0, 127);
        if ($transparent !== false) {
            imagefilledrectangle($logoResized, 0, 0, $targetW, $targetH, $transparent);
        }
        imagealphablending($logoResized, true);

        imagecopyresampled($logoResized, $logo, 0, 0, 0, 0, $targetW, $targetH, $logoOrigW, $logoOrigH);
        imagedestroy($logo);

        $padding = (int) ($imgW * 0.02);
        [$x, $y] = $this->resolvePosition(
            $settings->wm_position ?? 'bottom-right',
            $imgW, $imgH, $targetW, $targetH, $padding
        );

        // Appliquer l'opacité via imagecopymerge
        $opacity = (int) round(($settings->wm_opacity ?? 60) * 100 / 100);
        imagecopymerge($image, $logoResized, $x, $y, 0, 0, $targetW, $targetH, $opacity);

        imagedestroy($logoResized);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Calcule les coordonnées X, Y selon la position choisie.
     *
     * @return array{int, int}
     */
    private function resolvePosition(
        string $position,
        int $imgW, int $imgH,
        int $elemW, int $elemH,
        int $padding
    ): array {
        return match ($position) {
            'bottom-left'   => [$padding, $imgH - $elemH - $padding],
            'center'        => [(int) (($imgW - $elemW) / 2), (int) (($imgH - $elemH) / 2)],
            'bottom-center' => [(int) (($imgW - $elemW) / 2), $imgH - $elemH - $padding],
            default         => [$imgW - $elemW - $padding, $imgH - $elemH - $padding], // bottom-right
        };
    }

    /**
     * Cherche une police TrueType disponible sur le serveur.
     * Priorité : DejaVuSans Bold → DejaVuSans → Carlito → null (fallback GD).
     */
    private function resolveFontPath(): ?string
    {
        $candidates = [
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/crosextra/Carlito-Bold.ttf',
            '/usr/share/fonts/truetype/crosextra/Carlito-Regular.ttf',
        ];

        foreach ($candidates as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Taille de police TTF en points, adaptée à la largeur de l'image.
     */
    private function resolveFontSizePt(string $size, int $imgW): float
    {
        $base = match ($size) {
            'small'  => 14.0,
            'large'  => 28.0,
            default  => 20.0, // medium
        };

        // Adapter proportionnellement à la taille de l'image
        $scale = $imgW / 1200.0;
        $scale = max(0.5, min(2.5, $scale));

        return round($base * $scale, 1);
    }

    /**
     * Taille de police GD interne (1–5) — fallback sans FreeType.
     */
    private function resolveFontSizeGd(string $size, int $imgW): int
    {
        $base = match ($size) {
            'small'  => 2,
            'large'  => 5,
            default  => 4,
        };

        if ($imgW < 400) {
            return max(1, $base - 1);
        }
        if ($imgW > 2000) {
            return min(5, $base + 1);
        }

        return $base;
    }

    /**
     * Ratio de largeur du logo selon la taille.
     */
    private function resolveLogoRatio(string $size): float
    {
        return match ($size) {
            'small'  => 0.12,
            'large'  => 0.28,
            default  => 0.20,
        };
    }

    /**
     * Convertit opacity 10–100 en valeur alpha GD 0–127 (0 = opaque, 127 = transparent).
     */
    /**
     * MIME types supportés par GD pour le watermark.
     */
    private function isSupportedMime(string $mimeType): bool
    {
        return in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], strict: true);
    }
}
