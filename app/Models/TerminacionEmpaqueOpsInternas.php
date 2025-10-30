<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class TerminacionEmpaqueOpsInternas extends Model
{
    protected $connection = 'terminacion_empaque';
    protected $table = 'ops';
    public $timestamps = false;
    
    protected $fillable = [
        'op_codigo',
        'estado',
        'fecha_activacion',
        'usuario_activacion'
    ];
    
    public static function opsPendientes() 
    {
        return self::where('estado', 'activo')->pluck('op_codigo');
    }

    /**
     * Obtiene datos para el dashboard de OPs
     */
    public static function getDashboardData($fecha = null, $estado = null, $numeroOP = null)
    {
        $query = self::select(
            'op_codigo as codigo',
            'estado',
            'fecha_activacion as fecha_creacion'
        );
    
        if ($fecha) {
            $query->whereDate('fecha_activacion', $fecha);
        }
    
        if ($estado) {
            $query->where('estado', $estado);
        }
    
        if ($numeroOP) {
            $query->where('op_codigo', 'like', "%{$numeroOP}%");
        }
    
        $ops = $query->get();
    
        foreach ($ops as $op) {
            // Calcular cantidad total recibida
            $op->cantidad_total = DB::connection('terminacion_empaque')
                ->table('inv_op_recepciones')
                ->where('op_codigo', $op->codigo)
                ->sum('cantidad_recibida');
    
            // Calcular cantidad empacada
            $op->cantidad_empacada = DB::connection('terminacion_empaque')
                ->table('registro_empaque as re')
                ->join('inv_op_pv_distribucion as d', 're.pv_id', '=', 'd.pv_id')
                ->where('d.op_id', $op->codigo)
                ->sum('re.cantidad');
    
            // Calcular progreso
            $op->progreso = $op->cantidad_total > 0 
                ? round(($op->cantidad_empacada / $op->cantidad_total) * 100, 2) 
                : 0;
    
            // Obtener PVs asociadas + sus ítems completos
            $pvs = DB::connection('terminacion_empaque')
                ->table('inv_op_pv_distribucion as d')
                ->where('d.op_id', $op->codigo)
                ->select('d.pv_id as codigo')
                ->distinct()
                ->get();
    
            $op->costo_total = 0; // Inicializar costo total de la OP
    
            foreach ($pvs as $pv) {
                $pv->items = DB::connection('terminacion_empaque')
                    ->table('inv_op_pv_distribucion as d')
                    ->leftJoin('registro_empaque as re', function ($join) use ($pv) {
                        $join->on('re.pv_id', '=', 'd.pv_id')
                             ->on('re.item_hash', '=', 'd.item_hash');
                    })
                    ->where('d.pv_id', $pv->codigo)
                    ->select(
                        'd.oc_id',
                        'd.item_hash',
                        'd.id_item',
                        'd.referencia',
                        'd.descripcion',
                        'd.id_color',
                        'd.id_talla',
                        'd.cantidad_asignada',
                        'd.cantidad_teorica',
                        'd.fecha_asignacion',
                        'd.registrado_por',
                        DB::raw('COALESCE(SUM(re.cantidad), 0) as cantidad_empacada')
                    )
                    ->groupBy(
                        'd.oc_id',
                        'd.item_hash',
                        'd.id_item',
                        'd.referencia',
                        'd.descripcion',
                        'd.id_color',
                        'd.id_talla',
                        'd.cantidad_asignada',
                        'd.cantidad_teorica',
                        'd.fecha_asignacion',
                        'd.registrado_por'
                    )
                    ->get();
    
                $pv->costo_total = 0; // Inicializar costo total de la PV
    
                // Calcular costo total de cada item de la PV
                foreach ($pv->items as $item) {
                    // Obtener el precio unitario desde inv_op_recepciones
                    // Sumar todas las recepciones del item para obtener cantidad total y costo total
                    $recepciones = DB::connection('terminacion_empaque')
                        ->table('inv_op_recepciones')
                        ->where('op_codigo', $op->codigo)
                        ->where('item_hash', $item->item_hash)
                        ->select('cantidad_recibida', 'precio_unitario')
                        ->get();
    
                    $cantidad_total_recibida = $recepciones->sum('cantidad_recibida');
                    $costo_total_recepciones = $recepciones->sum(function($recepcion) {
                        return $recepcion->cantidad_recibida * $recepcion->precio_unitario;
                    });
    
                    // Calcular precio promedio ponderado si hay múltiples recepciones
                    $precio_promedio = $cantidad_total_recibida > 0 
                        ? $costo_total_recepciones / $cantidad_total_recibida 
                        : 0;
    
                    // Asignar costo total del item basado en la cantidad asignada
                    $item->precio_unitario = $precio_promedio;
                    $item->costo_total = $item->cantidad_asignada * $precio_promedio;
    
                    // Sumar al costo total de la PV
                    $pv->costo_total += $item->costo_total;
                }
    
                // Obtener PTs agrupados por pt_codigo
                $ptRecords = DB::connection('terminacion_empaque')
                    ->table('inv_pt_recepciones as pt')
                    ->where('pt.pv_codigo', $pv->codigo)
                    ->select(
                        'pt.id',
                        'pt.pv_codigo',
                        'pt.pt_codigo',
                        'pt.item_hash',
                        'pt.referencia',
                        'pt.id_item',
                        'pt.descripcion',
                        'pt.id_color',
                        'pt.id_talla',
                        'pt.cantidad_teorica',
                        'pt.cantidad_recibida',
                        'pt.precio_unitario', // Agregar precio_unitario
                        'pt.usuario',
                        'pt.ubicacion',
                        'pt.comentario',
                        'pt.fecha_registro'
                    )
                    ->get();
    
                // Agrupar PTs por su código y calcular costos
                $groupedPTs = $ptRecords->groupBy('pt_codigo')->map(function ($group) {
                    $ptData = (object) [
                        'pt_codigo' => $group->first()->pt_codigo,
                        'items' => $group->all(),
                        'costo_total' => 0
                    ];
    
                    // Calcular costo total de cada item en la PT y sumar al total de la PT
                    foreach ($ptData->items as $ptItem) {
                        $ptItem->costo_total = $ptItem->cantidad_recibida * $ptItem->precio_unitario;
                        $ptData->costo_total += $ptItem->costo_total;
                    }
    
                    return $ptData;
                })->values();
    
                $pv->pts = $groupedPTs;
                
                // 🔹 Empacador asignado
                $empacador = DB::connection('terminacion_empaque')
                    ->table('empacador_pv_asignaciones')
                    ->where('pv_codigo', $pv->codigo)
                    ->select('empacador_id')
                    ->first();
            
                $pv->empacador = $empacador ? $empacador->empacador_id : null;
    
                // Sumar el costo de esta PV al costo total de la OP
                $op->costo_total += $pv->costo_total;
            }
    
            $op->pvs = $pvs;
        }
    
        return $ops;
    }

    /**
     * Obtiene detalle completo de una OP
     */
    public static function getDetalleCompleto($opCodigo)
    {
        $op = self::where('op_codigo', $opCodigo)->first();

        if (!$op) {
            return null;
        }

        // Obtener PVs asociadas
        $pvs = DB::connection('terminacion_empaque')
            ->table('inv_op_pv_distribucion')
            ->where('op_id', $opCodigo)
            ->select('pv_id as codigo')
            ->distinct()
            ->get();

        // Obtener registros de empaque
        $empaque_registros = DB::connection('terminacion_empaque')
            ->table('registro_empaque as re')
            ->leftJoin('users as u', 're.empacador_id', '=', 'u.id')
            ->join('inv_op_pv_distribucion as d', 're.pv_id', '=', 'd.pv_id')
            ->where('d.op_id', $opCodigo)
            ->select('re.*', 'u.nombre_completo as empacador_nombre')
            ->get();

        return [
            'op' => $op,
            'pvs' => $pvs,
            'pts' => [], // Vacío por ahora
            'empaque_registros' => $empaque_registros
        ];
    }

    /**
     * Obtiene datos para QR de una OP
     */
    public static function getQRData($opCodigo)
    {
        $op = self::where('op_codigo', $opCodigo)->first();

        if (!$op) {
            return null;
        }

        // Obtener PVs asociadas
        $pvs = DB::connection('terminacion_empaque')
            ->table('inv_op_pv_distribucion')
            ->where('op_id', $opCodigo)
            ->select('pv_id as codigo')
            ->distinct()
            ->get();

        return [
            'op_id' => $op->id,
            'codigo' => $op->op_codigo,
            'estado' => $op->estado,
            'descripcion' => '', // No disponible en tu tabla
            'cantidad_total' => DB::connection('terminacion_empaque')
                ->table('inv_op_recepciones')
                ->where('op_codigo', $opCodigo)
                ->sum('cantidad_recibida'),
            'fecha_creacion' => $op->fecha_activacion,
            'pvs' => $pvs,
            'pts' => [], // Vacío por ahora
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * Actualiza el estado de una OP
     */
    public static function actualizarEstado($opCodigo, $nuevoEstado)
    {
        return self::where('op_codigo', $opCodigo)
            ->update(['estado' => $nuevoEstado]);
    }

    /**
     * Obtiene el progreso de empaque de una OP
     */
    public static function getProgreso($opCodigo)
    {
        $cantidad_total = DB::connection('terminacion_empaque')
            ->table('inv_op_recepciones')
            ->where('op_codigo', $opCodigo)
            ->sum('cantidad_recibida');

        $cantidad_empacada = DB::connection('terminacion_empaque')
            ->table('registro_empaque as re')
            ->join('inv_op_pv_distribucion as d', 're.pv_id', '=', 'd.pv_id')
            ->where('d.op_id', $opCodigo)
            ->sum('re.cantidad');

        $porcentaje = $cantidad_total > 0 
            ? round(($cantidad_empacada / $cantidad_total) * 100, 2) 
            : 0;

        return (object) [
            'cantidad_total' => $cantidad_total,
            'cantidad_empacada' => $cantidad_empacada,
            'porcentaje_progreso' => $porcentaje
        ];
    }
}
