<?php

namespace App\Mail;

use App\Models\Platform\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SslFailedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Organization $organization,
        public readonly string $domain,
        public readonly string $errorDetails,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '⚠️ Pladigit — Échec activation HTTPS pour '.$this->organization->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ssl-failed',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
