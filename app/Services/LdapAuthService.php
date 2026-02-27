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
            'use_ssl' => false,
            'use_tls' => (bool) $settings->ldap_use_tls,
            'timeout' => 5,
        ]);
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

            return $this->syncUser($ldapUser);

        } catch (BindException) {
            return null;
        }
    }

    public function syncUser(array $ldapEntry): User
    {
        $email = $ldapEntry['mail'][0] ?? null;

        if (! $email) {
            throw new \RuntimeException('L\'entrée LDAP ne possède pas d\'email.');
        }

        return User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $ldapEntry['cn'][0] ?? $email,
                'ldap_dn' => $ldapEntry['dn'] ?? null,
                'ldap_synced_at' => now(),
                'status' => 'active',
                'password_hash' => null,
            ]
        );
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
            $this->syncUser($ldapEntry);
        }

        $activeLdapDns = array_column($ldapUsers, 'dn');
        User::whereNotNull('ldap_dn')
            ->whereNotIn('ldap_dn', $activeLdapDns)
            ->update(['status' => 'locked']);
    }
}
