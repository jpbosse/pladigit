<?php

namespace App\Console\Commands;

use App\Models\Platform\Organization;
use App\Models\Tenant\GedDocument;
use App\Models\Tenant\GedDocumentVersion;
use App\Models\Tenant\GedFolder;
use App\Models\Tenant\MediaItem;
use App\Models\Tenant\User;
use App\Services\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Remet l'organisation démo dans son état initial.
 *
 * - Vide toutes les tables tenant
 * - Supprime les fichiers GED et médias
 * - Re-seed les données statiques (DemoSeeder)
 * - Copie les fichiers depuis storage/demo_ged/ et storage/demo_photos/
 *
 * Usage : php artisan demo:reset
 * Planifié : chaque nuit à minuit (routes/console.php)
 */
class DemoResetCommand extends Command
{
    protected $signature   = 'demo:reset {--slug=demo : Slug de l\'organisation démo}';
    protected $description = 'Remet l\'organisation démo dans son état initial (données + fichiers)';

    public function __construct(private TenantManager $tenantManager)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $slug = $this->option('slug');

        $org = Organization::where('slug', $slug)->first();
        if (! $org) {
            $this->error("Organisation « {$slug} » introuvable en base platform.");
            return self::FAILURE;
        }

        $this->tenantManager->connectTo($org);
        $this->info("Remise à zéro de « {$org->name} » (base : {$org->db_name})...");

        $this->wipeTables();
        $this->info('✓ Tables vidées');

        $this->wipePhysicalFiles();
        $this->info('✓ Fichiers physiques supprimés');

        $seeder = new \Database\Seeders\DemoSeeder();
        $seeder->setContainer(app())->setCommand($this);
        $seeder->run();
        $this->info('✓ Données de base re-seedées');

        $this->seedGedFiles();
        $this->seedPhotos();

        $this->info('');
        $this->info('Remise à zéro terminée.');

