<?php

namespace App\Services\Ged;

use App\Models\Tenant\GedWopiLock;

/**
 * Gestion des verrous WOPI pour Collabora Online.
 *
 * Implémente la sémantique de la spec WOPI :
 *   - Un seul verrou actif par document (UNIQUE sur document_id)
 *   - TTL configurable (défaut 30 min), rafraîchi par REFRESH_LOCK
 *   - Les verrous expirés sont purgés avant chaque opération
 *   - Conflit → status 'conflict' + lock_id courant pour X-WOPI-Lock
 *
 * Retour de chaque méthode :
 *   ['status' => 'ok',       'lock' => GedWopiLock]   succès
 *   ['status' => 'conflict', 'current_lock_id' => '']  conflit (current_lock_id vide = pas de verrou)
 */
class WopiLockService
{
    private int $ttlMinutes;

    public function __construct()
    {
        $this->ttlMinutes = (int) config('collabora.lock_ttl', 30);
    }

    // =========================================================================
    // Opérations WOPI
    // =========================================================================

    /**
     * LOCK — pose un verrou sur le document.
     *
     * - Pas de verrou actif    → crée le verrou, retourne ok
     * - Même lock_id          → conversion (refresh TTL), retourne ok
     * - lock_id différent     → conflit 409
     *
     * @return array{status: 'ok', lock: GedWopiLock}|array{status: 'conflict', current_lock_id: string}
     */
    public function lock(int $documentId, string $lockId, int $userId): array
    {
        $this->purgeExpired($documentId);

        $existing = $this->find($documentId);

        if ($existing === null) {
            $lock = GedWopiLock::create([
                'document_id' => $documentId,
                'lock_id' => $lockId,
                'expires_at' => now()->addMinutes($this->ttlMinutes),
                'locked_by' => $userId,
            ]);

            return ['status' => 'ok', 'lock' => $lock];
        }

        if ($existing->lock_id === $lockId) {
            // Même lock_id : conversion / prise de verrou — on rafraîchit le TTL
            $existing->update(['expires_at' => now()->addMinutes($this->ttlMinutes)]);

            return ['status' => 'ok', 'lock' => $existing];
        }

        return ['status' => 'conflict', 'current_lock_id' => $existing->lock_id];
    }

    /**
     * UNLOCK — libère le verrou si le lock_id correspond.
     *
     * - Verrou correspondant → supprime, retourne ok
     * - lock_id différent   → conflit 409
     * - Pas de verrou       → conflit 409 avec current_lock_id vide (spec WOPI)
     *
     * @return array{status: 'ok'}|array{status: 'conflict', current_lock_id: string}
     */
    public function unlock(int $documentId, string $lockId): array
    {
        $this->purgeExpired($documentId);

        $existing = $this->find($documentId);

        if ($existing === null) {
            return ['status' => 'conflict', 'current_lock_id' => ''];
        }

        if ($existing->lock_id !== $lockId) {
            return ['status' => 'conflict', 'current_lock_id' => $existing->lock_id];
        }

        $existing->delete();

        return ['status' => 'ok'];
    }

    /**
     * REFRESH_LOCK — prolonge le TTL d'un verrou existant.
     *
     * - Verrou correspondant → renouvelle expires_at, retourne ok
     * - lock_id différent   → conflit 409
     * - Pas de verrou       → conflit 409 avec current_lock_id vide
     *
     * @return array{status: 'ok', lock: GedWopiLock}|array{status: 'conflict', current_lock_id: string}
     */
    public function refreshLock(int $documentId, string $lockId): array
    {
        $this->purgeExpired($documentId);

        $existing = $this->find($documentId);

        if ($existing === null) {
            return ['status' => 'conflict', 'current_lock_id' => ''];
        }

        if ($existing->lock_id !== $lockId) {
            return ['status' => 'conflict', 'current_lock_id' => $existing->lock_id];
        }

        $existing->update(['expires_at' => now()->addMinutes($this->ttlMinutes)]);

        return ['status' => 'ok', 'lock' => $existing];
    }

    /**
     * GET_LOCK — retourne le verrou actif ou null.
     * Toujours 200 ; X-WOPI-Lock vide si aucun verrou.
     */
    public function getLock(int $documentId): ?GedWopiLock
    {
        $this->purgeExpired($documentId);

        return $this->find($documentId);
    }

    /**
     * Vérifie si le document est verrouillé par un lock_id différent.
     * Utilisé par PutFile pour bloquer l'écriture en cas de conflit.
     */
    public function isLockedByOther(int $documentId, string $lockId): bool
    {
        $lock = $this->getLock($documentId);

        return $lock !== null && $lock->lock_id !== $lockId;
    }

    // =========================================================================
    // Helpers privés
    // =========================================================================

    private function find(int $documentId): ?GedWopiLock
    {
        return GedWopiLock::where('document_id', $documentId)->first();
    }

    private function purgeExpired(int $documentId): void
    {
        GedWopiLock::where('document_id', $documentId)
            ->where('expires_at', '<', now())
            ->delete();
    }
}
