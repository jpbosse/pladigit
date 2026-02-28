<?php

namespace App\Services;

use App\Models\Tenant\TenantSettings;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\Crypt;
use LdapRecord\Auth\BindException;
use LdapRecord\Connection;

class LdapAuthService
{
    private function buildConnection(TenantSettings $settings): Connection
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
            'timeout' => 5,
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
            ->whereContains('member', $userDn)
            ->get();

        foreach ($groups as $group) {
            $cn = $group['cn'][0] ?? null;
            if ($cn && isset($roleMap[$cn])) {
                return $roleMap[$cn];
            }
        }

        return 'user';
    }

    public function syncUser(array $ldapEntry, ?Connection $conn = null, ?string $baseDn = null): User
    {
        $email = $ldapEntry['mail'][0] ?? null;
        if (! $email) {
            throw new \RuntimeException('L\'entrée LDAP ne possède pas d\'email.');
        }

        $role = 'user';
        if ($conn && $baseDn && isset($ldapEntry['dn'])) {
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

    public function authenticate(string $email, string $password): ?User
    {
        // Vérifier qu'un tenant est actif
        if (! app(\App\Services\TenantManager::class)->hasTenant()) {
            return null;
        }

        $settings = TenantSettings::sole();

        if (! $settings->ldap_host) {
            return null;
        }

        try {
            $conn = $this->buildConnection($settings);
            $conn->connect();

            $ldapUser = $conn->query()
                ->setDn($settings->ldap_base_dn)
                ->whereEquals('mail', $email)
                ->first();

            if (! $ldapUser) {
                return null;
            }

            $conn->auth()->attempt($ldapUser['dn'], $password, $bindAsUser = true);

            return $this->syncUser($ldapUser, $conn, $settings->ldap_base_dn);

        } catch (BindException) {
            return null;
        }
    }

    public function syncAllUsers(): void
    {
        if (! app(\App\Services\TenantManager::class)->hasTenant()) {
            return;
        }

        $settings = TenantSettings::sole();

        if (! $settings->ldap_host) {
            return;
        }

        $conn = $this->buildConnection($settings);
        $conn->connect();

        $ldapUsers = $conn->query()
            ->setDn($settings->ldap_base_dn)
            ->whereHas('mail')
            ->get();

        foreach ($ldapUsers as $ldapEntry) {
            $this->syncUser($ldapEntry, $conn, $settings->ldap_base_dn);
        }

        $activeLdapDns = array_column($ldapUsers, 'dn');
        User::whereNotNull('ldap_dn')
            ->whereNotIn('ldap_dn', $activeLdapDns)
            ->update(['status' => 'locked']);
    }
}
