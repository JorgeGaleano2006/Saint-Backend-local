<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB; // ✅ Importa la clase DB
use Illuminate\Support\Facades\Log;

class InconsistenciaSiesa2 extends Model
{
    protected $connection = 'siesa';
    protected $table = 't431_cm_pv_movto';
    protected $primaryKey = 'f431_rowid';
    public $incrementing = false;
    public $timestamps = false;


  
 public static function obtenerItemsPorPVsYCliente($numerosPV, $cliente)
{
    // Si no hay PVs o cliente, retornar colección vacía
    if (empty($numerosPV) || empty($cliente)) {
        return collect([]);
    }

    // Convertir a array si es necesario
    if (!is_array($numerosPV)) {
        $numerosPV = [$numerosPV];
    }

    return self::from('t431_cm_pv_movto as mov')
        ->join('t121_mc_items_extensiones as ext', 'ext.f121_rowid', '=', 'mov.f431_rowid_item_ext')
        ->join('t120_mc_items as i', 'i.f120_rowid', '=', 'ext.f121_rowid_item')
        ->leftJoin('t117_mc_extensiones1_detalle as col_det', function($join) {
            $join->on('col_det.f117_id', '=', 'ext.f121_id_ext1_detalle')
                 ->on('col_det.f117_id_extension1', '=', 'ext.f121_id_extension1');
        })
        ->leftJoin('t119_mc_extensiones2_detalle as tal_det', function($join) {
            $join->on('tal_det.f119_id', '=', 'ext.f121_id_ext2_detalle')
                 ->on('tal_det.f119_id_extension2', '=', 'ext.f121_id_extension2');
        })
        ->join('t430_cm_pv_docto as cab', 'cab.f430_rowid', '=', 'mov.f431_rowid_pv_docto')
        ->join('t200_mm_terceros as cli', 'cli.f200_rowid', '=', 'cab.f430_rowid_tercero_fact')
        ->leftJoin('t054_mm_estados as est', function($join) {
            $join->on('est.f054_id', '=', 'cab.f430_ind_estado')
                 ->on('est.f054_id_grupo_clase_docto', '=', 'cab.f430_id_grupo_clase_docto');
        })
        ->select([
            'mov.f431_rowid_pv_docto as numero_pv',
            'i.f120_id',
            'i.f120_referencia as referencia',
            'i.f120_descripcion_corta as descripcion_corta',
            'i.f120_descripcion as descripcion',
            'mov.f431_rowid_item_ext',
            'col_det.f117_id as id_color',
            'col_det.f117_descripcion as color',
            'tal_det.f119_id as id_talla',
            'tal_det.f119_descripcion as talla',
            'mov.f431_id_unidad_medida as unidad_medida',
            DB::raw('ROUND(mov.f431_cant_pedida_base, 0) as cantidad'),
            'mov.f431_precio_unitario_base as precio_unitario',
            'mov.f431_vlr_neto as valor_total',
            'mov.f431_rowid_bodega as bodega',
            'cab.f430_num_docto_referencia as oc_cliente',
            'cli.f200_razon_social as cliente',
            'cab.f430_notas as notas_completas',
            'est.f054_descripcion as estado_op',
            DB::raw("
                CASE 
                    WHEN PATINDEX('%PT %', cab.f430_notas) > 0 THEN
                        LTRIM(RTRIM(
                            SUBSTRING(
                                cab.f430_notas,
                                PATINDEX('%PT %', cab.f430_notas),
                                CHARINDEX(' ', cab.f430_notas + ' ', PATINDEX('%PT %', cab.f430_notas) + 3) 
                                    - PATINDEX('%PT %', cab.f430_notas)
                            )
                        ))
                    ELSE 'N/A'
                END AS PT
            ")
        ])
        ->whereIn('mov.f431_rowid_pv_docto', function($query) use ($numerosPV) {
            $query->select('f430_rowid')
                  ->from('t430_cm_pv_docto')
                  ->whereIn('f430_consec_docto', $numerosPV);
        })
        // ->where('cli.f200_razon_social', $cliente)
        ->orderBy('i.f120_referencia')
        ->orderBy('tal_det.f119_descripcion')
        ->orderBy('col_det.f117_descripcion')
        ->get();
}


}
