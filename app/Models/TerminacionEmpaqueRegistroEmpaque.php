<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TerminacionEmpaqueRegistroEmpaque extends Model
{
    protected $table = 'registro_empaque';
    protected $connection = 'terminacion_empaque';
    public $timestamps = false;
    
    protected $fillable = [
        'empacador_id',
        'pv_id',
        'item_id',
        'item_hash',
        'cantidad',
        'fecha_empaque',
        'tipo_empaque',
        'numero_empaque',
        'comentario'
    ];

    protected $casts = [
        'cantidad' => 'decimal:2',
        'fecha_empaque' => 'datetime'
    ];

    /**
     * Registra múltiples registros de empaque
     */
    public static function registrarEmpaqueMultiple($registros)
    {
        try {
            DB::connection('terminacion_empaque')
            ->beginTransaction();
            
            foreach ($registros as $registro) {
                self::create([
                    'empacador_id' => $registro['empacador_id'],
                    'pv_id' => $registro['pv_id'],
                    'item_id' => $registro['item_id'],
                    'item_hash' => $registro['item_hash'],
                    'cantidad' => $registro['cantidad'],
                    'tipo_empaque' => $registro['tipo_empaque'],
                    'numero_empaque' => $registro['numero_empaque'],
                    'fecha_empaque' => Carbon::now('America/Bogota'),
                ]);
            }
            
            DB::connection('terminacion_empaque')
            ->commit();
            return true;
            
        } catch (\Exception $e) {
            DB::connection('terminacion_empaque')
            ->rollback();
            throw $e;
        }
    }
    
    public static function obtenerEmpaquesPorPV(string $pvId, int $empacadorId)
    {
        return DB::connection('terminacion_empaque')
            ->table('registro_empaque as re')
            ->join('inv_op_pv_distribucion as dist', function ($join) {
                $join->on('re.pv_id', '=', 'dist.pv_id')
                     ->on('re.item_hash', '=', 'dist.item_hash');
            })
            ->select(
                're.pv_id',
                're.item_id',
                're.item_hash',
                're.cantidad',
                're.tipo_empaque',
                're.numero_empaque',
                're.fecha_empaque',
                're.comentario',
                're.empacador_id',
                'dist.descripcion',
                'dist.id_color',
                'dist.id_talla',
                'dist.op_id',
                'dist.pv_id',
                'dist.oc_id',
                'dist.cliente'
            )
            ->where('re.pv_id', $pvId)
            ->where('re.empacador_id', $empacadorId)
            ->orderBy('re.fecha_empaque', 'desc')
            ->get();
    }
    
    public static function EmpaquesPorPV(string $pvId)
    {
        return DB::connection('terminacion_empaque')
            ->table('registro_empaque as re')
            ->join('inv_op_pv_distribucion as dist', function ($join) {
                $join->on('re.pv_id', '=', 'dist.pv_id')
                     ->on('re.item_hash', '=', 'dist.item_hash');
            })
            ->select(
                're.pv_id',
                're.item_id',
                're.item_hash',
                're.cantidad',
                're.tipo_empaque',
                're.numero_empaque',
                're.fecha_empaque',
                're.comentario',
                're.empacador_id',
                'dist.descripcion',
                'dist.id_color',
                'dist.id_talla',
                'dist.op_id',
                'dist.pv_id',
                'dist.oc_id',
                'dist.cliente'
            )
            ->where('re.pv_id', $pvId)
            ->orderBy('re.fecha_empaque', 'desc')
            ->get();
    }

    /**
     * Obtiene el total empacado por PV y empacador
     */
    public static function obtenerTotalEmpacadoPorPV($pvCodigo, $empacadorId = null)
    {
        $query = self::where('pv_id', $pvCodigo);
        
        if ($empacadorId) {
            $query->where('empacador_id', $empacadorId);
        }
        
        return $query->sum('cantidad');
    }

    /**
     * Obtiene el total empacado por item
     */
    public static function obtenerTotalEmpacadoPorItem($itemId, $pvCodigo = null)
    {
        $query = self::where('item_id', $itemId);
        
        if ($pvCodigo) {
            $query->where('pv_id', $pvCodigo);
        }
        
        return $query->sum('cantidad');
    }

    /**
     * Obtiene el historial de empaque de una PV
     */
    public static function obtenerHistorialEmpaquePV($pvCodigo)
    {
        return self::where('pv_id', $pvCodigo)
            ->orderBy('fecha_empaque', 'desc')
            ->get();
    }

    /**
     * Obtiene estadísticas de empaque por empacador
     */
    public static function obtenerEstadisticasEmpacador($empacadorId, $fechaInicio = null, $fechaFin = null)
    {
        $query = self::where('empacador_id', $empacadorId);

        if ($fechaInicio) {
            $query->where('fecha_empaque', '>=', $fechaInicio);
        }

        if ($fechaFin) {
            $query->where('fecha_empaque', '<=', $fechaFin);
        }

        return [
            'pvs_trabajadas' => $query->distinct('pv_id')->count('pv_id'),
            'registros_empaque' => $query->count(),
            'total_empacado' => $query->sum('cantidad'),
            'items_diferentes' => $query->distinct('item_id')->count('item_id'),
            'primer_empaque' => $query->min('fecha_empaque'),
            'ultimo_empaque' => $query->max('fecha_empaque')
        ];
    }
    
    /**
     * Obtiene los KPIs principales del dashboard
     */
    public static function obtenerKPIs(array $filtros = []): array
    {
        // ========================
        // 1. KPIs de registro_empaque (PVs, items, empacadores)
        // ========================
        $queryPV = DB::connection('terminacion_empaque')
            ->table('registro_empaque')
            ->selectRaw('
                COUNT(DISTINCT pv_id) as total_pvs,
                SUM(cantidad) as total_items,
                COUNT(DISTINCT empacador_id) as total_empacadores
            ');
    
        // Filtro de fechas sobre fecha_empaque
        if (!empty($filtros['fechaInicio']) && !empty($filtros['fechaFin'])) {
            $queryPV->whereBetween('fecha_empaque', [$filtros['fechaInicio'], $filtros['fechaFin']]);
        } elseif (!empty($filtros['fechaInicio'])) {
            $queryPV->whereDate('fecha_empaque', '>=', $filtros['fechaInicio']);
        } elseif (!empty($filtros['fechaFin'])) {
            $queryPV->whereDate('fecha_empaque', '<=', $filtros['fechaFin']);
        }
    
        // Filtro por empacador
        if (!empty($filtros['empacador'])) {
            $queryPV->where('empacador_id', $filtros['empacador']);
        }
    
        $resultadoPV = $queryPV->first();
    
        // ========================
        // 2. KPIs de OPS (usando tabla ops)
        // ========================
        $queryOP = DB::connection('terminacion_empaque')
            ->table('ops')
            ->selectRaw('COUNT(DISTINCT id) as total_ops')
            ->where('estado', 'activo'); // Asumo que tienes un campo estado o algo que marca las activas
    
        // Filtro de fechas sobre fecha_activacion
        if (!empty($filtros['fechaInicio']) && !empty($filtros['fechaFin'])) {
            $queryOP->whereBetween('fecha_activacion', [$filtros['fechaInicio'], $filtros['fechaFin']]);
        } elseif (!empty($filtros['fechaInicio'])) {
            $queryOP->whereDate('fecha_activacion', '>=', $filtros['fechaInicio']);
        } elseif (!empty($filtros['fechaFin'])) {
            $queryOP->whereDate('fecha_activacion', '<=', $filtros['fechaFin']);
        }
    
        $resultadoOP = $queryOP->first();
    
        // ========================
        // 3. Combinar resultados
        // ========================
        return [
            'total_pvs'        => (int) ($resultadoPV->total_pvs ?? 0),
            'total_ops'        => (int) ($resultadoOP->total_ops ?? 0),
            'total_items'      => (int) ($resultadoPV->total_items ?? 0),
            'total_empacadores'=> (int) ($resultadoPV->total_empacadores ?? 0),
        ];
    }

    /**
     * Obtiene registros por mes
     */
    public static function obtenerRegistrosPorDia(array $filtros = []): array
    {
        $query = DB::connection('terminacion_empaque')
            ->table('registro_empaque as re')
            ->join('inv_op_pv_distribucion as d', function($join) {
                $join->on('d.pv_id', '=', 're.pv_id')
                     ->on('d.item_hash', '=', 're.item_hash');
            })
            ->join('inv_op_recepciones as orx', function($join) {
                $join->on('orx.op_codigo', '=', 'd.op_id')
                     ->on('orx.item_hash', '=', 'd.item_hash');
            })
            ->selectRaw('
                DATE(re.fecha_empaque) as fecha,
                SUM(re.cantidad) as total_items,
                COUNT(DISTINCT re.pv_id) as total_pvs,
                SUM(re.cantidad * orx.precio_unitario) as total_costo
            ')
            ->whereBetween('re.fecha_empaque', [
                DB::raw('DATE_FORMAT(CURDATE(), "%Y-%m-01")'),   // primer día del mes
                DB::raw('LAST_DAY(CURDATE())')                  // último día del mes
            ])
            ->groupBy(DB::raw('DATE(re.fecha_empaque)'))
            ->orderBy('fecha');

        // Aplicar filtros opcionales
        if (!empty($filtros['empacador'])) {
            $query->where('re.empacador_id', $filtros['empacador']);
        }
        if (!empty($filtros['fecha_inicio'])) {
            $query->whereDate('re.fecha_empaque', '>=', $filtros['fecha_inicio']);
        }
        if (!empty($filtros['fecha_fin'])) {
            $query->whereDate('re.fecha_empaque', '<=', $filtros['fecha_fin']);
        }

        $registros = $query->get();

        return $registros->map(function ($registro) {
            return [
                'fecha'        => $registro->fecha,
                'total_items'  => (int) $registro->total_items,
                'total_pvs'    => (int) $registro->total_pvs,
                'total_costo'  => (float) $registro->total_costo
            ];
        })->toArray();
    }

    /**
     * Obtiene estadísticas por empacador
     */
    public static function obtenerEstadisticasPorEmpacador(array $filtros = []): array
    {
        $query = DB::connection('terminacion_empaque')
            ->table('registro_empaque')
            ->selectRaw('
                empacador_id,
                SUM(cantidad) as total_items,
                COUNT(DISTINCT pv_id) as total_pvs,
                COUNT(DISTINCT SUBSTRING_INDEX(pv_id, "-", -1)) as total_ops
            ');

        // Aplicar filtros
        if (!empty($filtros['fecha'])) {
            $query->whereDate('fecha_empaque', $filtros['fecha']);
        }

        if (!empty($filtros['empacador'])) {
            $query->where('empacador_id', $filtros['empacador']);
        }

        $registros = $query->groupBy('empacador_id')->get();

        return $registros->map(function ($registro) {
            return [
                'empacador_id' => $registro->empacador_id,
                'total_items' => (int) $registro->total_items,
                'total_pvs' => (int) $registro->total_pvs,
                'total_ops' => (int) $registro->total_ops
            ];
        })->toArray();
    }

    /**
     * Obtiene registros detallados con información adicional
     */
    public static function obtenerRegistrosDetallados(array $filtros = []): array
    {
        $query = DB::connection('terminacion_empaque')
            ->table('registro_empaque as r')
            ->selectRaw('
                r.*,
                SUBSTRING_INDEX(r.pv_id, "-", -1) as op_id,
                d.descripcion,
                d.id_talla
            ')
            ->leftJoin('inv_op_pv_distribucion as d', function($join) {
                $join->on('r.pv_id', '=', 'd.pv_id')
                     ->on('r.item_id', '=', 'd.id_item')
                     ->on('r.item_hash', '=', 'd.item_hash');
            });

        // Aplicar filtros
        if (!empty($filtros['fecha'])) {
            $query->whereDate('r.fecha_empaque', $filtros['fecha']);
        }

        if (!empty($filtros['empacador'])) {
            $query->where('r.empacador_id', $filtros['empacador']);
        }

        $registros = $query->orderByDesc('r.fecha_empaque')
                          ->limit(50)
                          ->get();

        return $registros->map(function ($registro) {
            return [
                'id' => $registro->id,
                'pv_id' => $registro->pv_id,
                'op_id' => $registro->op_id,
                'item_id' => $registro->item_id,
                'empacador_id' => $registro->empacador_id,
                'cantidad' => (int) $registro->cantidad,
                'fecha_empaque' => $registro->fecha_empaque,
                'descripcion' => $registro->descripcion,
                'id_talla' => $registro->id_talla,
                'tipo_empaque' => $registro->tipo_empaque,
                'numero_empaque' => $registro->numero_empaque
            ];
        })->toArray();
    }
}
