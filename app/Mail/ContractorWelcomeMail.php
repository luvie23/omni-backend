<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContractorWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $contractor;

    /**
     * Create a new message instance.
     */
    public function __construct(array $contractor)
    {
        $this->contractor = $contractor;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Welcome to Your Contractor Account')
                    ->markdown('emails.contractor-welcome');
    }
}
