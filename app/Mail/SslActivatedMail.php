<?php

namespace App\Mail;

use App\Models\Platform\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SslActivatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Organization $organization,
        public readonly string $domain,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '✅ Pladigit — HTTPS activé pour '.$this->organization->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ssl-activated',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
