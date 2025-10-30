<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class TerminacionEmpaquePVItems extends Model
{
    protected $connection = 'siesa';
    protected $table = 't431_cm_pv_movto';
    protected $primaryKey = 'f431_rowid';
    public $incrementing = false;
    public $timestamps = false;

    /**
     * Listar ítems de una PV
     */
    public static function listarItemsDePV($numeroPV)
    {
        return self::join('t121_mc_items_extensiones AS ext', 'ext.f121_rowid', '=', 't431_cm_pv_movto.f431_rowid_item_ext')
            ->join('t120_mc_items AS i', 'i.f120_rowid', '=', 'ext.f121_rowid_item')
            ->leftJoin('t117_mc_extensiones1_detalle AS col_det', function($join) {
                $join->on('col_det.f117_id', '=', 'ext.f121_id_ext1_detalle')
                     ->on('col_det.f117_id_extension1', '=', 'ext.f121_id_extension1');
            })
            ->leftJoin('t119_mc_extensiones2_detalle AS tal_det', function($join) {
                $join->on('tal_det.f119_id', '=', 'ext.f121_id_ext2_detalle')
                     ->on('tal_det.f119_id_extension2', '=', 'ext.f121_id_extension2');
            })
            ->join('t430_cm_pv_docto AS cab', 'cab.f430_rowid', '=', 't431_cm_pv_movto.f431_rowid_pv_docto')
            ->join('t200_mm_terceros AS cli', 'cli.f200_rowid', '=', 'cab.f430_rowid_tercero_fact')
            ->where('t431_cm_pv_movto.f431_rowid_pv_docto', function($query) use ($numeroPV) {
                $query->select('f430_rowid')
                    ->from('t430_cm_pv_docto')
                    ->where('f430_consec_docto', $numeroPV)
                    ->limit(1);
            })
            ->orderBy('i.f120_referencia')
            ->orderBy('tal_det.f119_descripcion')
            ->orderBy('col_det.f117_descripcion')
            ->select([
                't431_cm_pv_movto.f431_rowid_pv_docto AS numero_pv',
                'i.f120_id',
                'i.f120_referencia AS referencia',
                'i.f120_descripcion_corta AS descripcion_corta',
                'i.f120_descripcion AS descripcion',
                't431_cm_pv_movto.f431_rowid_item_ext',
                'col_det.f117_id AS id_color',
                'col_det.f117_descripcion AS color',
                'tal_det.f119_id AS id_talla',
                'tal_det.f119_descripcion AS talla',
                't431_cm_pv_movto.f431_id_unidad_medida AS unidad_medida',
                DB::raw('ROUND([t431_cm_pv_movto].[f431_cant_pedida_base], 0) AS cantidad'),
                't431_cm_pv_movto.f431_precio_unitario_base AS precio_unitario',
                't431_cm_pv_movto.f431_vlr_neto AS valor_total',
                't431_cm_pv_movto.f431_rowid_bodega AS bodega',
                'cab.f430_num_docto_referencia AS oc_cliente',
                'cli.f200_razon_social AS cliente',
                'cab.f430_notas AS notas_completas',
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
            ->get();
    }
    
    /**
     * Calcular el costo real de una PV (suma de todos sus ítems).
     */
    public static function costoRealDePV($numeroPv)
    {
        // Normalizar número (quita letras, PV, etc.)
        $numeroPv = preg_replace('/\D/', '', $numeroPv);
    
        if (empty($numeroPv) || strlen($numeroPv) > 10) {
            return 0;
        }
    
        try {
            return DB::connection('siesa')
                ->table('t431_cm_pv_movto AS m')
                ->join('t121_mc_items_extensiones AS ext', 'ext.f121_rowid', '=', 'm.f431_rowid_item_ext')
                ->join('t120_mc_items AS i', 'i.f120_rowid', '=', 'ext.f121_rowid_item')
                ->where('m.f431_rowid_pv_docto', function ($query) use ($numeroPv) {
                    $query->select('f430_rowid')
                        ->from('t430_cm_pv_docto')
                        ->where('f430_consec_docto', $numeroPv)
                        ->limit(1);
                })
                ->selectRaw('SUM(m.f431_cant_pedida_base * m.f431_precio_unitario_base) as costo_real')
                ->value('costo_real') ?? 0;
        } catch (\Exception $e) {
            \Log::error("Error al calcular costoRealDePV para PV {$numeroPv}: " . $e->getMessage());
            return 0;
        }
    }

}

