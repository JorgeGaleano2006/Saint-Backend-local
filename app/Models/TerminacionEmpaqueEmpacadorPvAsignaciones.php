<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class TerminacionEmpaqueEmpacadorPvAsignaciones extends Model
{
    protected $connection = 'terminacion_empaque';
    protected $table = 'empacador_pv_asignaciones';
    public $timestamps = false;
    protected $fillable = [
        'empacador_id',
        'pv_codigo',
        'fecha_asignacion',
        'usuario_asignacion',
        'activo'
    ];

    /**
     * Obtener PVs pendientes (disponibles para asignar)
     * Se obtienen desde inv_op_pv_distribucion y se filtran las que ya están asignadas
     */
    public static function obtenerPVsPendientes()
    {
        return DB::connection('terminacion_empaque') 
        ->table('inv_op_pv_distribucion as dist')
            ->select([
                'dist.pv_id as codigo',
                'dist.op_id as numero_op',
            ])
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                      ->from('empacador_pv_asignaciones as epa')
                      ->whereColumn('epa.pv_codigo', 'dist.pv_id');
                    //   ->where('epa.activo', 1);
            })
            ->groupBy('dist.pv_id', 'dist.op_id')
            ->get();
    }

    /**
     * Obtener asignaciones múltiples de empacadores
     */
    public static function obtenerAsignacionesMultiples($empacadorIds)
    {
        $resultado = [];
    
        foreach ($empacadorIds as $empacadorId) {
            // Obtener PVs asignadas al empacador (incluyendo las que no tengan recepciones)
            $pvs = DB::connection('terminacion_empaque')
                ->table('empacador_pv_asignaciones as epa')
                ->leftJoin('inv_op_pv_distribucion as dist', 'dist.pv_id', '=', 'epa.pv_codigo')
                ->leftJoin('inv_pt_recepciones as rec', 'rec.pv_codigo', '=', 'epa.pv_codigo') // 🔹 left join mantiene PVs aunque no tengan recepción
                ->select([
                    'epa.pv_codigo as codigo',
                    DB::raw('MIN(dist.op_id) as op_id'),
                    DB::raw('0 as empacado'), // se calcula después
                    DB::raw('COALESCE(SUM(dist.cantidad_teorica),0) as teorico'),
                    DB::raw('COALESCE(SUM(dist.cantidad_asignada),0) as asignado'),
                    DB::raw('MAX(rec.pt_codigo) as pt') // puede ser NULL si no hay recepción
                ])
                ->where('epa.empacador_id', $empacadorId)
                ->groupBy('epa.pv_codigo')
                ->get();
    
            // Calcular totales empacados desde registro_empaque
            $totalEmpacado = DB::connection('terminacion_empaque')
                ->table('registro_empaque')
                ->where('empacador_id', $empacadorId)
                ->sum('cantidad');
    
            // Calcular totales teóricos y asignados
            $totalTeorico = 0;
            $totalAsignado = 0;
    
            foreach ($pvs as $pv) {
                // Calcular empacado específico por PV
                $pv->empacado = self::calcularEmpacadosPorPV($empacadorId, $pv->codigo);
    
                $totalTeorico += $pv->teorico;
                $totalAsignado += $pv->asignado;
            }
    
            $resultado[$empacadorId] = [
                'pvs' => $pvs,
                'total_empacado' => $totalEmpacado,
                'total_teorico' => $totalTeorico,
                'total_asignado' => $totalAsignado
            ];
        }
    
        return $resultado;
    }

    /**
     * Asignar una PV a un empacador
     */
    public static function asignarPV($empacadorId, $pvCodigo)
    {
        // // Verificar que la PV existe en inv_op_pv_distribucion
        // $pvExists = DB::connection('terminacion_empaque') 
        //     ->table('inv_op_pv_distribucion')
        //     ->where('pv_id', $pvCodigo)
        //     ->exists();

        // if (!$pvExists) {
        //     throw new \Exception('La PV especificada no existe en la distribución');
        // }

        // Verificar que no esté ya asignada
        $yaAsignada = DB::connection('terminacion_empaque') 
            ->table('empacador_pv_asignaciones')
            ->where('pv_codigo', $pvCodigo)
            ->exists();

        if ($yaAsignada) {
            throw new \Exception('La PV ya está asignada a otro empacador');
        }

        // Crear la asignación
        return DB::connection('terminacion_empaque') 
            ->table('empacador_pv_asignaciones')->insert([
            'empacador_id' => $empacadorId,
            'pv_codigo' => $pvCodigo,
            'fecha_asignado' => now(),
            'usuario_asigna' => auth()->id() ?? 1
        ]);
    }

    /**
     * Desasignar una PV de un empacador
     */
    public static function desasignarPV($empacadorId, $pvCodigo)
    {
        return DB::connection('terminacion_empaque')
            ->table('empacador_pv_asignaciones')
            ->where('empacador_id', $empacadorId)
            ->where('pv_codigo', $pvCodigo)
            ->delete() > 0;
    }

    /**
     * Métodos auxiliares para cálculos
     */
    private static function calcularTeoricosPorPV($pvCodigo)
    {
        return DB::connection('terminacion_empaque') 
            ->table('inv_op_pv_distribucion')
            ->where('pv_id', $pvCodigo)
            ->sum('cantidad_teorica') ?? 0;
    }

    private static function calcularAsignadosPorPV($pvCodigo)
    {
        return DB::connection('terminacion_empaque') 
            ->table('inv_op_pv_distribucion')
            ->where('pv_id', $pvCodigo)
            ->sum('cantidad_asignada') ?? 0;
    }

    private static function calcularEmpacadosPorPV($empacadorId, $pvCodigo)
    {
        return DB::connection('terminacion_empaque') 
            ->table('registro_empaque')
            ->where('empacador_id', $empacadorId)
            ->where('pv_id', 'LIKE', "%{$pvCodigo}%") // Como están separados por coma en tu BD actual
            ->sum('cantidad') ?? 0;
    }
}