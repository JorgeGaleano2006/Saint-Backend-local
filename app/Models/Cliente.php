<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Cliente extends Model
{
    protected $connection = 'siesa';   // Conexión a la BD de Siesa
    protected $table = 't200_mm_terceros'; // Tabla principal
    protected $primaryKey = 'f200_rowid';
    public $timestamps = false;

    /**
     * Buscar clientes por palabra clave en nombre o NIT
     */
    public static function buscarPorPalabra($word)
    {
        return DB::connection('siesa')
            ->table('t200_mm_terceros AS t200')
            ->distinct()
            ->select([
                't200.f200_rowid AS id',
                't200.f200_nit AS nit',
                't200.f200_razon_social AS razon_social',
            ])
            ->join('t201_mm_clientes AS t201', 't200.f200_rowid', '=', 't201.f201_rowid_tercero')
            ->where('t200.f200_ind_estado', 1)
            ->where(function ($query) use ($word) {
                $query->where('t200.f200_razon_social', 'LIKE', '%' . $word . '%')
                      ->orWhere('t200.f200_nit', 'LIKE', '%' . $word . '%');
            })
            ->get();
    }
}
