<?php

namespace App\Services;

use App\Models\Tenant\AuditLog;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\Request;

/**
 * Enregistre toutes les actions dans le journal immuable audit_logs.
 * À appeler après chaque opération sensible.
 */
class AuditService
{
    /**
     * @param  string  $action  Ex: 'user.login', 'document.create'
     * @param  User|null  $user  Utilisateur concerné
     * @param  array  $extra  Données additionnelles (old/new values)
     */
    public function log(string $action, ?User $user = null, array $extra = []): void
    {
        AuditLog::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name, // Dénormalisé intentionnellement
            'action' => $action,
            'model_type' => $extra['model_type'] ?? null,
            'model_id' => $extra['model_id'] ?? null,
            'old_values' => isset($extra['old']) ? json_encode($extra['old']) : null,
            'new_values' => isset($extra['new']) ? json_encode($extra['new']) : null,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }
}
