<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email envoyé à contact@pladigit.fr lors d'une demande de démo
 * soumise via le formulaire de la page d'accueil.
 */
class ContactRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $organization,
        public readonly string $email,
        public readonly string $plan,
        public readonly string $messageText,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Demande de démo — {$this->organization}",
            replyTo: [$this->email],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contact',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
