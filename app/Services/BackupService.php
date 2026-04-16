<?php

namespace App\Services;

use App\Models\Platform\Organization;
use App\Models\Tenant\TenantSettings;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Orchestre la création et l'envoi des sauvegardes Pladigit.
 *
 * Contenu sauvegardé :
 *   - DB platform (mysqldump)
 *   - DB tenant courant (mysqldump)
 *   - Fichiers GED (storage/app/private/ged/)
 *   - Fichiers NAS médias (chemin local configuré)
 *   - Fichier .env
 *
 * Destinations supportées : local (chemin serveur), SFTP (NAS distant).
 *
 * La sauvegarde est créée sous forme d'archive tar.gz horodatée.
 * La rotation supprime les archives anciennes au-delà du compte de rétention.
 */
class BackupService
{
    /**
     * Lance une sauvegarde complète pour l'organisation courante.
     *
     * @return array{ok: bool, message: string, size_bytes: int}
     */
    public function run(Organization $org, TenantSettings $settings): array
    {
        $label = date('Y-m-d_His').'_'.$org->slug;
        $tmpDir = sys_get_temp_dir().'/pladigit_backup_'.$label;

        try {
            if (! mkdir($tmpDir, 0750, true) && ! is_dir($tmpDir)) {
                throw new \RuntimeException("Impossible de créer le répertoire temporaire {$tmpDir}");
            }

            // ── 1. Dump bases de données ──────────────────────────────
            $this->dumpDatabase($tmpDir, 'mysql', 'db_platform.sql.gz');
            $this->dumpDatabase($tmpDir, 'tenant', "db_{$org->slug}.sql.gz");

            // ── 2. Fichiers GED ───────────────────────────────────────
            $gedPath = storage_path('app/private/ged');
            if (is_dir($gedPath)) {
                $this->copyDirectory($gedPath, $tmpDir.'/ged');
            }

            // ── 3. Fichiers NAS médias (driver local uniquement) ──────
            $nasPath = config('nas.local_path') ?: storage_path('app/nas_simulation');
            if (is_dir($nasPath)) {
                $this->copyDirectory($nasPath, $tmpDir.'/nas');
            }

            // ── 4. Fichier .env (sans les mots de passe chiffrés) ─────
            $envDest = $tmpDir.'/env.txt';
            copy(base_path('.env'), $envDest);

            // ── 5. Créer l'archive tar.gz ─────────────────────────────
            $archiveName = "backup_{$label}.tar.gz";
            $archivePath = sys_get_temp_dir().'/'.$archiveName;
            $this->createArchive($tmpDir, $archivePath);

            $sizeBytes = (int) filesize($archivePath);

            // ── 6. Envoyer à destination ──────────────────────────────
            $this->sendToDestination($archivePath, $archiveName, $settings);

            // ── 7. Rotation des anciennes sauvegardes ─────────────────
            $this->cleanOldBackups($settings, $org->slug);

            return [
                'ok' => true,
                'message' => "Sauvegarde {$archiveName} créée (".number_format($sizeBytes / 1024 / 1024, 1).' Mo).',
                'size_bytes' => $sizeBytes,
            ];

        } finally {
            // Nettoyage — toujours exécuté même en cas d'erreur
            if (is_dir($tmpDir)) {
                $this->removeDirectory($tmpDir);
            }
            if (isset($archivePath) && file_exists($archivePath)) {
                @unlink($archivePath);
            }
        }
    }

    // =========================================================================
    // Dump MySQL
    // =========================================================================

