<?php

namespace App\Services;

use App\Jobs\SendEmailJob;
use Illuminate\Support\Facades\Log;

class EmailService
{
    public static function send($destinatario, $mailable)
    {
        Log::info("EmailService::send() iniciado", [
            'destinatario' => $destinatario,
            'mailable' => get_class($mailable)
        ]);

        SendEmailJob::dispatch($destinatario, $mailable);

        Log::info("EmailService::send() dispatch ejecutado correctamente");
    }

    public static function sendAprobacionInconsistencia($inconsistencia)
    {
        $destinatario = $inconsistencia->usuario->email ?? null;

        Log::info("EmailService::sendAprobacionInconsistencia()", [
            'destinatario' => $destinatario,
            'id_inconsistencia' => $inconsistencia->id ?? null
        ]);

        if (!$destinatario) {
            Log::error("ERROR: La inconsistencia no tiene usuario o correo asignado.");
            return;
        }

        self::send($destinatario, new \App\Mail\AprobacionInconsistenciaMail($inconsistencia));
    }
}
