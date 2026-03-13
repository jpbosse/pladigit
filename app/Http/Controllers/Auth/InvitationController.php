<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\InvitationService;
use App\Services\PasswordPolicyService;
use Illuminate\Http\Request;

/**
 * Gère l'activation d'un compte via le lien d'invitation reçu par email.
 *
 * GET  /invitation/{token}  → formulaire de choix de mot de passe
 * POST /invitation/{token}  → activation du compte + invalidation du token
 */
class InvitationController extends Controller
{
    public function __construct(
        private InvitationService $invitation,
        private PasswordPolicyService $policy,
        private AuditService $audit,
    ) {}

    /**
     * Affiche le formulaire d'activation.
     * Vérifie la validité du token avant d'afficher le formulaire.
     */
    public function show(string $token)
    {
        $user = $this->invitation->findValidUser($token);

        if (! $user) {
            $expired = $this->invitation->isExpired($token);

            return view('auth.invitation-invalid', [
                'reason' => $expired ? 'expired' : 'invalid',
            ]);
        }

        return view('auth.invitation-accept', [
            'token' => $token,
            'user' => $user,
        ]);
    }

    /**
     * Active le compte : valide le MDP, consomme le token, connecte l'utilisateur.
     */
    public function accept(Request $request, string $token)
    {
        $user = $this->invitation->findValidUser($token);

        if (! $user) {
            return redirect()->route('invitation.show', $token)
                ->withErrors(['token' => 'Ce lien d\'activation n\'est plus valide.']);
        }

        $request->validate([
            'password' => ['required', 'string', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ]);

        // Valider la politique de mots de passe du tenant
        $policyErrors = $this->policy->validate($request->password);
        if (! empty($policyErrors)) {
            return back()
                ->withErrors(['password' => $policyErrors])
                ->with('token', $token);
        }

        // Consommer le token AVANT d'écrire le mot de passe
        // → usage unique garanti même en cas de double-soumission
        $this->invitation->consume($user);

        // Définir le mot de passe
        $this->policy->updatePassword($user, $request->password);

        $this->audit->log('user.invitation_accepted', $user, [
            'model_type' => get_class($user),
            'model_id' => $user->id,
        ]);

        // Connecter l'utilisateur directement
        auth()->login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')
            ->with('success', 'Votre compte est activé. Bienvenue sur Pladigit !');
    }
}
