<?php

namespace App\Mail;

use App\Models\Tenant\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email d'invitation envoyé à un nouvel utilisateur.
 *
 * Contient un lien d'activation avec le token brut (non hashé).
 * Le token brut n'est jamais stocké en base — seul son hash SHA-256 l'est.
 * TTL : 72 heures. Usage unique (invalidé à la première utilisation).
 */
class UserInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $token,        // Token brut (jamais stocké en base)
        public readonly string $activationUrl,
        public readonly string $orgName,
        public readonly string $invitedByName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Invitation à rejoindre {$this->orgName} — Pladigit",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invitation',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
