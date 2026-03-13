<?php

namespace App\Services;

use App\Models\Platform\Organization;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;

/**
 * Configure dynamiquement le mailer Laravel avec les paramètres SMTP du tenant.
 * Priorité : SMTP tenant > fallback .env
 */
class TenantMailer
{
    public function configureForTenant(Organization $org): void
    {
        if (! $this->isConfigured($org)) {
            return;
        }

        $password = null;
        if ($org->smtp_password_enc) {
            try {
                $password = Crypt::decryptString($org->smtp_password_enc);
            } catch (\Throwable) {
                return;
            }
        }

        Config::set('mail.mailers.smtp', [
            'transport' => 'smtp',
            'host' => $org->smtp_host,
            'port' => $org->smtp_port ?? 587,
            'scheme' => $this->resolveScheme($org),
            'username' => $org->smtp_user,
            'password' => $password,
            'timeout' => 10,
        ]);

        Config::set('mail.from', [
            'address' => $org->smtp_from_address ?: config('mail.from.address'),
            'name' => $org->smtp_from_name ?: config('mail.from.name'),
        ]);

        Mail::forgetMailers();
    }

    public function isConfigured(Organization $org): bool
    {
        return ! empty($org->smtp_host)
            && ! empty($org->smtp_user)
            && ! empty($org->smtp_password_enc);
    }

    private function resolveScheme(Organization $org): string
    {
        return match ($org->smtp_encryption ?? 'tls') {
            'smtps', 'ssl' => 'smtps',
            default => 'smtp',
        };
    }
}
