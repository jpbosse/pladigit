<?php

namespace App\Services;

use App\Models\Platform\Organization;
use App\Models\Platform\PlatformSettings;
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
 * Si GPG est activé, l'archive est chiffrée (AES-256) et un fichier
 * SHA-256 est généré pour vérification d'intégrité.
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

            // ── 4. Fichier .env ───────────────────────────────────────
            copy(base_path('.env'), $tmpDir.'/env.txt');

            // ── 5. Créer l'archive tar.gz ─────────────────────────────
            $archiveName = "backup_{$label}.tar.gz";
            $archivePath = sys_get_temp_dir().'/'.$archiveName;
            $this->createArchive($tmpDir, $archivePath);

            $sizeBytes = (int) filesize($archivePath);

            // ── 6. Chiffrement GPG (si activé) ───────────────────────
            if ($this->gpgEnabled()) {
                $archivePath = $this->encryptArchive($archivePath);
                $archiveName = basename($archivePath);
                $sizeBytes = (int) filesize($archivePath);
            }

            // ── 7. Somme de contrôle SHA-256 ──────────────────────────
            $this->writeChecksum($archivePath);

            // ── 8. Envoyer à destination ──────────────────────────────
            $this->sendToDestination($archivePath, $archiveName, $settings);

            // ── 9. Rotation des anciennes sauvegardes ─────────────────
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
            if (isset($archivePath) && file_exists($archivePath.'.sha256')) {
                @unlink($archivePath.'.sha256');
            }
        }
    }

    // =========================================================================
    // Chiffrement GPG et intégrité SHA-256
    // =========================================================================

    /**
     * Indique si le chiffrement GPG est activé et configuré.
     */
    private function gpgEnabled(): bool
    {
        $ps = PlatformSettings::first();

        return $ps !== null
            && $ps->backup_gpg_enabled
            && ! empty($ps->backup_gpg_passphrase_enc);
    }

    /**
     * Chiffre l'archive avec GPG (AES-256, passphrase symétrique).
     *
     * Supprime l'archive non chiffrée après chiffrement réussi.
     *
     * @throws \RuntimeException si GPG n'est pas disponible ou si le chiffrement échoue
     */
    private function encryptArchive(string $archivePath): string
    {
        exec('which gpg 2>/dev/null', $out, $code);
        if ($code !== 0) {
            throw new \RuntimeException(
                "gpg n'est pas installé. Exécutez : sudo apt install gnupg"
            );
        }

        $ps = PlatformSettings::firstOrFail();
        $passphrase = Crypt::decryptString((string) $ps->backup_gpg_passphrase_enc);

        $encryptedPath = $archivePath.'.gpg';

        $cmd = sprintf(
            'gpg --batch --yes --symmetric --cipher-algo AES256 '
            .'--passphrase %s --output %s %s 2>/dev/null',
            escapeshellarg($passphrase),
            escapeshellarg($encryptedPath),
            escapeshellarg($archivePath)
        );

        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0 || ! file_exists($encryptedPath)) {
            throw new \RuntimeException('Chiffrement GPG de l\'archive échoué (code '.$exitCode.').');
        }

        // Supprimer l'archive non chiffrée
        @unlink($archivePath);

        return $encryptedPath;
    }

    /**
     * Génère un fichier de somme de contrôle SHA-256 à côté de l'archive.
     *
     * Exemple : backup_2026-05-08_demo.tar.gz.gpg
     *        → backup_2026-05-08_demo.tar.gz.gpg.sha256
     *
     * @throws \RuntimeException si le hash ne peut pas être calculé
     */
    private function writeChecksum(string $archivePath): void
    {
        $hash = hash_file('sha256', $archivePath);

        if ($hash === false) {
            throw new \RuntimeException("Impossible de calculer le SHA-256 de {$archivePath}.");
        }

        $content = $hash.'  '.basename($archivePath).PHP_EOL;

        if (file_put_contents($archivePath.'.sha256', $content) === false) {
            throw new \RuntimeException("Impossible d'écrire le fichier de somme de contrôle.");
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

        $cmd = sprintf(
            'mysqldump --no-tablespaces --single-transaction --routines --events'
            .' -h %s -P %d -u %s %s %s 2>/dev/null | gzip > %s',
            escapeshellarg($host),
            $port,
            escapeshellarg($user),
            empty($pass) ? '' : '-p'.escapeshellarg($pass),
            escapeshellarg($db),
            escapeshellarg($destDir.'/'.$fileName)
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
     * Envoie l'archive et son fichier SHA-256 vers la destination configurée.
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
     * Copie l'archive et son fichier SHA-256 dans un répertoire local.
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

        // Copier aussi le fichier SHA-256
        $checksumPath = $archivePath.'.sha256';
        if (file_exists($checksumPath)) {
            copy($checksumPath, $destDir.'/'.$archiveName.'.sha256');
        }
    }

    /**
     * Envoie l'archive sur un serveur SFTP via l'extension PHP ssh2.
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
     * Supprime aussi les fichiers .sha256 associés.
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

        $archives = array_merge(
            glob($destDir."/backup_*_{$orgSlug}.tar.gz") ?: [],
            glob($destDir."/backup_*_{$orgSlug}.tar.gz.gpg") ?: []
        );

        sort($archives);

        $toDelete = array_slice($archives, 0, max(0, count($archives) - $retention));

        foreach ($toDelete as $file) {
            @unlink($file);
            @unlink($file.'.sha256');
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
