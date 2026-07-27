<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * The single email shape for every notification_logs `email` row —
 * transactional and marketing alike. Templates supply plain-text bodies
 * with placeholders already resolved by RenderNotificationTemplate before
 * this is constructed; this class only wraps that text for delivery.
 */
class NotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $mailSubject,
        public string $mailBody,
    ) {}

    public function build(): self
    {
        return $this->subject($this->mailSubject)->view('emails.notification');
    }
}
