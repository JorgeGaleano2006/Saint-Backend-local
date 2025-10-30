<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB; // ✅ Importa la clase DB
use Illuminate\Support\Facades\Log;

class InconsistenciaSiesa extends Model
{
    protected $connection = 'siesa';
    protected $table = 't850_mf_op_docto';
    protected $primaryKey = 'f850_consec_docto';
    public $incrementing = false;
    public $timestamps = false;


    /**
     * consulta para obtener codigo orden de compra (OP,OPM,OPR) 
     */

    public static function obtenerCodigoOrden($tipoDeOrden)
    {

        return self::select([
            'f850_referencia_1 AS cliente',
            'f850_consec_docto AS id',
            'f850_consec_docto AS codigo',
            'f850_fecha_ts_creacion AS fecha_creacion',
            'f850_ind_estado AS estado',
            'f850_notas AS tipo_orden',
        ])
            ->where('f850_id_cia', '01')
            ->whereNotIn('f850_ind_estado', [9])
            ->where('f850_fecha_ts_creacion', '>=', DB::raw('DATEADD(YEAR, -2, GETDATE())'))
            ->where('f850_id_tipo_docto', $tipoDeOrden)
            ->orderByDesc('f850_fecha_ts_creacion')
            ->get();
    }


    /**
     * consulta para obtener pv de la orden de compra 
     */
    public static function obtenerPv($codigo)
    {
        return self::select([
            DB::raw("ISNULL(f850_notas, '') AS pvs"),
            'f850_consec_docto AS numero_op',
            'f850_fecha_ts_creacion AS fecha_op',
            'f850_ind_estado AS estado_op',
        ])
            ->where('f850_consec_docto', $codigo)
            ->get();
    }

    
}
