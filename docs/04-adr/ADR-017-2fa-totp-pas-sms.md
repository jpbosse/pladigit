# ADR-012 — 2FA par TOTP (pas SMS)

**Date :** Décembre 2025
**Statut :** Accepté

## Contexte

Les organisations du secteur public ont une obligation croissante de sécuriser l'accès à leurs systèmes d'information. Un second facteur d'authentification est requis. Deux approches courantes existent : SMS (OTP envoyé par message) et TOTP (code généré par une application).

## Décision

Utiliser **TOTP (Time-based One-Time Password)** via l'algorithme Google Authenticator (compatible Authy, Microsoft Authenticator, etc.). Le secret TOTP est chiffré en base (`totp_secret_enc`) via `Crypt::encryptString()`. Un code de secours chiffré (`totp_backup_code_enc`) permet de récupérer l'accès si l'appareil est perdu.

L'administrateur peut rendre le 2FA obligatoire pour toute l'organisation (`TenantSettings.force_2fa`).

## Alternatives écartées

- **SMS :** nécessite un fournisseur tiers payant (Twilio, OVH SMS), dépendance réseau mobile, vulnérable au SIM-swapping. Hors de portée pour des petites collectivités sans budget IT.
- **WebAuthn/FIDO2 :** plus sécurisé mais demande du matériel (clé physique) ou un navigateur récent avec capteur biométrique — barrière trop haute pour les profils agents.

## Conséquences

- Fonctionne hors ligne (l'application d'authentification génère les codes sans connexion).
- Pas de coût supplémentaire.
- L'utilisateur doit télécharger une application TOTP à l'activation — légère friction à l'onboarding.
- En cas de perte de l'appareil ET du code de secours, la réinitialisation doit passer par l'administrateur tenant.
