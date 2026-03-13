<?php

namespace App\Services;

use App\Models\Tenant\TenantSettings;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use LdapRecord\Auth\BindException;
use LdapRecord\Connection;
use LdapRecord\LdapRecordException;

class LdapAuthService
{
    /**
     * Indique pourquoi LDAP n'a pas pu être utilisé lors du dernier appel.
     * Valeurs possibles : null | 'not_configured' | 'unavailable' | 'bind_failed' | 'user_not_found'
     */
    private ?string $lastFailureReason = null;

    public function getLastFailureReason(): ?string
    {
        return $this->lastFailureReason;
    }

    /**
     * Indique si l'échec est dû à une indisponibilité réseau/serveur
     * (et non à de mauvais identifiants). Utile pour le fallback local.
     */
    public function isUnavailable(): bool
    {
        return $this->lastFailureReason === 'unavailable';
    }

    protected function buildConnection(TenantSettings $settings): Connection
    {
        if (! $settings->ldap_host) {
            throw new \RuntimeException('LDAP non configuré pour ce tenant.');
        }

        $bindPassword = Crypt::decryptString($settings->ldap_bind_password_enc);

        return new Connection([
            'hosts' => [$settings->ldap_host],
            'port' => $settings->ldap_port ?? 636,
            'base_dn' => $settings->ldap_base_dn,
            'username' => $settings->ldap_bind_dn,
            'password' => $bindPassword,
            'use_ssl' => (bool) $settings->ldap_use_ssl,
            'use_tls' => (bool) $settings->ldap_use_tls,
            'timeout' => 3,
        ]);
    }

    /**
     * Récupère le rôle depuis les groupes LDAP de l'utilisateur.
     */
    private function resolveRole(Connection $conn, string $userDn, string $baseDn): string
    {
        $roleMap = [
            'admin' => 'admin',
            'president' => 'president',
            'dgs' => 'dgs',
            'resp_direction' => 'resp_direction',
            'resp_service' => 'resp_service',
            'user' => 'user',
        ];

        $groups = $conn->query()
            ->setDn('ou=groups,'.$baseDn)
            ->whereEquals('objectClass', 'groupOfNames')
            ->whereEquals('member', $userDn)
            ->get();

        foreach ($groups as $group) {
            $cn = $group['cn'][0] ?? null;
            if ($cn && isset($roleMap[$cn])) {
                return $roleMap[$cn];
            }
        }

        return 'user';
    }

