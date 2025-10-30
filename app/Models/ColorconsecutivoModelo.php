<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

class ColorconsecutivoModelo
{
    protected $connection = 'providencia_renueva_bigbag'; // conexión específica

    /**
     * Obtener los colores de precintos con su consecutivo actual
     */
    public function obtenerColorConsecutivo()
    {
        return DB::connection($this->connection)
            ->table('consecutivo_precintos')
            ->select('id_consecutivo', 'color', 'numero_actual')
            ->get();
    }

    /**
     * Actualizar el número actual de un color específico
     */
    public function actualizarNumeroActual($color, $nuevoNumero)
    {
        return DB::connection($this->connection)
            ->table('consecutivo_precintos')
            ->where('color', $color)
            ->update(['numero_actual' => $nuevoNumero]);
    }
}
