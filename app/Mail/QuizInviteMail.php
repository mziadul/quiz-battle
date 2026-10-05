<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QuizInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $sender,
        public string $appDownloadUrl
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->sender->name . ' has challenged you to a Quiz Battle!',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: "
                <h2>You have been challenged on QuizBattle!</h2>
                <p>Hi, <strong>{$this->sender->name}</strong> wants to play a quiz battle with you.</p>
                <p>Download the app to accept the challenge:</p>
                <a href='{$this->appDownloadUrl}' style='padding: 10px 20px; background: #4f46e5; color: white; text-decoration: none; border-radius: 5px; display: inline-block;'>Download App & Accept Challenge</a>
            "
        );
    }
}