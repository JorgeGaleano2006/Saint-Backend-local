<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TerminacionEmpaqueInvOpRecepcion extends Model
{
    protected $connection = 'terminacion_empaque';
    protected $table = 'inv_op_recepciones';
    public $timestamps = false;

    protected $fillable = [
        'op_codigo',
        'item_hash',
        'referencia',
        'descripcion',
        'id_color',
        'id_talla',
        'cantidad_recibida',
        'precio_unitario',
        'usuario',
        'ubicacion',
        'comentario',
        'fecha_registro'
    ];

    public static function registrarRecepcion($opCodigo, $items, $usuario)
    {
        DB::connection('terminacion_empaque')->transaction(function () use ($opCodigo, $items, $usuario) {
            foreach ($items as $it) {
                if (empty($it['cantidad_recibida']) || $it['cantidad_recibida'] <= 0) {
                    continue;
                }
    
                $ubicacion = $it['ubicacion'] ?? 'Empaque';
                $comentario = $it['comentario'] ?? null;
    
                if ($ubicacion === 'Empaque') {
                    // === Caso normal: tabla principal ===
                    DB::connection('terminacion_empaque')
                        ->table('inv_op_recepciones')
                        ->updateOrInsert(
                            [
                                'op_codigo' => $opCodigo, 
                                'item_hash' => $it['hash'],
                                'ubicacion' => $ubicacion
                            ],
                            [
                                'referencia'        => $it['referencia'],
                                'id_item'           => $it['id_item'],
                                'descripcion'       => $it['descripcion'],
                                'id_color'          => $it['id_color'],
                                'id_talla'          => $it['id_talla'],
                                'cantidad_recibida' => DB::raw('COALESCE(cantidad_recibida, 0) + '.$it['cantidad_recibida']),
                                'precio_unitario'   => $it['precio_unitario'],
                                'comentario'        => $comentario,
                                'usuario'           => $usuario,
                                'fecha_registro'    => Carbon::now('America/Bogota'),
                            ]
                        );
    
                } else {
                    // === Caso: ubicación distinta ===
                    $tabla = DB::connection('terminacion_empaque')->table('inv_op_recepciones_distintas');
    
                    // Verificar si ya existe registro activo en esa ubicación
                    $existe = $tabla->where([
                            ['op_codigo', '=', $opCodigo],
                            ['item_hash', '=', $it['hash']],
                            ['ubicacion', '=', $ubicacion],
                            ['activo', '=', 1]
                        ])->first();
    
                    if ($existe) {
                        // Si existe, sumar la cantidad
                        $tabla->where('id', $existe->id)
                              ->update([
                                  'cantidad_recibida' => DB::raw('cantidad_recibida + '.$it['cantidad_recibida']),
                                  'comentario'        => $comentario,
                                  'usuario'           => $usuario,
                                  'fecha_registro'    => Carbon::now('America/Bogota')
                              ]);
                    } else {
                        // Inactivar otros registros previos del mismo item
                        $tabla->where([
                            ['op_codigo', '=', $opCodigo],
                            ['item_hash', '=', $it['hash']],
                            ['activo', '=', 1]
                        ])->update(['activo' => 0]);
    
                        // Crear nuevo registro en la nueva ubicación
                        $tabla->insert([
                            'op_codigo'        => $opCodigo,
                            'item_hash'        => $it['hash'],
                            'referencia'       => $it['referencia'],
                            'id_item'          => $it['id_item'],
                            'descripcion'      => $it['descripcion'],
                            'id_color'         => $it['id_color'],
                            'id_talla'         => $it['id_talla'],
                            'cantidad_recibida'=> $it['cantidad_recibida'],
                            'precio_unitario'  => $it['precio_unitario'],
                            'comentario'       => $comentario,
                            'usuario'          => $usuario,
                            'ubicacion'        => $ubicacion,
                            'activo'           => 1,
                            'fecha_registro'   => Carbon::now('America/Bogota')
                        ]);
                    }
                }
    
                // === Registrar en histórico ===
                TerminacionEmpaqueInvHistoricoMovimiento::on('terminacion_empaque')->create([
                    'tipo'        => 'recepcion',
                    'op_codigo'   => $opCodigo,
                    'pv_codigo'   => null,
                    'item_hash'   => $it['hash'],
                    'referencia'  => $it['referencia'],
                    'id_item'     => $it['id_item'],
                    'descripcion' => $it['descripcion'],
                    'id_color'    => $it['id_color'],
                    'id_talla'    => $it['id_talla'],
                    'cantidad'    => $it['cantidad_recibida'],
                    'usuario'     => $usuario
                ]);
            }
    
            // === OP activa solo la primera vez ===
            DB::connection('terminacion_empaque')
                ->table('ops')
                ->updateOrInsert(
                    ['op_codigo' => $opCodigo],
                    [
                        'estado'            => 'activo',
                        'usuario_activacion'=> $usuario,
                        'fecha_activacion'  => Carbon::now()
                    ]
                );
        });
    }
    
    public static function actualizarUbicacion(
        string $opCodigo, 
        string $itemHash,
        string $id_item,
        string $nuevaUbicacion, 
        string $ubicacionActual, 
        ?string $comentario = null, 
        int $cantidad = 0,
        int $usuario
    ) {
        $ahora = Carbon::now();
    
        // 1. Traemos el registro actual
        $registroActual = DB::connection('terminacion_empaque')
            ->table('inv_op_recepciones_distintas')
            ->where('op_codigo', $opCodigo)
            ->where('item_hash', $itemHash)
            ->where('ubicacion', $ubicacionActual)
            ->where('activo', 1)
            ->first();
    
        if (!$registroActual) {
            throw new \Exception("No existe registro activo para mover");
        }
    
        // 2. Desactivar el registro actual
        DB::connection('terminacion_empaque')
            ->table('inv_op_recepciones_distintas')
            ->where('op_codigo', $opCodigo)
            ->where('item_hash', $itemHash)
            ->where('ubicacion', $ubicacionActual)
            ->update([
                'activo' => 0,
                'fecha_actualizacion' => $ahora,
            ]);
    
        // 3. Si la nueva ubicación es Empaque => tabla principal
        if ($nuevaUbicacion === 'Empaque') {
            $existe = DB::connection('terminacion_empaque')
                ->table('inv_op_recepciones')
                ->where('op_codigo', $opCodigo)
                ->where('item_hash', $itemHash)
                ->first();
    
            if ($existe) {
                // sumamos cantidades
                DB::connection('terminacion_empaque')
                    ->table('inv_op_recepciones')
                    ->where('op_codigo', $opCodigo)
                    ->where('item_hash', $itemHash)
                    ->update([
                        'cantidad_recibida' => DB::raw("cantidad_recibida + $cantidad"),
                        'comentario' => $comentario,
                        'fecha_registro' => $ahora,
                    ]);
            } else {
                // nuevo registro en principal usando datos del registro desactivado
                DB::connection('terminacion_empaque')
                    ->table('inv_op_recepciones')
                    ->insert([
                        'op_codigo'        => $opCodigo,
                        'item_hash'        => $itemHash,
                        'referencia'       => $registroActual->referencia,
                        'id_item'          => $registroActual->id_item,
                        'descripcion'      => $registroActual->descripcion,
                        'id_talla'         => $registroActual->id_talla,
                        'id_color'         => $registroActual->id_color,
                        'ubicacion'        => $nuevaUbicacion,
                        'cantidad_recibida'=> $cantidad,
                        'comentario'       => $comentario,
                        'fecha_registro'   => $ahora,
                        'usuario'          => $usuario
                    ]);
            }
        } else {
            // 4. Registrar / actualizar en la tabla inv_op_recepciones_distintas
            $existe = DB::connection('terminacion_empaque')
                ->table('inv_op_recepciones_distintas')
                ->where('op_codigo', $opCodigo)
                ->where('item_hash', $itemHash)
                ->where('ubicacion', $nuevaUbicacion)
                ->where('activo', 1)
                ->first();
    
            if ($existe) {
                // sumamos cantidades
                DB::connection('terminacion_empaque')
                    ->table('inv_op_recepciones_distintas')
                    ->where('op_codigo', $opCodigo)
                    ->where('item_hash', $itemHash)
                    ->where('ubicacion', $nuevaUbicacion)
                    ->update([
                        'cantidad_recibida' => DB::raw("cantidad_recibida + $cantidad"),
                        'comentario'        => $comentario,
                        'fecha_registro'    => $ahora,
                    ]);
            } else {
                // nuevo registro en distintas usando datos del viejo
                DB::connection('terminacion_empaque')
                    ->table('inv_op_recepciones_distintas')
                    ->insert([
                        'op_codigo'        => $opCodigo,
                        'item_hash'        => $itemHash,
                        'referencia'       => $registroActual->referencia,
                        'id_item'          => $registroActual->id_item,
                        'descripcion'      => $registroActual->descripcion,
                        'id_talla'         => $registroActual->id_talla,
                        'id_color'         => $registroActual->id_color,
                        'ubicacion'        => $nuevaUbicacion,
                        'cantidad_recibida'=> $cantidad,
                        'comentario'       => $comentario,
                        'fecha_registro'   => $ahora,
                        'activo'           => 1,
                        'usuario'          => $usuario
                    ]);
            }
        }
    }
        
    public static function obtenerCantidadesPorHashes(string $opCodigo, array $hashes): array
    {
        $conn = DB::connection('terminacion_empaque');
    
        // 1) Obtener recepciones de la tabla principal
        $recepcionesMain = $conn->table('inv_op_recepciones')
            ->select('item_hash', 'cantidad_recibida', 'ubicacion', 'comentario')
            ->where('op_codigo', $opCodigo)
            ->whereIn('item_hash', $hashes)
            ->get();
    
        // 2) Obtener recepciones activas de la tabla de "distintas"
        $recepcionesDistintas = $conn->table('inv_op_recepciones_distintas')
            ->select('item_hash', 'cantidad_recibida', 'ubicacion', 'comentario')
            ->where('op_codigo', $opCodigo)
            ->where('activo', 1)
            ->whereIn('item_hash', $hashes)
            ->get();
    
        // 3) Unir ambas colecciones
        $recepciones = $recepcionesMain->merge($recepcionesDistintas);
    
        $resultado = [];
    
        foreach ($hashes as $hash) {
            // Filtrar por hash
            $recepcionesDelHash = $recepciones->where('item_hash', $hash);
    
            // Sumar cantidades (usar float porque son decimales)
            $cantidadTotal = (float) round($recepcionesDelHash->sum(function ($r) {
                return floatval($r->cantidad_recibida);
            }), 2);
    
            // Agrupar ubicaciones distintas a 'Empaque'
            $ubicacionesDistintas = $recepcionesDelHash
                ->filter(function ($r) {
                    return strcasecmp(trim((string)($r->ubicacion ?? '')), 'Empaque') !== 0;
                })
                ->groupBy(function ($r) {
                    return $r->ubicacion ?? 'Sin ubicación';
                })
                ->map(function ($group, $ubicacion) {
                    // Sumar cantidades por grupo de ubicación
                    $cantidad = (float) round($group->sum(function ($g) {
                        return floatval($g->cantidad_recibida);
                    }), 2);
    
                    // Concatenar comentarios únicos y no vacíos
                    $comentarios = $group->pluck('comentario')
                        ->filter()
                        ->map(function ($c) { return trim((string)$c); })
                        ->unique()
                        ->values()
                        ->all();
    
                    $comentariosStr = count($comentarios) ? implode('; ', $comentarios) : '';
    
                    return [
                        'ubicacion' => $ubicacion,
                        'cantidad'  => $cantidad,
                        'comentario'=> $comentariosStr
                    ];
                })
                ->values()
                ->toArray();
    
            $resultado[$hash] = [
                'cantidad_recibida_total' => $cantidadTotal,
                'ubicaciones_distintas'   => $ubicacionesDistintas
            ];
        }
    
        return $resultado;
    }


    public static function obtenerItemsPendientePorOP(string $opCodigo)
    {
        if (empty($opCodigo)) {
            return false;
        }
    
        $hashes = DB::connection('terminacion_empaque')
            ->table('inv_op_recepciones')
            ->where('op_codigo', $opCodigo)
            ->pluck('item_hash')
            ->filter() // elimina posibles valores nulos o vacíos
            ->toArray();
    
        return !empty($hashes) ? $hashes : false;
    }
    
    public static function verificarPendientes(array $itemsPorPV)
    {
        $resultado = [];
    
        foreach ($itemsPorPV as $pvData) {
            $numeroOp = $pvData['numero_op'] ?? null;
            $numeroPv = $pvData['numero_pv'] ?? null;
            $itemsValidados = [];
    
            foreach ($pvData['items'] as $item) {
                $hash = md5($item['f120_id'] . '|' . $item['id_color'] . '|' . $item['id_talla']);
                $item['hash'] = $hash;
    
                // Stock disponible en esta OP
                $disponible = self::where('op_codigo', $numeroOp)
                    ->where('item_hash', $hash)
                    ->sum('cantidad_recibida');
    
                // Cantidad ya asignada a esta PV
                $asignado = TerminacionEmpaqueOpPvDistribucion::cantidadAsignada(
                    $numeroOp,
                    $numeroPv,
                    $hash
                );
    
                // Cantidad teórica que debería tener esta PV
                $teorico = $item['cantidad'] ?? 0;
    
                // Pendiente = lo que queda por asignar en la OP
                $pendiente = $disponible - $asignado;
    
                // Regla de disponibilidad:
                // 1. Debe haber stock pendiente en la OP
                // 2. La PV aún no debe tener todo lo que necesita
                $disponibleFlag = ($pendiente > 0) && ($asignado < $teorico);
    
                $itemsValidados[] = [
                    'item' => $item,
                    'disponible' => $disponibleFlag,
                    'pendiente' => max(0, $pendiente),
                    'asignado' => $asignado,
                    'teorico' => $teorico
                ];
            }
    
            $resultado[] = [
                'numero_op' => $numeroOp,
                'numero_pv' => $numeroPv,
                'items_validados' => $itemsValidados
            ];
        }
    
        return $resultado;
    }
}
