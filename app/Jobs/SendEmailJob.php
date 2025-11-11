<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $email;
    private $mailable;

    public function __construct($email, $mailable)
    {
        $this->email = $email;
        $this->mailable = $mailable;
    }

    public function handle()
{
    try {
        Log::info("SendEmailJob::handle() - enviando correo", [
            'email' => $this->email,
            'mailable' => get_class($this->mailable)
        ]);

        Mail::to($this->email)->send($this->mailable);

        Log::info("SendEmailJob::handle() - correo enviado correctamente");
    } catch (\Throwable $e) {
        Log::error("ERROR EN ENVÍO DE CORREO", [
            'error' => $e->getMessage(),
            'linea' => $e->getLine(),
            'archivo' => $e->getFile()
        ]);
    }
}

}
