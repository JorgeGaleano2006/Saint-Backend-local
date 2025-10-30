<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TerminacionEmpaqueInvPtRecepcion extends Model
{
    protected $connection = 'terminacion_empaque';
    protected $table = 'inv_pt_recepciones';
    public $timestamps = false;

    protected $fillable = [
        'pt_codigo',
        'pv_codigo',
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

    public static function registrarRecepcionPT($ptCodigo, $pvCodigo, $items, $usuario)
    {
        DB::connection('terminacion_empaque')->transaction(function () use ($ptCodigo, $pvCodigo, $items, $usuario) {
            foreach ($items as $it) {
                if (empty($it['cantidad_recibida']) || $it['cantidad_recibida'] <= 0) {
                    continue;
                }
    
                $ubicacion = $it['ubicacion'] ?? 'Terminacion';
                $comentario = $it['comentario'] ?? null;
    
                // 1. Acumulado por ubicación
                // Crear un hash único que incluya la ubicación para diferenciar registros
                $hashUnico = $it['hash'] . '_' . $ubicacion;
                
                DB::connection('terminacion_empaque')
                    ->table('inv_pt_recepciones')
                    ->updateOrInsert(
                        [
                            'pv_codigo' => $pvCodigo,
                            'pt_codigo' => $ptCodigo, 
                            'item_hash' => $it['hash'],
                            'ubicacion' => $ubicacion
                        ],
                        [
                            'precio_unitario'   => $it['precio_unitario'],
                            'referencia'        => $it['referencia'],
                            'id_item'           => $it['id_item'],
                            'descripcion'       => $it['descripcion'],
                            'id_color'          => $it['id_color'],
                            'id_talla'          => $it['id_talla'],
                            'cantidad_teorica'  => $it['cantidad_teorica'],
                            'cantidad_recibida' => DB::raw('COALESCE(cantidad_recibida, 0) + '.$it['cantidad_recibida']),
                            'precio_unitario'   => $it['precio_unitario'],
                            'comentario'        => $comentario,
                            'usuario'           => $usuario,
                            'ubicacion'         => $ubicacion,  // Agregar ubicación al historial
                            'comentario'        => $comentario, // Agregar comentario al historial
                            'fecha_registro'    => Carbon::now()
                        ]
                    );
    
                // 2. Historial con ubicación
                // TerminacionEmpaqueInvHistoricoMovimiento::on('terminacion_empaque')->create([
                //     'tipo'        => 'recepcion',
                //     'op_codigo'   => $opCodigo,
                //     'pv_codigo'   => null,
                //     'item_hash'   => $it['hash'],
                //     'referencia'  => $it['referencia'],
                //     'id_item'     => $it['id_item'],
                //     'descripcion' => $it['descripcion'],
                //     'id_color'    => $it['id_color'],
                //     'id_talla'    => $it['id_talla'],
                //     'cantidad'    => $it['cantidad_recibida'],
                //     'usuario'     => $usuario
                // ]);
            }
    
            // 3. Registrar OP activa solo la primera vez
            // DB::connection('terminacion_empaque')
            //     ->table('ops')
            //     ->updateOrInsert(
            //         ['op_codigo' => $opCodigo],
            //         [
            //             'estado'            => 'activo',
            //             'usuario_activacion'=> $usuario,
            //             'fecha_activacion'  => Carbon::now()
            //         ]
            //     );
        });
    }
    
    public static function obtenerCantidadesPtPorHashes(string $ptCodigo, array $hashes): array
    {
        $recepciones = DB::connection('terminacion_empaque')
            ->table('inv_pt_recepciones')
            ->select('item_hash', 'cantidad_recibida', 'ubicacion', 'comentario')
            ->where('pt_codigo', $ptCodigo)
            ->whereIn('item_hash', $hashes)
            ->get();
    
        $resultado = [];
    
        foreach ($hashes as $hash) {
            $recepcionesDelHash = $recepciones->where('item_hash', $hash);
            
            // Calcular cantidad total
            $cantidadTotal = (int) $recepcionesDelHash->sum('cantidad_recibida');
            
            // Obtener ubicaciones distintas a 'Terminacion'
            $ubicacionesDistintas = $recepcionesDelHash
                ->where('ubicacion', '!=', 'Terminacion')
                ->map(function ($recepcion) {
                    return [
                        'ubicacion' => $recepcion->ubicacion,
                        'cantidad' => (int) $recepcion->cantidad_recibida,
                        'comentario' => $recepcion->comentario
                    ];
                })
                ->groupBy('ubicacion')
                ->map(function ($grupo) {
                    $ubicacion = $grupo->first()['ubicacion'];
                    $cantidadTotal = $grupo->sum('cantidad');
                    $comentarios = $grupo->pluck('comentario')->filter()->unique()->implode('; ');
    
                    return [
                        'ubicacion' => $ubicacion,
                        'cantidad' => $cantidadTotal,
                        'comentario' => $comentarios
                    ];
                })
                ->values()
                ->toArray();
    
            $resultado[$hash] = [
                'cantidad_recibida_total' => $cantidadTotal,
                'ubicaciones_distintas' => $ubicacionesDistintas
            ];
        }
    
        return $resultado;
    }
    
    public static function verificarEstadoPTs(array $pts): array
    {
        // Consulta todas las recepciones de las PTs dadas
        $recepciones = DB::connection('terminacion_empaque')
            ->table('inv_pt_recepciones')
            ->select(
                'pt_codigo',
                DB::raw('SUM(cantidad_recibida) as total_recibida'),
                DB::raw('SUM(cantidad_teorica) as cantidad_teorica')
            )
            ->whereIn('pt_codigo', $pts)
            ->groupBy('pt_codigo')
            ->get()
            ->keyBy('pt_codigo');
    
        $resultados = [];
    
        foreach ($pts as $pt) {
            if (!isset($recepciones[$pt])) {
                // No existe en la tabla
                $resultados[$pt] = [
                    'estado' => 'sin_empezar',
                    'total_recibida' => 0,
                    'cantidad_teorica' => 0
                ];
            } else {
                $totalRecibida = (int) $recepciones[$pt]->total_recibida;
                $cantidadTeorica = (int) $recepciones[$pt]->cantidad_teorica;
    
                if ($totalRecibida === $cantidadTeorica && $cantidadTeorica > 0) {
                    $resultados[$pt] = [
                        'estado' => 'completa',
                        'total_recibida' => $totalRecibida,
                        'cantidad_teorica' => $cantidadTeorica
                    ];
                } elseif ($totalRecibida > 0) {
                    $resultados[$pt] = [
                        'estado' => 'parcial',
                        'total_recibida' => $totalRecibida,
                        'cantidad_teorica' => $cantidadTeorica
                    ];
                } else {
                    $resultados[$pt] = [
                        'estado' => 'sin_empezar',
                        'total_recibida' => 0,
                        'cantidad_teorica' => $cantidadTeorica
                    ];
                }
            }
        }
    
        return $resultados;
    }

    public static function obtenerItemsPorPV(string $pvCodigo): array
    {
        $rows = DB::connection('terminacion_empaque')
            ->table('inv_pt_recepciones')
            ->select(
                'pt_codigo',
                'pv_codigo',
                'item_hash',
                'id_item',
                'referencia',
                'descripcion',
                'id_color',
                'id_talla',
                DB::raw('SUM(cantidad_teorica) AS teorico'),
                DB::raw('SUM(cantidad_recibida) AS asignado'),
                DB::raw('MAX(ubicacion) AS ubicacion'),
                DB::raw("GROUP_CONCAT(COALESCE(comentario, '') SEPARATOR '; ') AS comentarios")
            )
            ->where('pv_codigo', $pvCodigo)
            ->groupBy(
                'pt_codigo',
                'pv_codigo',
                'item_hash',
                'id_item',
                'referencia',
                'descripcion',
                'id_color',
                'id_talla'
            )
            ->get()
            ->toArray();
    
        return $rows;
    }

}
