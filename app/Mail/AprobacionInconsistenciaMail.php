<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AprobacionInconsistenciaMail extends Mailable
{
    use Queueable, SerializesModels;

    public $inconsistencia;

    public function __construct($inconsistencia)
    {
        $this->inconsistencia = $inconsistencia;
    }

    public function build()
    {
        return $this->subject("Inconsistencia aprobada")
                    ->view('emails.aprobacion_inconsistencia');
    }
}
