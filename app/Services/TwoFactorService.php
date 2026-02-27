<?php
 
namespace App\Services;
 
use App\Models\Tenant\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
 
/**
 * Gestion de la double authentification TOTP.
 * Le secret est chiffré en AES-256 avant stockage.
 */
class TwoFactorService
{
    public function __construct(private Google2FA $google2fa) {}
 
    /**
     * Génère un nouveau secret TOTP et retourne le QR Code SVG.
     */
    public function generateSetup(User $user): array
    {
        $secret = $this->google2fa->generateSecretKey(32);
 
        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            company: config('app.name'),
            holder:  $user->email,
            secret:  $secret
        );
 
        // Générer le QR Code SVG
        $writer = new Writer(
            new ImageRenderer(
                new RendererStyle(200),
                new SvgImageBackEnd()
            )
        );
        $qrCodeSvg = $writer->writeString($qrCodeUrl);
 
        return [
            'secret'    => $secret,
            'qr_code'   => $qrCodeSvg,
            'qr_url'    => $qrCodeUrl,
        ];
    }
 
    /**
     * Active le 2FA après vérification du premier code TOTP.
     * Génère également les codes de secours.
     */
    public function enable(User $user, string $plainSecret, string $code): bool
    {
        if (! $this->google2fa->verifyKey($plainSecret, $code,4)) {
            return false;
        }
 
        $backupCodes = $this->generateBackupCodes();
 
        $user->update([
            'totp_secret_enc'      => Crypt::encryptString($plainSecret),
            'totp_enabled'         => true,
            'totp_backup_code_enc' => Crypt::encryptString(
                json_encode(array_map('hash', array_fill(0, count($backupCodes), 'sha256'), $backupCodes))
            ),
        ]);
 
        return true;
    }
 
    /**
     * Vérifie un code TOTP ou un code de secours.
     */

public function verify(User $user, string $code): bool
{
    \Log::info('TwoFactor::verify', [
        'user_id'    => $user->id,
        'enabled'    => $user->totp_enabled,
        'has_secret' => !empty($user->totp_secret_enc),
        'code'       => $code,
    ]);

    if (! $user->totp_enabled || ! $user->totp_secret_enc) {
        \Log::error('2FA — secret manquant');
        return false;
    }

    $plainSecret = Crypt::decryptString($user->totp_secret_enc);
    $valid = $this->google2fa->verifyKey($plainSecret, $code, 4);
    
    \Log::info('2FA result', ['secret_len' => strlen($plainSecret), 'valid' => $valid]);

    if ($valid) return true;

    return $this->verifyBackupCode($user, $code);
}


    /**
     * Vérifie et consomme un code de secours (usage unique).
     */
    private function verifyBackupCode(User $user, string $code): bool
    {
        if (! $user->totp_backup_code_enc) return false;
 
        $hashedCodes = json_decode(
            Crypt::decryptString($user->totp_backup_code_enc), true
        );
 
        $inputHash = hash('sha256', $code);
        $key = array_search($inputHash, $hashedCodes, true);
 
        if ($key === false) return false;
 
        // Supprimer le code utilisé
        unset($hashedCodes[$key]);
        $user->update([
            'totp_backup_code_enc' => Crypt::encryptString(
                json_encode(array_values($hashedCodes))
            ),
        ]);
 
        return true;
    }
 
    /**
     * Désactive le 2FA (après confirmation du mot de passe actuel).
     */
    public function disable(User $user): void
    {
        $user->update([
            'totp_secret_enc'      => null,
            'totp_enabled'         => false,
            'totp_backup_code_enc' => null,
        ]);
    }
 
    private function generateBackupCodes(int $count = 8): array
    {
        return array_map(fn() => strtoupper(Str::random(8)), range(1, $count));
    }
}
