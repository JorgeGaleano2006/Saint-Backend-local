<?php

namespace App\Models;

use App\Models\TerminacionEmpaquePVItems;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class TerminacionEmpaqueOps extends Model
{
    protected $connection = 'siesa';
    protected $table = 't850_mf_op_docto';
    protected $primaryKey = 'f850_consec_docto';
    public $incrementing = false;
    public $timestamps = false;

    /**
     * Obtener OPs activas de los últimos 2 años
     */
    public static function listarActivasUltimos2Anios()
    {
        return self::select([
                'f850_consec_docto AS id',
                'f850_consec_docto AS codigo',
                'f850_fecha_ts_creacion AS fecha_creacion',
                'f850_ind_estado AS estado'
            ])
            ->where('f850_id_cia', '01')
            ->whereNotIn('f850_ind_estado', [9])
            ->where('f850_fecha_ts_creacion', '>=', now()->subYears(2))
            ->orderByDesc('f850_fecha_ts_creacion')
            ->get();
    }

    /**
     * Listar PVs asociadas a una OP
     */
    public static function listarPVsPorOP($numeroOp)
    {
        return self::select([
                DB::raw("ISNULL(f850_notas, '') AS pvs"), // Función SQL nativa
                'f850_consec_docto AS numero_op',
                'f850_fecha_ts_creacion AS fecha_op',
                'f850_ind_estado AS estado_op'
            ])
            ->where('f850_consec_docto', $numeroOp)
            ->get();
    }
    
    public static function costoRealDeOP($numeroOp)
    {
        // Buscar registro de OP
        $op = self::listarPVsPorOP($numeroOp)->first();
    
        if (!$op || empty($op->pvs)) {
            return ['total' => 0, 'pvs' => []];
        }
    
        // Normalizar: quitar "PV", puntos y espacios extra
        $rawPvs = strtoupper($op->pvs);
        $rawPvs = str_replace(['PV', '.', ';'], '', $rawPvs);
    
        // Separar por coma, guion o espacio
        $tokens = preg_split('/[,\-\s]+/', $rawPvs);
    
        // Filtrar: dejar solo valores numéricos válidos
        $pvs = array_values(array_filter($tokens, function ($pv) {
            return preg_match('/^\d+$/', $pv);
        }));
    
        $total = 0;
        $detallePvs = [];
    
        foreach ($pvs as $numeroPv) {
            $costoRealPv = TerminacionEmpaquePVItems::costoRealDePV($numeroPv);
    
            $detallePvs[$numeroPv] = $costoRealPv;
            $total += $costoRealPv;
        }
    
        return [
            'total' => $total,
            'pvs'   => $detallePvs
        ];
    }
        
    public static function costoRealDePT($numeroPt)
    {
        // Normalizar: extraer solo dígitos
        $numeroPt = preg_replace('/\D/', '', $numeroPt);
    
        if (empty($numeroPt) || strlen($numeroPt) > 10) {
            return 0;
        }
    
        try {
            return DB::connection('siesa')
                ->table('t431_cm_pv_movto AS mov')
                ->join('t121_mc_items_extensiones AS ext', 'ext.f121_rowid', '=', 'mov.f431_rowid_item_ext')
                ->join('t120_mc_items AS i', 'i.f120_rowid', '=', 'ext.f121_rowid_item')
                ->where('mov.f431_rowid_pv_docto', function ($query) use ($numeroPt) {
                    $query->select('f430_rowid')
                        ->from('t430_cm_pv_docto')
                        ->where('f430_consec_docto', $numeroPt)
                        ->limit(1);
                })
                ->selectRaw('ROUND(SUM(mov.f431_cant_pedida_base * mov.f431_precio_unitario_base), 2) as total')
                ->value('total') ?? 0;
        } catch (\Exception $e) {
            \Log::error("Error al calcular costoRealDePT para {$numeroPt}: " . $e->getMessage());
            return 0;
        }
    }

}
