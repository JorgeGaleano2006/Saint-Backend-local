<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Exception;

class RecepcionesBigbagModelo extends Model
{
    protected $table = 'reporte_llegada_empaques';
    protected $connection = 'providencia_renueva_bigbag'; 
    public $timestamps = false;

    public function obtenerDatos()
    {
        return DB::connection('providencia_renueva_bigbag') 
            ->table($this->table)
            ->orderBy('id_reporte_llegada_empaque', 'desc')
            ->get();
    }

    public function actualizarCantidades($numRecepcion, $cantRelacionada, $cantidadFisico, $diferenciaReportada, $usuarioId, $firstName, $lastName, $justificacion)
    {
        try {
            return DB::connection('providencia_renueva_bigbag')->transaction(function () use (
                $numRecepcion,
                $cantRelacionada,
                $cantidadFisico,
                $diferenciaReportada,
                $usuarioId,
                $firstName,
                $lastName,
                $justificacion
            ) {
                // Paso 1: Validar que num_recepcion no esté vacío
                if (empty($numRecepcion)) {
                    throw new Exception("num_recepcion no puede estar vacío");
                }

                // Paso 2: Obtener datos actuales
                $datosAnteriores = DB::connection('providencia_renueva_bigbag')
                    ->table($this->table)
                    ->select('cant_relacionada', 'cantidad_fisico', 'diferencia_reportada')
                    ->where('num_recepcion', $numRecepcion)
                    ->first();

                if (!$datosAnteriores) {
                    throw new Exception("No se encontró el registro con num_recepcion: " . $numRecepcion);
                }

                // Paso 3: Actualizar datos principales
                DB::connection('providencia_renueva_bigbag')
                    ->table($this->table)
                    ->where('num_recepcion', $numRecepcion)
                    ->update([
                        'cant_relacionada' => floatval($cantRelacionada),
                        'cantidad_fisico' => floatval($cantidadFisico),
                        'diferencia_reportada' => strval($diferenciaReportada),
                    ]);

                // Paso 4: Insertar en recepciones_log
                $idLog = DB::connection('providencia_renueva_bigbag')
                    ->table('recepciones_log')
                    ->insertGetId([
                        'user_id' => $usuarioId,
                        'num_recepcion' => $numRecepcion,
                        'accion' => 'Editó',
                        'fecha_accion' => now(),
                        'firts_name' => $firstName,
                        'last_name' => $lastName,
                        'comentario' => $justificacion
                    ]);

                // Paso 5: Obtener último comentario anterior
                $oldComentario = DB::connection('providencia_renueva_bigbag')
                    ->table('recepciones_log')
                    ->where('num_recepcion', $numRecepcion)
                    ->orderBy('fecha_accion', 'desc')
                    ->value('comentario') ?? 'Sin comentario anterior';

                // Paso 6: Insertar versión anterior y nuevos datos
                DB::connection('providencia_renueva_bigbag')
                    ->table('versiones_recepcion')
                    ->insert([
                        'id_log' => $idLog,
                        'old_cantidad_relacional' => $datosAnteriores->cant_relacionada,
                        'old_cantidad_fisico' => $datosAnteriores->cantidad_fisico,
                        'old_diferencia_reportada' => $datosAnteriores->diferencia_reportada,
                        'num_recepcion' => $numRecepcion,
                        'comentario' => $justificacion,
                        'new_cantidad_fisico' => floatval($cantidadFisico),
                        'new_cantidad_relacional' => floatval($cantRelacionada),
                        'new_diferencia_reportada' => strval($diferenciaReportada),
                        'responsable_edicion' => trim($firstName . ' ' . $lastName)
                    ]);

                return true;
            });
        } catch (Exception $e) {
            report($e);
            return false;
        }
    }
}
