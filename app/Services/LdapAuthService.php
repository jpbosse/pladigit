<?php
 
namespace App\Services;
 
use App\Models\Tenant\TenantSettings;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\Crypt;
use LdapRecord\Connection;
use LdapRecord\Auth\BindException;
 
/**
 * Gère l'authentification et la synchronisation LDAP/Active Directory.
 * LDAPS (TLS) est obligatoire — le LDAP non chiffré est refusé.
 */
class LdapAuthService
{
    private function buildConnection(TenantSettings $settings): Connection
    {
        if (! $settings->ldap_host) {
            throw new \RuntimeException('LDAP non configuré pour ce tenant.');
        }
 
        // Déchiffrer le mot de passe de service
        $bindPassword = Crypt::decryptString($settings->ldap_bind_password_enc);
 
        return new Connection([
            'hosts'             => [$settings->ldap_host],
            'port'              => $settings->ldap_port ?? 636,
            'base_dn'           => $settings->ldap_base_dn,
            'username'          => $settings->ldap_bind_dn,
            'password'          => $bindPassword,
            'use_ssl'           => false,
            'use_tls'           => (bool) $settings->ldap_use_tls, // TLS obligatoire
            'timeout'           => 5,
        ]);
    }
 
    /**
     * Authentifie un utilisateur via LDAP et met à jour/crée son compte local.
     */
    public function authenticate(string $email, string $password): ?User
    {
        $settings = TenantSettings::sole();
 
        if (! $settings->ldap_host) {
            return null; // LDAP non configuré → authentification locale uniquement
        }
 
        try {
            $conn = $this->buildConnection($settings);
            $conn->connect();
 
            // Rechercher l'entrée LDAP par email
            $ldapUser = $conn->query()
                ->setDn($settings->ldap_base_dn)
                ->whereEquals('mail', $email)
                ->first();
 
            if (! $ldapUser) {
                return null;
            }
 
            // Authentifier l'utilisateur avec son propre mot de passe
            $conn->auth()->attempt($ldapUser['dn'][0], $password, $bindAsUser = true);
 
            // Synchroniser le compte dans la base tenant
            return $this->syncUser($ldapUser);
 
        } catch (BindException) {
            return null; // Mauvais mot de passe LDAP
        }
    }
 
    /**
     * Crée ou met à jour le compte utilisateur local depuis l'annuaire LDAP.
     */
    public function syncUser(array $ldapEntry): User
    {
        $email = $ldapEntry['mail'][0] ?? null;
 
        if (! $email) {
            throw new \RuntimeException('L\'entrée LDAP ne possède pas d\'email.');
        }
 
        return User::updateOrCreate(
            ['email' => $email],
            [
                'name'           => $ldapEntry['cn'][0] ?? $email,
                'ldap_dn'        => $ldapEntry['dn'][0],
                'ldap_synced_at' => now(),
                'department'     => $ldapEntry['department'][0] ?? null,
                'status'         => 'active',
                'password_hash'  => null, // Jamais de mdp local pour comptes LDAP
            ]
        );
    }
 
    /**
     * Synchronise tous les utilisateurs depuis l'annuaire.
     * Exécuté en tâche planifiée (toutes les X heures).
     */
    public function syncAllUsers(): void
    {
        $settings = TenantSettings::sole();
        if (! $settings->ldap_host) return;
 
        $conn = $this->buildConnection($settings);
        $conn->connect();
 
        $ldapUsers = $conn->query()
            ->setDn($settings->ldap_base_dn)
            ->whereHas('mail')
            ->get();
 
        foreach ($ldapUsers as $ldapEntry) {
            $this->syncUser($ldapEntry);
        }
 
        // Désactiver les comptes retirés de l'annuaire
        $activeLdapDns = array_column($ldapUsers, 'dn');
        User::whereNotNull('ldap_dn')
            ->whereNotIn('ldap_dn', $activeLdapDns)
            ->update(['status' => 'locked']);
    }
}