        return self::SUCCESS;
    }

    // ─────────────────────────────────────────────────────────────
    //  Vidage des tables
    // ─────────────────────────────────────────────────────────────

    private function wipeTables(): void
    {
        DB::connection('tenant')->statement('SET FOREIGN_KEY_CHECKS=0');

        $tables = [
            'ged_wopi_locks',
            'ged_wopi_tokens',
            'ged_document_versions',
            'ged_documents',
            'ged_folder_permissions',
            'ged_folder_user_permissions',
            'ged_folders',
            'task_comments',
            'tasks',
            'project_milestones',
            'project_members',
            'projects',
            'shares',
            'media_items',
            'media_albums',
            'department_user',
            'departments',
            'notifications',
            'audit_logs',
            'sessions',
            'personal_access_tokens',
            'password_reset_tokens',
            'users',
        ];

        foreach ($tables as $table) {
            try {
                DB::connection('tenant')->table($table)->truncate();
            } catch (\Throwable) {
                // Table absente selon la version des migrations — on continue
            }
        }

        DB::connection('tenant')->statement('SET FOREIGN_KEY_CHECKS=1');
    }

    // ─────────────────────────────────────────────────────────────
    //  Suppression des fichiers physiques
    // ─────────────────────────────────────────────────────────────

    private function wipePhysicalFiles(): void
    {
        // GED — driver local : storage/app/ged/
        Storage::disk('local')->deleteDirectory('ged');

        // Médias NAS simulation
        $nasPath = config('nas.local_path', storage_path('app/nas_simulation'));
        if (is_dir($nasPath)) {
            foreach (glob("{$nasPath}/*") as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  Seed documents GED depuis storage/demo_ged/
    // ─────────────────────────────────────────────────────────────

    private function seedGedFiles(): void
    {
        $sourcePath = storage_path('demo_ged');

        if (! is_dir($sourcePath)) {
            $this->warn('  ⚠  storage/demo_ged/ absent — documents GED ignorés.');
            return;
        }

        $admin = User::on('tenant')->where('role', 'admin')->first();
        if (! $admin) {
            return;
        }

        $this->processGedDir($sourcePath, null, $admin->id);
        $this->info('✓ Documents GED copiés');
    }

    private function processGedDir(string $dirPath, ?int $parentId, int $adminId): void
    {
        $items = array_diff(scandir($dirPath) ?: [], ['.', '..']);

        // Si ce n'est pas la racine demo_ged, créer un dossier GED
        $folderId = $parentId;
        if (basename($dirPath) !== 'demo_ged') {
            $name   = basename($dirPath);
            $slug   = Str::slug($name);
            $folder = GedFolder::create([
                'name'       => $name,
                'slug'       => $slug,
                'path'       => '/' . $slug,
                'nas_path'   => '',
                'parent_id'  => $parentId,
                'is_private' => false,
                'created_by' => $adminId,
            ]);
            $folderId = $folder->id;
        }

        foreach ($items as $item) {
            $fullPath = $dirPath . '/' . $item;

            if (is_dir($fullPath)) {
                $this->processGedDir($fullPath, $folderId, $adminId);
            } elseif (is_file($fullPath) && $folderId !== null) {
                $this->createGedDocument($fullPath, $item, $folderId, $adminId);
            }
        }
    }

    private function createGedDocument(
        string $sourcePath,
        string $fileName,
        int $folderId,
        int $adminId
    ): void {
        $ext      = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $mime     = $this->guessMime($ext);
        $uuid     = Str::uuid()->toString();
        $diskPath = "ged/{$uuid}.{$ext}";
        $size     = filesize($sourcePath);

        Storage::disk('local')->put($diskPath, file_get_contents($sourcePath));

        $doc = GedDocument::create([
            'folder_id'       => $folderId,
            'name'            => $fileName,
            'disk_path'       => $diskPath,
            'mime_type'       => $mime,
            'size_bytes'      => $size,
            'current_version' => 1,
            'created_by'      => $adminId,
        ]);

        GedDocumentVersion::create([
            'document_id'    => $doc->id,
            'version_number' => 1,
            'disk_path'      => $diskPath,
            'mime_type'      => $mime,
            'size_bytes'     => $size,
            'uploaded_by'    => $adminId,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    //  Seed photos depuis storage/demo_photos/
    // ─────────────────────────────────────────────────────────────

    private function seedPhotos(): void
    {
        $sourcePath = storage_path('demo_photos');

        if (! is_dir($sourcePath)) {
            $this->warn('  ⚠  storage/demo_photos/ absent — photos ignorées.');
            return;
        }

        $photos = glob("{$sourcePath}/*.{jpg,jpeg,png,webp}", GLOB_BRACE) ?: [];

        if (empty($photos)) {
            $this->warn('  ⚠  Aucune photo dans storage/demo_photos/ — photos ignorées.');
            return;
        }

        $admin = User::on('tenant')->where('role', 'admin')->first();
        if (! $admin) {
            return;
        }

        $album = \App\Models\Tenant\MediaAlbum::on('tenant')
            ->where('name', 'Fête de la commune 2025')
            ->first();
        if (! $album) {
            return;
        }

        $nasPath = config('nas.local_path', storage_path('app/nas_simulation'));
        if (! is_dir($nasPath)) {
            mkdir($nasPath, 0775, true);
        }

        $count = 0;
        foreach ($photos as $photoPath) {
            $ext      = strtolower(pathinfo($photoPath, PATHINFO_EXTENSION));
            $sha256   = hash_file('sha256', $photoPath);
            $destName = "{$sha256}.{$ext}";
            $destFull = "{$nasPath}/{$destName}";

            copy($photoPath, $destFull);

            [$width, $height] = @getimagesize($photoPath) ?: [null, null];

            MediaItem::create([
                'album_id'          => $album->id,
                'uploaded_by'       => $admin->id,
                'file_name'         => basename($photoPath),
                'file_path'         => $destName,
                'thumb_path'        => null,
                'mime_type'         => mime_content_type($photoPath) ?: 'image/jpeg',
                'file_size_bytes'   => filesize($photoPath),
                'width_px'          => $width,
                'height_px'         => $height,
                'sha256_hash'       => $sha256,
                'is_duplicate'      => false,
                'processing_status' => 'done',
            ]);

            $count++;
        }

        $this->info("✓ {$count} photo(s) copiée(s)");
    }

    // ─────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────

    private function guessMime(string $ext): string
    {
        return match ($ext) {
            'pdf'  => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'odt'  => 'application/vnd.oasis.opendocument.text',
            'ods'  => 'application/vnd.oasis.opendocument.spreadsheet',
            'txt'  => 'text/plain',
            'jpg', 'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            default => 'application/octet-stream',
        };
    }
}