    public function syncUser(array $ldapEntry, ?string $preResolvedRole = null, ?Connection $conn = null, ?string $baseDn = null): User
    {
        $email = $ldapEntry['mail'][0] ?? null;
        if (! $email) {
            throw new \RuntimeException("L'entrée LDAP ne possède pas d'email.");
        }

        // Priorité : rôle pré-résolu (bound admin) > résolution à la volée > défaut user
        $role = $preResolvedRole ?? 'user';
        if ($preResolvedRole === null && $conn && $baseDn && isset($ldapEntry['dn'])) {
            $role = $this->resolveRole($conn, $ldapEntry['dn'], $baseDn);
        }

        return User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $ldapEntry['cn'][0] ?? $email,
                'ldap_dn' => $ldapEntry['dn'] ?? null,
                'ldap_synced_at' => now(),
                'status' => 'active',
                'password_hash' => null,
                'role' => $role,
            ]
        );
    }

    /**
     * Tente l'authentification LDAP.
     *
     * Retourne l'utilisateur synchronisé en cas de succès.
     * Retourne null dans tous les cas d'échec.
     *
     * Consultez getLastFailureReason() pour distinguer :
     *   - 'not_configured' : LDAP absent des settings tenant => ignorer, fallback local normal
     *   - 'unavailable'    : serveur LDAP injoignable (timeout, réseau, dev sans OpenLDAP)
     *                        => fallback local autorisé, warning loggé
     *   - 'bind_failed'    : mauvais mot de passe LDAP => NE PAS fallback sur local
     *   - 'user_not_found' : email inconnu dans l'annuaire => NE PAS fallback sur local
     */
    public function authenticate(string $email, string $password): ?User
    {
        $this->lastFailureReason = null;

        if (! app(TenantManager::class)->hasTenant()) {
            $this->lastFailureReason = 'not_configured';

            return null;
        }

        $settings = TenantSettings::firstOrCreate([]);

        if (! $settings->ldap_host) {
            $this->lastFailureReason = 'not_configured';

            return null;
        }

        // ── Étape 1 : connexion + bind admin ──────────────────────────
        // Toute exception ici = serveur inaccessible (pas de mauvais identifiants utilisateur).
        try {
            $conn = $this->buildConnection($settings);
            $conn->connect();
        } catch (BindException|LdapRecordException|\Exception $e) {
            $this->lastFailureReason = 'unavailable';
            Log::warning('LDAP indisponible — fallback sur authentification locale.', [
                'host' => $settings->ldap_host ?? 'non défini',
                'email' => $email,
                'exception' => $e->getMessage(),
            ]);

            return null;
        }

        // ── Étape 2 : recherche utilisateur + vérification mot de passe ──
        try {
            $ldapUser = $conn->query()
                ->setDn($settings->ldap_base_dn)
                ->whereEquals('mail', $email)
                ->first();

            if (! $ldapUser) {
                $this->lastFailureReason = 'user_not_found';

                return null;
            }

            // Résolution du rôle pendant que la connexion est encore bound en admin.
            // attempt(bindAsUser: true) ci-dessous rebind en tant qu'utilisateur,
            // qui n'a généralement pas les droits de lecture sur ou=groups.
            $role = $this->resolveRole($conn, $ldapUser['dn'], $settings->ldap_base_dn);

            // Vérifie le mot de passe — retourne false si incorrect (pas d'exception)
            $bound = $conn->auth()->attempt($ldapUser['dn'], $password, true);

            if (! $bound) {
                $this->lastFailureReason = 'bind_failed';

                return null;
            }

            return $this->syncUser($ldapUser, $role);

        } catch (BindException) {
            // Mauvais mot de passe LDAP — ne pas fallback sur local
            $this->lastFailureReason = 'bind_failed';

            return null;

        } catch (LdapRecordException|\Exception $e) {
            $this->lastFailureReason = 'unavailable';
            Log::warning('LDAP indisponible — fallback sur authentification locale.', [
                'host' => $settings->ldap_host ?? 'non défini',
                'email' => $email,
                'exception' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function syncAllUsers(): void
    {
        if (! app(TenantManager::class)->hasTenant()) {
            return;
        }

        $settings = TenantSettings::firstOrCreate([]);

        if (! $settings->ldap_host) {
            return;
        }

        try {
            $conn = $this->buildConnection($settings);
            $conn->connect();
        } catch (LdapRecordException|\Exception $e) {
            Log::error('Impossible de synchroniser les utilisateurs LDAP : serveur inaccessible.', [
                'host' => $settings->ldap_host,
                'exception' => $e->getMessage(),
            ]);

            return;
        }

        // La requête est dans son propre try/catch : une query qui échoue
        // ne doit jamais déclencher la désactivation des comptes existants.
        try {
            $ldapUsers = $conn->query()
                ->setDn($settings->ldap_base_dn)
                ->whereHas('mail')
                ->get();
        } catch (LdapRecordException|\Exception $e) {
            Log::error('LDAP sync annulée : échec de la requête (résultat non fiable).', [
                'host' => $settings->ldap_host,
                'exception' => $e->getMessage(),
            ]);

            return;
        }

        // Circuit breaker : éviter la désactivation masse sur timeout partiel.
        $knownLdapCount = User::whereNotNull('ldap_dn')->count();

        // Règle 1 : résultat vide alors que des comptes LDAP existent en base.
        if (count($ldapUsers) === 0 && $knownLdapCount > 0) {
            Log::warning('LDAP sync interrompue : 0 utilisateurs retournés alors que '
                ."{$knownLdapCount} comptes LDAP existent. Désactivation masse bloquée.", [
                    'host' => $settings->ldap_host,
                ]);

            return;
        }

        // Règle 2 : plus de 50% des comptes connus seraient verrouillés.
        if ($knownLdapCount > 0) {
            $activeDns = array_filter(array_column($ldapUsers, 'dn'));
            $wouldBeLocked = User::whereNotNull('ldap_dn')
                ->whereNotIn('ldap_dn', $activeDns)
                ->count();

            if (($wouldBeLocked / $knownLdapCount) > 0.5) {
                Log::warning('LDAP sync interrompue : '.$wouldBeLocked.'/'.$knownLdapCount
                    .' comptes seraient verrouillés ('.round($wouldBeLocked / $knownLdapCount * 100)
                    .'%). Seuil 50% dépassé — désactivation masse bloquée.', [
                        'host' => $settings->ldap_host,
                    ]);

                return;
            }
        }

        foreach ($ldapUsers as $ldapEntry) {
            $this->syncUser($ldapEntry, null, $conn, $settings->ldap_base_dn);
        }

        $activeLdapDns = array_filter(array_column($ldapUsers, 'dn'));
        $locked = User::whereNotNull('ldap_dn')
            ->whereNotIn('ldap_dn', $activeLdapDns)
            ->update(['status' => 'locked']);

        Log::info('LDAP sync terminée.', [
            'host' => $settings->ldap_host,
            'synced' => count($ldapUsers),
            'locked' => $locked,
        ]);
    }
}
