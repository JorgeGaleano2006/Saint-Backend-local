<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TerminacionEmpaqueOpPvDistribucion extends Model
{
    protected $connection = 'terminacion_empaque';
    protected $table = 'inv_op_pv_distribucion';
    public $timestamps = false;

    protected $fillable = [
        'op_id',
        'pv_id',
        'oc_id',
        'item_hash',
        'referencia',
        'cantidad_asignada',
        'cantidad_teorica',
        'fecha_asignacion',
        'registrado_por',
        'descripcion',
        'id_color',
        'id_talla'
    ];
    
    public static function obtenerDistribucionDeItems(string $numeroPv, string $hash): float
    {
        return DB::connection('terminacion_empaque')
            ->table('inv_op_pv_distribucion')
            ->where('pv_id', $numeroPv)
            ->where('item_hash', $hash)
            ->value('cantidad_asignada') ?? 0;
    }
    
    public static function obtenerItemsPVConDistribucion(string $numeroPv, string $empacadorId)
    {
        return DB::connection('terminacion_empaque')
            ->table('inv_op_pv_distribucion as dist')
            ->leftJoin('registro_empaque as re', function ($join) use ($empacadorId) {
                $join->on('re.item_hash', '=', 'dist.item_hash')
                     ->where('re.empacador_id', '=', $empacadorId);
            })
            ->select([
                'dist.item_hash',
                'dist.id_item',
                'dist.descripcion',
                'dist.referencia',
                'dist.id_talla',
                'dist.id_color',
                DB::raw('SUM(dist.cantidad_teorica) as teorico'),
                DB::raw('SUM(dist.cantidad_asignada) as asignado'),
                DB::raw('COALESCE(SUM(re.cantidad), 0) as empacado')
            ])
            ->where('dist.pv_id', $numeroPv)
            ->groupBy('dist.item_hash', 'dist.id_item', 'dist.descripcion','dist.referencia','dist.id_talla','dist.id_color')
            ->get();
    }
    
    public static function existenItemsPendientes(array $hashes): bool
    {
        if (empty($hashes)) {
            return false; // no hay nada que validar
        }
    
        // Obtenemos todos los registros que coinciden con los item_id dados
        $registros = DB::connection('terminacion_empaque')
            ->table('inv_op_pv_distribucion')
            ->whereIn('item_hash', $hashes)
            ->get(['item_hash', 'cantidad_asignada', 'cantidad_teorica']);
    
        // Creamos una colección de los item_hashes encontrados en la base de datos
        $encontrados = $registros->pluck('item_hash')->toArray();
    
        // Si algún item_id del array original no está en los encontrados → retornar true
        $faltantes = array_diff($hashes, $encontrados);
        if (!empty($faltantes)) {
            return true;
        }
    
        // Si alguno tiene cantidad_asignada < cantidad_teorica → retornar true
        foreach ($registros as $registro) {
            if ($registro->cantidad_asignada < $registro->cantidad_teorica) {
                return true;
            }
        }
    
        // Todo está completo
        return false;
    }
    
    public static function cantidadAsignada($numeroOp, $numeroPv, $hash)
    {
        return self::where('op_id', $numeroOp)
            ->where('pv_id', $numeroPv)
            ->where('item_hash', $hash)
            ->sum('cantidad_asignada');
    }
    
    public static function registrarAsignacionPVs($opCodigo, $pvCodigo, $cliente, $items, $usuario)
    {
        foreach ($items as $item) {
            $oc_id        = $item['oc_cliente'];
            $cliente      = $item['cliente'];
            $itemHash     = $item['hashes'];
            $referencia   = $item['referencia'];
            $idItem       = $item['f120_id'] ?? null;
            $descripcion  = $item['descripcion'];
            $idColor      = $item['id_color'];
            $idTalla      = $item['id_talla'];
            $cantidad     = $item['cantidad_a_asignar'];
            $cantidad_t   = $item['cantidad'];
    
            // 1️⃣ UPDATE si existe, sino INSERT en distribucion
            $existente = DB::connection('terminacion_empaque')
                ->table('inv_op_pv_distribucion')
                ->where('op_id', $opCodigo)
                ->where('pv_id', $pvCodigo)
                ->where('item_hash', $itemHash)
                ->first();
    
            if ($existente) {
                DB::connection('terminacion_empaque')
                    ->table('inv_op_pv_distribucion')
                    ->where('op_id', $opCodigo)
                    ->where('pv_id', $pvCodigo)
                    ->where('item_hash', $itemHash)
                    ->update([
                        'cantidad_asignada'     => DB::raw("cantidad_asignada + {$cantidad}"),
                        'registrado_por'        => $usuario,
                        'fecha_asignacion'      => now()
                    ]);
            } else {
                DB::connection('terminacion_empaque')
                    ->table('inv_op_pv_distribucion')
                    ->insert([
                        'op_id'                 => $opCodigo,
                        'pv_id'                 => $pvCodigo,
                        'oc_id'                 => $oc_id,
                        'cliente'               => $cliente,
                        'item_hash'             => $itemHash,
                        'referencia'            => $referencia,
                        'id_item'               => $idItem,
                        'descripcion'           => $descripcion,
                        'id_color'              => $idColor,
                        'id_talla'              => $idTalla,
                        'cantidad_asignada'     => $cantidad,
                        'cantidad_teorica'      => $cantidad_t,
                        'registrado_por'        => $usuario,
                        'fecha_asignacion'      => now()
                    ]);
            }
    
            // 2️⃣ Insertar SIEMPRE en historial de movimientos
            DB::connection('terminacion_empaque')
                ->table('inv_historial_movimientos')
                ->insert([
                    'tipo'          => 'distribucion',
                    'op_codigo'     => $opCodigo,
                    'pv_codigo'     => $pvCodigo,
                    'item_hash'     => $itemHash,
                    'referencia'    => $referencia,
                    'id_item'       => $idItem,
                    'descripcion'   => $descripcion,
                    'id_color'      => $idColor,
                    'id_talla'      => $idTalla,
                    'cantidad'      => $cantidad,
                    'usuario'       => $usuario,
                    'fecha_registro'=> now()
                ]);
        }
    }
}
