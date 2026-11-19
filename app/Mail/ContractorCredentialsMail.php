<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContractorCredentialsMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $contractorName,
        public string $contractorEmail,
        public string $initialPassword,
        public string $certificateNumber
    ) {}

    public function build()
    {
        return $this
            ->subject('Your OMNI RGB Contractor Portal Access')
            ->view('emails.contractor-credentials');
    }
}