    /**
     * Dump une base de données avec mysqldump vers un fichier .sql.gz.
     *
     * @throws \RuntimeException si mysqldump échoue ou n'est pas disponible
     */
    private function dumpDatabase(string $destDir, string $connection, string $fileName): void
    {
        $cfg = config("database.connections.{$connection}");

        if (empty($cfg['database'])) {
            Log::warning("BackupService: connexion {$connection} sans base configurée — dump ignoré.");

            return;
        }

        $host = $cfg['host'] ?? '127.0.0.1';
        $port = (int) ($cfg['port'] ?? 3306);
        $user = $cfg['username'] ?? '';
        $pass = $cfg['password'] ?? '';
        $db = $cfg['database'];

        $destFile = $destDir.'/'.$fileName;

        // --no-tablespaces évite l'erreur "Access denied; you need PROCESS privilege"
        // --single-transaction garantit la cohérence sans verrou (InnoDB)
        $cmd = sprintf(
            'mysqldump --no-tablespaces --single-transaction --routines --events'
            .' -h %s -P %d -u %s %s %s 2>/dev/null | gzip > %s',
            escapeshellarg($host),
            $port,
            escapeshellarg($user),
            empty($pass) ? '' : '-p'.escapeshellarg($pass),
            escapeshellarg($db),
            escapeshellarg($destFile)
        );

        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new \RuntimeException("mysqldump {$db} a échoué (code {$exitCode}).");
        }
    }

    // =========================================================================
    // Archive
    // =========================================================================

    /**
     * Crée une archive tar.gz du répertoire source vers archivePath.
     *
     * Utilise tar(1) si disponible (plus rapide, streaming),
     * sinon ZipArchive en fallback.
     *
     * @throws \RuntimeException si la création échoue
     */
    private function createArchive(string $sourceDir, string $archivePath): void
    {
        $cmd = sprintf(
            'tar -czf %s -C %s . 2>/dev/null',
            escapeshellarg($archivePath),
            escapeshellarg($sourceDir)
        );

        exec($cmd, $output, $exitCode);

        if ($exitCode === 0 && file_exists($archivePath)) {
            return;
        }

        // Fallback : ZipArchive
        $zip = new \ZipArchive;

        // Remplace .tar.gz par .zip pour le fallback
        $archivePath = preg_replace('/\.tar\.gz$/', '.zip', $archivePath) ?? $archivePath;

        if ($zip->open($archivePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Impossible de créer l'archive {$archivePath}.");
        }

        $this->addDirectoryToZip($zip, $sourceDir, '');
        $zip->close();
    }

    /**
     * Ajoute récursivement un répertoire dans un ZipArchive.
     */
    private function addDirectoryToZip(\ZipArchive $zip, string $dir, string $prefix): void
    {
        $entries = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($entries as $file) {
            /** @var \SplFileInfo $file */
            $localPath = $prefix.ltrim(substr($file->getPathname(), strlen($dir)), '/\\');
            $zip->addFile($file->getPathname(), $localPath);
        }
    }

    // =========================================================================
    // Envoi vers destination
    // =========================================================================

    /**
     * Envoie l'archive vers la destination configurée (local ou SFTP).
     *
     * @throws \RuntimeException si l'envoi échoue
     */
    private function sendToDestination(string $archivePath, string $archiveName, TenantSettings $settings): void
    {
        $driver = $settings->backup_driver ?? 'local';

        match ($driver) {
            'local' => $this->sendLocal($archivePath, $archiveName, $settings),
            'sftp' => $this->sendSftp($archivePath, $archiveName, $settings),
            default => throw new \RuntimeException("Driver de sauvegarde inconnu : {$driver}"),
        };
    }

    /**
     * Copie l'archive dans un répertoire local.
     */
    private function sendLocal(string $archivePath, string $archiveName, TenantSettings $settings): void
    {
        $destDir = rtrim((string) ($settings->backup_local_path ?? ''), '/');

        if ($destDir === '') {
            throw new \RuntimeException('Chemin local de sauvegarde non configuré.');
        }

        if (! is_dir($destDir) && ! mkdir($destDir, 0750, true) && ! is_dir($destDir)) {
            throw new \RuntimeException("Impossible de créer le répertoire de destination {$destDir}.");
        }

        if (! copy($archivePath, $destDir.'/'.$archiveName)) {
            throw new \RuntimeException("Copie vers {$destDir} échouée.");
        }
    }

    /**
     * Envoie l'archive sur un serveur SFTP via l'extension PHP ssh2.
     *
     * Nécessite : sudo apt install php8.4-ssh2
     */
    private function sendSftp(string $archivePath, string $archiveName, TenantSettings $settings): void
    {
        if (! function_exists('ssh2_connect')) {
            throw new \RuntimeException(
                "L'extension PHP ssh2 n'est pas installée. "
                .'Exécutez : sudo apt install php8.4-ssh2 && sudo systemctl restart php8.4-fpm'
            );
        }

        $host = (string) ($settings->backup_sftp_host ?? '');
        $port = (int) ($settings->backup_sftp_port ?? 22);
        $user = (string) ($settings->backup_sftp_user ?? '');
        $remotePath = rtrim((string) ($settings->backup_sftp_path ?? '/backup'), '/');

        if ($host === '' || $user === '') {
            throw new \RuntimeException('SFTP : hôte ou utilisateur manquant.');
        }

        $password = '';
        if (! empty($settings->backup_sftp_password_enc)) {
            try {
                $password = Crypt::decryptString($settings->backup_sftp_password_enc);
            } catch (\Throwable) {
                throw new \RuntimeException('SFTP : impossible de déchiffrer le mot de passe.');
            }
        }

        $ssh = @ssh2_connect($host, $port);

        if ($ssh === false) {
            throw new \RuntimeException("SFTP : connexion à {$host}:{$port} impossible.");
        }

        if (! @ssh2_auth_password($ssh, $user, $password)) {
            throw new \RuntimeException("SFTP : authentification échouée pour {$user}@{$host}.");
        }

        $sftp = @ssh2_sftp($ssh);

        if ($sftp === false) {
            throw new \RuntimeException('SFTP : impossible d\'initialiser le sous-système SFTP.');
        }

        // Créer le répertoire distant si nécessaire
        @ssh2_sftp_mkdir($sftp, $remotePath, 0750, true);

        $remoteFile = $remotePath.'/'.$archiveName;
        $sftpStream = @fopen("ssh2.sftp://{$sftp}{$remoteFile}", 'w');

        if ($sftpStream === false) {
            throw new \RuntimeException("SFTP : impossible d'ouvrir le flux vers {$remoteFile}.");
        }

        $localStream = fopen($archivePath, 'r');

        if ($localStream === false) {
            fclose($sftpStream);
            throw new \RuntimeException("Impossible de lire l'archive locale {$archivePath}.");
        }

        $transferred = stream_copy_to_stream($localStream, $sftpStream);
        fclose($localStream);
        fclose($sftpStream);

        if ($transferred === false || $transferred === 0) {
            throw new \RuntimeException('SFTP : transfert de l\'archive échoué.');
        }
    }

    // =========================================================================
    // Rotation des sauvegardes
    // =========================================================================

    /**
     * Supprime les archives les plus anciennes au-delà du compte de rétention.
     * Ne s'applique qu'au driver local (les fichiers distants SFTP ne sont pas listés).
     */
    private function cleanOldBackups(TenantSettings $settings, string $orgSlug): void
    {
        if (($settings->backup_driver ?? 'local') !== 'local') {
            return;
        }

        $destDir = rtrim((string) ($settings->backup_local_path ?? ''), '/');

        if ($destDir === '' || ! is_dir($destDir)) {
            return;
        }

        $retention = max(1, (int) ($settings->backup_retention_count ?? 7));

        $archives = glob($destDir."/backup_*_{$orgSlug}.tar.gz") ?: [];

        // Trier par date (le nom commence par backup_YYYY-MM-DD_HHmmss_)
        sort($archives);

        $toDelete = array_slice($archives, 0, max(0, count($archives) - $retention));

        foreach ($toDelete as $file) {
            @unlink($file);
        }
    }

    // =========================================================================
    // Test de connexion SFTP
    // =========================================================================

    /**
     * Teste la connexion SFTP sans transférer de fichier.
     *
     * @return array{ok: bool, message: string}
     */
    public function testSftp(TenantSettings $settings): array
    {
        if (! function_exists('ssh2_connect')) {
            return [
                'ok' => false,
                'message' => 'Extension php-ssh2 manquante. Installez-la : sudo apt install php8.4-ssh2',
            ];
        }

        try {
            $host = (string) ($settings->backup_sftp_host ?? '');
            $port = (int) ($settings->backup_sftp_port ?? 22);
            $user = (string) ($settings->backup_sftp_user ?? '');

            if ($host === '' || $user === '') {
                return ['ok' => false, 'message' => 'Hôte ou utilisateur non configuré.'];
            }

            $password = '';
            if (! empty($settings->backup_sftp_password_enc)) {
                $password = Crypt::decryptString($settings->backup_sftp_password_enc);
            }

            $ssh = @ssh2_connect($host, $port);

            if ($ssh === false) {
                return ['ok' => false, 'message' => "Connexion à {$host}:{$port} impossible."];
            }

            if (! @ssh2_auth_password($ssh, $user, $password)) {
                return ['ok' => false, 'message' => "Authentification échouée pour {$user}@{$host}."];
            }

            return ['ok' => true, 'message' => "Connexion SFTP réussie à {$user}@{$host}:{$port}."];

        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    // =========================================================================
    // Helpers fichiers
    // =========================================================================

    /**
     * Copie récursivement un répertoire source vers destination.
     * Crée la destination si elle n'existe pas.
     */
    private function copyDirectory(string $src, string $dst): void
    {
        if (! is_dir($src)) {
            return;
        }

        if (! is_dir($dst) && ! mkdir($dst, 0750, true) && ! is_dir($dst)) {
            throw new \RuntimeException("Impossible de créer {$dst}.");
        }

        $entries = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($entries as $entry) {
            /** @var \SplFileInfo $entry */
            $target = $dst.'/'.substr($entry->getPathname(), strlen($src) + 1);

            if ($entry->isDir()) {
                if (! is_dir($target)) {
                    mkdir($target, 0750, true);
                }
            } else {
                copy($entry->getPathname(), $target);
            }
        }
    }

    /**
     * Supprime récursivement un répertoire.
     */
    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $entries = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($entries as $entry) {
            /** @var \SplFileInfo $entry */
            if ($entry->isDir()) {
                rmdir($entry->getPathname());
            } else {
                unlink($entry->getPathname());
            }
        }

        rmdir($dir);
    }
}
