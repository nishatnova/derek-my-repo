<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactUsMail extends Mailable
{
    use Queueable, SerializesModels;

    public $contact;

    public function __construct($contact)
    {
        $this->contact = $contact;
    }

    public function build()
    {
        return $this->subject('New Contact Form Submission')
            ->view('emails.contact_us')
            ->with([
                        'contactName' => $this->contact->name,
                        'contactEmail' => $this->contact->email,
                        'contactSubject' => $this->contact->subject,
                        'contactMessage' => $this->contact->message,
                        'createdAt' => $this->contact->created_at,
                    ]);
    }
}
