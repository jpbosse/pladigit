<?php

namespace App\Mail;

use App\Models\Platform\Organization;
use App\Models\Tenant\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SslRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Organization $organization,
        public readonly User $requestedBy,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '🔒 Pladigit — '.$this->organization->name.' demande l\'activation HTTPS',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ssl-request',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
