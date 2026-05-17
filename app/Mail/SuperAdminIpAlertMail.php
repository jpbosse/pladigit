<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Alerte email envoyée au Super Admin lorsqu'une tentative de connexion
 * est détectée depuis une adresse IP non autorisée.
 *
 * Envoi synchrone (pas de queue) pour garantir la réception immédiate,
 * même si les workers sont compromis ou arrêtés.
 */
class SuperAdminIpAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $ip,
        public readonly string $userAgent,
        public readonly string $url,
        public readonly string $detectedAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '⚠️ Pladigit — Tentative d\'accès Super Admin depuis IP non autorisée',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.super-admin-ip-alert',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
