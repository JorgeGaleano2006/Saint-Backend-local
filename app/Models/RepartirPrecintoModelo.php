<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Exception;

class RepartirPrecintoModelo
{
    protected $connection = 'providencia_renueva_bigbag'; // 👈 Conexión a la base de datos

    public function guardarPrecinto($data)
    {
        try {
            $id = DB::connection($this->connection)
                ->table('entrega_precintos_bigbag')
                ->insertGetId([
                    'id_reporte_llegada' => $data['id_reporte_llegada'],
                    'fecha_entrega'      => $data['fechaEntrega'],
                    'area_precinto'      => $data['area'],
                    'nombre_responsable' => $data['nombre'],
                    'cedula'             => $data['cedula'],
                    'cantidad'           => $data['cantidad'],
                    'numero_precinto'    => $data['numeroPrecinto'],
                    'rango_precintos'    => $data['rango'],
                    'color_consecutivo'  => $data['color_consecutivo'], 
                    'observaciones'      => $data['observaciones'],
                    'id_responsable'     => $data['id_operario'],
                ]);

            return [
                'success' => true,
                'mensaje' => 'Precinto guardado correctamente',
                'id'      => $id
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'mensaje' => 'Error al guardar el precinto',
                'error'   => $e->getMessage()
            ];
        }
    }

    public function obtenerPrecintosPorReporte($id_reporte)
    {
        try {
            $precintos = DB::connection($this->connection)
                ->table('entrega_precintos_bigbag')
                ->where('id_reporte_llegada', $id_reporte)
                ->orderBy('id', 'DESC')
                ->get();

            return [
                'success' => true,
                'data'    => $precintos
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'mensaje' => 'Error al obtener los precintos',
                'error'   => $e->getMessage()
            ];
        }
    }
}
