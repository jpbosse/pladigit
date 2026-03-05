<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Lien de partage temporaire vers un album.
 *
 * Relations :
 *   $link->album()    → MediaAlbum partagé
 *   $link->creator()  → User qui a créé le lien
 *
 * Helpers :
 *   $link->isExpired()   → true si la date d'expiration est dépassée
 *   $link->isValid()     → true si actif et non expiré
 *   MediaShareLink::generate($album, $user)  → crée un nouveau lien signé
 */
class MediaShareLink extends Model
{
    use HasFactory;

    protected $connection = 'tenant';

    protected $fillable = [
        'album_id',
        'created_by',
        'token',
        'expires_at',
        'allow_download',
        'password_hash',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'allow_download' => 'boolean',
    ];

    // ── Relations ────────────────────────────────────────────

    /**
     * Album partagé par ce lien.
     *
     * @return BelongsTo<MediaAlbum, $this>
     */
    public function album(): BelongsTo
    {
        return $this->belongsTo(MediaAlbum::class, 'album_id');
    }

    /**
     * Utilisateur créateur du lien.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Scopes ───────────────────────────────────────────────

    /**
     * Liens encore valides (non expirés).
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    // ── Helpers ──────────────────────────────────────────────

    /**
     * Vérifie si le lien est expiré.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Vérifie si le lien est utilisable.
     */
    public function isValid(): bool
    {
        return ! $this->isExpired();
    }

    /**
     * Vérifie un mot de passe optionnel.
     */
    public function checkPassword(string $password): bool
    {
        if ($this->password_hash === null) {
            return true; // Pas de mot de passe requis
        }

        return password_verify($password, $this->password_hash);
    }

    // ── Factory ──────────────────────────────────────────────

    /**
     * Génère un nouveau lien de partage signé.
     *
     * @param  int|null  $expiresInDays  null = pas d'expiration
     * @param  string|null  $password  null = pas de mot de passe
     */
    public static function generate(
        MediaAlbum $album,
        User $user,
        bool $allowDownload = true,
        ?int $expiresInDays = 7,
        ?string $password = null
    ): self {
        return self::create([
            'album_id' => $album->id,
            'created_by' => $user->id,
            'token' => Str::random(64),
            'expires_at' => $expiresInDays ? now()->addDays($expiresInDays) : null,
            'allow_download' => $allowDownload,
            'password_hash' => $password ? password_hash($password, PASSWORD_BCRYPT) : null,
        ]);
    }
}
