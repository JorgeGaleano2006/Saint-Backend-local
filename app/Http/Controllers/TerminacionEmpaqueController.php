<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

use App\Models\TerminacionEmpaqueOps;
use App\Models\TerminacionEmpaquePVItems;
use App\Models\TerminacionEmpaqueOpsInternas;
use App\Models\TerminacionEmpaqueInvOpRecepcion;
use App\Models\TerminacionEmpaqueInvPtRecepcion;
use App\Models\TerminacionEmpaqueRegistroEmpaque;
use App\Models\TerminacionEmpaqueOpPvDistribucion;
use App\Models\TerminacionEmpaqueInvHistoricoMovimiento;
use App\Models\TerminacionEmpaqueEmpacadorPvAsignaciones;

class TerminacionEmpaqueController extends Controller
{
    /**
     * 1. Listar OPs activas (últimos 2 años)
     */
    public function listarOpsActivas()
    {
        // $ops = DB::connection('siesa') // Conexión a SIESA
        //     ->table('t850_mf_op_docto')
        //     ->select([
        //         'f850_consec_docto AS id',
        //         'f850_consec_docto AS codigo',
        //         'f850_fecha_ts_creacion AS fecha_creacion',
        //         'f850_ind_estado AS estado'
        //     ])
        //     ->where('f850_id_cia', '01')
        //     ->whereNotIn('f850_ind_estado', [9])
        //     ->where('f850_fecha_ts_creacion', '>=', DB::raw('DATEADD(YEAR, -2, GETDATE())'))
        //     ->orderByDesc('f850_fecha_ts_creacion')
        //     ->get();

        // return response()->json($ops);
        
        $ops = TerminacionEmpaqueOps::listarActivasUltimos2Anios();
        return response()->json($ops);
    }

    /**
     * 2. Listar PVs asociadas a una OP por su número
     */
    // public function listarPVsPorOP($numeroOp)
    // {
    //     $pvs = DB::connection('siesa')
    //         ->table('t850_mf_op_docto AS op')
    //         ->leftJoin('t430_cm_pv_docto AS pv', 'pv.f430_rowid', '=', 'op.f850_rowid_pv_docto')
    //         ->select([
    //             DB::raw("ISNULL(op.f850_referencia_3, '') AS pvs"),
    //             'op.f850_consec_docto AS numero_op',
    //             'op.f850_fecha_ts_creacion AS fecha_op',
    //             'op.f850_ind_estado AS estado_op',
    //             'pv.f430_consec_docto AS numero_pv',
    //             'pv.f430_fecha_ts_creacion AS fecha_pv'
    //         ])
    //         ->where('op.f850_consec_docto', $numeroOp)
    //         ->get();
    
    //     return response()->json($pvs);
    // }
    
    //     public function listarPVsPorOP($numeroOp)
    // {
    //     $pvs = DB::connection('siesa')
    //         ->table('t850_mf_op_docto AS op')
    //         ->leftJoin('t430_cm_pv_docto AS pv', 'pv.f430_rowid', '=', 'op.f850_rowid_pv_docto')
    //         ->select([
    //             'op.*', // <- Esto traerá TODAS las columnas de la OP
    //             'pv.f430_consec_docto AS numero_pv',
    //             'pv.f430_fecha_ts_creacion AS fecha_pv'
    //         ])
    //         ->where('op.f850_consec_docto', $numeroOp)
    //         ->get();
    
    //     return response()->json($pvs);
    // }
    
    public function listarPVsPorOP($numeroOp)
    {
        // Obtenemos los datos desde el modelo (solo SQL)
        $resultado = TerminacionEmpaqueOps::listarPVsPorOP($numeroOp);
    
        if (!$resultado || empty($resultado[0]->pvs)) {
            return response()->json([
                'numero_op' => $resultado[0]->numero_op ?? $numeroOp,
                'fecha_op' => $resultado[0]->fecha_op ?? null,
                'estado_op' => $resultado[0]->estado_op ?? null,
                'pvs' => ''
            ]);
        }
    
        $pvsTexto = $resultado[0]->pvs;
        $listaPvs = [];
    
        // --- 🔹 Normalización del texto ---
        $pvsTexto = str_replace(['–', '—'], '…', $pvsTexto); // Normaliza guiones largos
        $pvsTexto = str_replace('PV', '', strtoupper($pvsTexto)); // Quita PV
        $pvsTexto = preg_replace('/\s+/', '', $pvsTexto); // Quita espacios
    
        // --- 🔹 Verificar si contiene rango con "…"
        if (strpos($pvsTexto, '…') !== false) {
            // Ejemplo: "50090,50091…50157"
            $partes = explode('…', $pvsTexto);
    
            // Buscar el último número antes de los puntos suspensivos
            $antes = explode(',', trim($partes[0]));
            $inicio = intval(end($antes));
            $fin = intval(trim($partes[1]));
    
            // Agregar las anteriores al rango (si existen antes de los puntos)
            foreach ($antes as $num) {
                $num = intval(trim($num));
                if ($num && $num < $inicio) {
                    $listaPvs[] = $num;
                }
            }
    
            // Expandir el rango completo
            if ($inicio && $fin && $inicio <= $fin) {
                for ($i = $inicio; $i <= $fin; $i++) {
                    $listaPvs[] = $i;
                }
            }
        } else {
            // 🔹 Si no hay rango, solo separar por coma
            $valores = explode(',', $pvsTexto);
            foreach ($valores as $valor) {
                $num = intval(trim($valor));
                if ($num) {
                    $listaPvs[] = $num;
                }
            }
        }
    
        // 🔹 Eliminar duplicados y ordenar
        $listaPvs = array_unique($listaPvs);
        sort($listaPvs, SORT_NUMERIC);
    
        // --- 🔹 Formatear el texto final ---
        $pvsString = '';
        if (count($listaPvs) > 0) {
            $pvsString = 'PV ' . implode(', ', $listaPvs);
        }
    
        // --- 🔹 Retornar respuesta final ---
        return response()->json([
            'numero_op' => $resultado[0]->numero_op,
            'fecha_op' => $resultado[0]->fecha_op,
            'estado_op' => $resultado[0]->estado_op,
            'pvs' => $pvsString
        ]);
    }

    /**
     * 3. Listar ítems de una PV
     */
    public function listarItemsDePV($numeroPV)
    {
        // $items = DB::connection('siesa')
        //     ->table('t431_cm_pv_movto AS pv')
        //     ->join('t121_mc_items_extensiones AS ext', 'ext.f121_rowid', '=', 'pv.f431_rowid_item_ext')
        //     ->join('t120_mc_items AS i', 'i.f120_rowid', '=', 'ext.f121_rowid_item')
        //     ->leftJoin('t117_mc_extensiones1_detalle AS col_det', function($join) {
        //         $join->on('col_det.f117_id', '=', 'ext.f121_id_ext1_detalle')
        //              ->on('col_det.f117_id_extension1', '=', 'ext.f121_id_extension1');
        //     })
        //     ->leftJoin('t119_mc_extensiones2_detalle AS tal_det', function($join) {
        //         $join->on('tal_det.f119_id', '=', 'ext.f121_id_ext2_detalle')
        //              ->on('tal_det.f119_id_extension2', '=', 'ext.f121_id_extension2');
        //     })
        //     ->where('pv.f431_rowid_pv_docto', function($query) use ($numeroPV) {
        //         $query->select('f430_rowid')
        //             ->from('t430_cm_pv_docto')
        //             ->where('f430_consec_docto', $numeroPV)
        //             ->limit(1);
        //     })
        //     ->orderBy('i.f120_referencia')
        //     ->orderBy('tal_det.f119_descripcion')
        //     ->orderBy('col_det.f117_descripcion')
        //     ->select([
        //         'pv.f431_rowid_pv_docto AS numero_pv',
        //         'i.f120_id',
        //         'i.f120_referencia AS referencia',
        //         'i.f120_descripcion_corta AS descripcion',
        //         'pv.f431_rowid_item_ext',
        //         'col_det.f117_id AS id_color',
        //         'col_det.f117_descripcion AS color',
        //         'tal_det.f119_id AS id_talla',
        //         'tal_det.f119_descripcion AS talla',
        //         'pv.f431_id_unidad_medida AS unidad_medida',
        //         'pv.f431_cant_pedida_base AS cantidad',
        //         'pv.f431_precio_unitario_base AS precio_unitario',
        //         'pv.f431_vlr_neto AS valor_total',
        //         'pv.f431_rowid_bodega AS bodega'
        //     ])
        //     ->get();
    
        // return response()->json($items);
        
        $items = TerminacionEmpaquePVItems::listarItemsDePV($numeroPV);
        
        return response()->json($items);
    }
    
    public function listarItemsDePVOP($numeroOp, $numeroPV)
    {
        $items = TerminacionEmpaquePVItems::listarItemsDePV($numeroPV);
        $hashes = [];

        foreach ($items as &$item) {
            $item['numero_op'] = $numeroOp;
            $item['hashes'] = $this->construirHash(
                $item['f120_id'],
                $item['id_color'],
                $item['id_talla']
            );
            $hashes[] = $item['hashes'];
        }

        $cantidadesRecibidas = TerminacionEmpaqueInvOpRecepcion::obtenerCantidadesPorHashes($numeroOp, $hashes);

        foreach ($items as &$item) {
            $hash = $item['hashes'];
        
            // Evitar conflicto de clave previa
            unset($item['cantidad_recibida_total'], $item['ubicaciones_distintas']);
        
            if (isset($cantidadesRecibidas[$hash])) {
                $item['cantidad_recibida_total'] = (int) $cantidadesRecibidas[$hash]['cantidad_recibida_total'];
                $item['ubicaciones_distintas'] = $cantidadesRecibidas[$hash]['ubicaciones_distintas'];
            } else {
                $item['cantidad_recibida_total'] = 0;
                $item['ubicaciones_distintas'] = [];
            }
        
            unset($item['cantidad_recibida']); // si ya no lo necesitas
        
            $item['cantidad_asignada'] = TerminacionEmpaqueOpPvDistribucion::obtenerDistribucionDeItems(
                $numeroPV,
                $hash
            );
        }

        return response()->json($items);
    }
    
    /**
     * 4. Recepcion de ítems de una OP
     */
    public function registrarRecepcionItems(Request $request)
    {
        $validated = $request->validate([
            'op_codigo' => 'required|string',
            'usuario'   => 'required|integer',
            'items'     => 'required|array'
        ]);

        try {
            TerminacionEmpaqueInvOpRecepcion::registrarRecepcion(
                $validated['op_codigo'],
                $validated['items'],
                $validated['usuario']
            );

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage()
            ], 500);
        }
    }
    
    public function registrarRecepcionItemsPT(Request $request)
    {
        $validated = $request->validate([
            'pt_codigo' => 'required|string',
            'pv_codigo' => 'required|string',
            'usuario'   => 'required|integer',
            'items'     => 'required|array'
        ]);

        try {
            TerminacionEmpaqueInvPtRecepcion::registrarRecepcionPT(
                $validated['pt_codigo'],
                $validated['pv_codigo'],
                $validated['items'],
                $validated['usuario']
            );

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage()
            ], 500);
        }
    }
    
    public function verificarEstadoPTs(Request $request)
    {
        // Validación básica: pts debe ser array (si prefieres, valida con $request->validate)
        $validator = Validator::make($request->all(), [
            'pts' => 'required|array',
            'pts.*' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Entrada inválida', 'details' => $validator->errors()], 422);
        }

        // Sanitizar y normalizar (closure compatible con PHP < 7.4)
        $pts = collect($request->input('pts', []))
            ->map(function ($pt) {
                // eliminar tags, trim y dejar sólo números (opcional)
                $clean = trim(strip_tags($pt));
                // Si quieres dejar sólo dígitos:
                $clean = preg_replace('/\D+/', '', $clean);
                return $clean;
            })
            ->filter(function ($pt) {
                return $pt !== '' && $pt !== null;
            })
            ->unique()
            ->values()
            ->toArray();

        if (empty($pts)) {
            return response()->json([], 200);
        }

        // Llamada al Model (implementa la lógica SQL dentro del Model)
        $resultados = TerminacionEmpaqueInvPtRecepcion::verificarEstadoPTs($pts);

        return response()->json($resultados, 200);
    }
    
    public function actualizarUbicacion(Request $request)
    {
        $validated = $request->validate([
            'op_codigo'         => 'required|string',
            'item_hash'         => 'required|string',
            'id_item'           => 'required|string',
            'ubicacion'         => 'required|string',
            'ubicacion_actual'  => 'required|string',
            'comentario'        => 'nullable|string',
            'cantidad_recibida' => 'required|int',
            'usuario'           => 'required|int'
        ]);
    
        try {
            TerminacionEmpaqueInvOpRecepcion::actualizarUbicacion(
                $validated['op_codigo'],
                $validated['item_hash'],
                $validated['id_item'],
                $validated['ubicacion'],
                $validated['ubicacion_actual'],
                $validated['comentario'] ?? null,
                $validated['cantidad_recibida'],
                $validated['usuario']
            );
    
            return response()->json(['success' => true]);
    
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage()
            ], 500);
        }
    }
    
    private function construirHash($f120_id, $id_color, $id_talla)
    {
        // Limpiar espacios al inicio y al final
        $f120_id  = trim((string) $f120_id);
        $id_color = trim((string) $id_color);
        $id_talla = trim((string) $id_talla);
    
        // Eliminar todos los espacios en medio
        $id_color = preg_replace('/\s+/', '', $id_color);
        $id_talla = preg_replace('/\s+/', '', $id_talla);
    
        return md5($f120_id . '|' . $id_color . '|' . $id_talla);
    }
    
    public function generarHashes(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.f120_id' => 'required',
            'items.*.id_color' => 'required',
            'items.*.id_talla' => 'required',
        ]);
    
        $hashes = [];
        foreach ($request->items as $item) {
            $hashes[] = [
                'hash' => $this->construirHash(
                    $item['f120_id'],
                    $item['id_color'],
                    $item['id_talla']
                )
            ];
        }
    
        return response()->json($hashes);
    }
    
    /**
     * 5. Contar las cantidades en base al hash
     */
    public function ConsultarCantidadesHash(Request $request)
    {
        $validated = $request->validate([
            'op_codigo' => 'required|string',
            'hashes'    => 'required|array'
        ]);
    
        $opCodigo = $validated['op_codigo'];
        $hashes = $validated['hashes'];
    
        try {
            $cantidades = TerminacionEmpaqueInvOpRecepcion::obtenerCantidadesPorHashes(
              $opCodigo,
              $hashes
            );
            
            return response()->json([
                'success' => true,
                'data' => $cantidades
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function ConsultarCantidadesPtHash(Request $request)
    {
        $validated = $request->validate([
            'pt_codigo' => 'required|string',
            'hashes'    => 'required|array'
        ]);
    
        $ptCodigo = $validated['pt_codigo'];
        $hashes = $validated['hashes'];
    
        try {
            $cantidades = TerminacionEmpaqueInvPtRecepcion::obtenerCantidadesPtPorHashes(
              $ptCodigo,
              $hashes
            );
            
            return response()->json([
                'success' => true,
                'data' => $cantidades
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 6. Obtener la Ops pendientes de manera local
     */
    public function ObtenerOPsPendientes()
    {
        $ops = TerminacionEmpaqueOpsInternas::opsPendientes();
        return response()->json($ops);
    }
    
    /**
     * 7. Obtener de que OP y PV son los items sin asignar
     */
    public function obtenerOPsConItemsPendiente(Request $request) 
    {
        $validated = $request->validate([
            'op_codigo' => 'required|string'
        ]);
        
        $opCodigo = $validated['op_codigo'];
    
        try {
            $items = TerminacionEmpaqueInvOpRecepcion::obtenerItemsPendientePorOP($opCodigo);
    
            // Si no hay items (es decir, retorno fue false)
            if ($items === false) {
                return response()->json([
                    'success' => true,
                    'data' => false
                ]);
            }
    
            // Si hay hashes, buscar los ya distribuidos
            $resultado = TerminacionEmpaqueOpPvDistribucion::existenItemsPendientes($items);
    
            return response()->json([
                'success' => true,
                'data' => $resultado
            ]);
    
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage()
            ], 500);
        }
    }
    
    public function obtenerItemsPendientesPorPV(Request $request)
    {
        $itemsPorPV = $request->input('items_por_pv');
    
        try {
            $resultado = TerminacionEmpaqueInvOpRecepcion::verificarPendientes($itemsPorPV);
    
            return response()->json([
                'success' => true,
                'data' => $resultado
            ]);
    
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage()
            ], 500);
        }
    }
    
    public function registrarAsignaciones(Request $request)
    {
        TerminacionEmpaqueOpPvDistribucion::registrarAsignacionPVs(
            $request->op_codigo,
            $request->pv_id,
            $request->items,
            $request->usuario
        );
    
        return response()->json(['message' => 'Asignaciones registradas correctamente']);
    }

    /**
     * Obtener PVs pendientes (sin asignar a empacadores)
     */
    public function obtenerPVsPendientes()
    {
        try {
            $pvs = TerminacionEmpaqueEmpacadorPvAsignaciones::obtenerPVsPendientes();
            return response()->json($pvs);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obtener asignaciones múltiples de empacadores
     */
    public function obtenerAsignacionesMultiples(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer'
        ]);
    
        try {
            $asignaciones = TerminacionEmpaqueEmpacadorPvAsignaciones::obtenerAsignacionesMultiples($validated['ids']);
            return response()->json($asignaciones);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Asignar una PV a un empacador
     */
    public function asignarPVAEmpacador(Request $request)
    {
        $validated = $request->validate([
            'empacador_id' => 'required|integer',
            'pv_codigo' => 'required|string'
        ]);
    
        try {
            $resultado = TerminacionEmpaqueEmpacadorPvAsignaciones::asignarPV(
                $validated['empacador_id'],
                $validated['pv_codigo']
            );
    
            if ($resultado) {
                return response()->json([
                    'success' => true,
                    'message' => 'PV asignada correctamente'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'No se pudo asignar la PV'
                ], 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 200);
        }
    }
    
    /**
     * Desasignar una PV de un empacador
     */
    public function desasignarPV(Request $request)
    {
        $validated = $request->validate([
            'empacador_id' => 'required|integer',
            'pv_codigo' => 'required|string'
        ]);
    
        try {
            $resultado = TerminacionEmpaqueEmpacadorPvAsignaciones::desasignarPV(
                $validated['empacador_id'],
                $validated['pv_codigo']
            );
    
            if ($resultado) {
                return response()->json([
                    'success' => true,
                    'message' => 'PV desasignada correctamente'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'No se pudo desasignar la PV'
                ], 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obtener PVs asignadas a un empacador con información de empaque
     */
    public function obtenerPVsAsignadas($empacadorId)
    {
        try {
            $pvs = TerminacionEmpaqueEmpacadorPvAsignaciones::obtenerAsignacionesMultiples([$empacadorId]);
            
            return response()->json($pvs);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obtener ítems de una PV con información de empaque
     */
    public function obtenerItemsPVEmpaque(Request $request)
    {
        $validated = $request->validate([
            'empacador_id' => 'required|integer',
            'pv_codigo'    => 'required|string'
        ]);
    
        try {
            $pvCodigo    = $validated['pv_codigo'];
            $empacadorId = $validated['empacador_id'];
    
            // ===================== ITEMS DE LA PV =====================
            $itemsPVRaw = TerminacionEmpaquePVItems::listarItemsDePV($pvCodigo);
            
            // 👇 Agrupar y consolidar items duplicados por hash
            $itemsPV = $this->consolidarItemsDuplicados($itemsPVRaw);
    
            $itemsDistribucionDB = collect(TerminacionEmpaqueOpPvDistribucion::obtenerItemsPVConDistribucion($pvCodigo, $empacadorId))
                ->keyBy('item_hash');
    
            $itemsDistribucion = $itemsPV->map(function ($item) use ($itemsDistribucionDB) {
                $hash = $this->construirHash($item->f120_id, $item->id_color, $item->id_talla);
    
                $dist = $itemsDistribucionDB->get($hash);
    
                return [
                    'item_hash'   => $hash,
                    'id_item'     => $item->f120_id,
                    'referencia'  => $item->referencia,
                    'descripcion' => $item->descripcion,
                    'id_color'    => $item->id_color,
                    'id_talla'    => $item->id_talla,
                    'asignado'    => $dist->asignado ?? 0,
                    'empacado'    => $dist->empacado ?? 0,
                    'teorico'     => $item->cantidad, // Ya consolidada
                ];
            });
    
            // ===================== ITEMS DE LA PT =====================
            $ptCodigos = TerminacionEmpaqueInvPtRecepcion::where('pv_codigo', $pvCodigo)
                ->pluck('pt_codigo')
                ->unique();
    
            $itemsRecepcion = collect();
    
            foreach ($ptCodigos as $ptCodigo) {
                // 👇 También consolidar los items de PT
                $itemsPTRaw = TerminacionEmpaquePVItems::listarItemsDePV($ptCodigo);
                $itemsPT = $this->consolidarItemsDuplicados($itemsPTRaw);
    
                $itemsRecepcionDB = collect(TerminacionEmpaqueInvPtRecepcion::obtenerItemsPorPV($pvCodigo))
                    ->keyBy('item_hash');
    
                $itemsPT->each(function ($item) use ($itemsRecepcionDB, $ptCodigo, $pvCodigo, $itemsRecepcion) {
                    $hash = $this->construirHash($item->f120_id, $item->id_color, $item->id_talla);
    
                    $rec = $itemsRecepcionDB->get($hash);
    
                    $itemsRecepcion->push([
                        'pt_codigo'   => $ptCodigo,
                        'pv_codigo'   => $pvCodigo,
                        'item_hash'   => $hash,
                        'id_item'     => $item->f120_id,
                        'referencia'  => $item->referencia,
                        'descripcion' => $item->descripcion,
                        'id_color'    => $item->id_color,
                        'id_talla'    => $item->id_talla,
                        'asignado'    => $rec->asignado ?? 0,
                        'empacado'    => $rec->empacado ?? 0,
                        'teorico'     => $item->cantidad, // Ya consolidada
                        'ubicacion'   => $rec->ubicacion ?? null,
                        'comentarios' => $rec->comentarios ?? '',
                    ]);
                });
            }
    
            return response()->json([
                'success'            => true,
                'items_distribucion' => $itemsDistribucion,
                'items_recepcion'    => $itemsRecepcion
            ]);
    
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Consolida items duplicados sumando sus cantidades
     */
    private function consolidarItemsDuplicados($items)
    {
        return collect($items)->groupBy(function ($item) {
            // Agrupar por el hash único del item
            return $this->construirHash($item->f120_id, $item->id_color, $item->id_talla);
        })->map(function ($grupo) {
            // Para cada grupo, tomar el primer item y sumar todas las cantidades
            $itemBase = $grupo->first();
            $cantidadTotal = $grupo->sum('cantidad');
            
            // Crear una copia del item con la cantidad consolidada
            $itemConsolidado = clone $itemBase;
            $itemConsolidado->cantidad = $cantidadTotal;
            
            return $itemConsolidado;
        })->values(); // Reindexar la colección
    }
    
    /**
     * Registrar empaque de múltiples ítems
     */
    public function registrarEmpaque(Request $request)
    {
        $validated = $request->validate([
            'registros' => 'required|array|min:1',
            'registros.*.pv_id' => 'required|string',
            'registros.*.item_id' => 'required|string',
            'registros.*.item_hash' => 'required|string',
            'registros.*.cantidad' => 'required|numeric|min:0.01',
            'registros.*.tipo_empaque' => 'required|string|max:30',
            'registros.*.numero_empaque' => 'nullable|string|max:10',
            'registros.*.empacador_id' => 'required|integer'
        ]);
    
        try {
            $registros = $validated['registros'];
            
            // Validar que todas las cantidades sean positivas
            foreach ($registros as $registro) {
                if ($registro['cantidad'] <= 0) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Las cantidades deben ser mayores a 0'
                    ], 400);
                }
            }
    
            // Validar que el empacador tenga asignadas las PVs
            foreach ($registros as $registro) {
                $asignacion = TerminacionEmpaqueEmpacadorPvAsignaciones::where('empacador_id', $registro['empacador_id'])
                    ->where('pv_codigo', $registro['pv_id'])
                    ->exists();
                
                if (!$asignacion) {
                    return response()->json([
                        'success' => false,
                        'error' => "El empacador no tiene asignada la PV {$registro['pv_id']}"
                    ], 400);
                }
            }
    
            TerminacionEmpaqueRegistroEmpaque::registrarEmpaqueMultiple($registros);
    
            return response()->json([
                'success' => true,
                'message' => 'Empaque registrado correctamente'
            ]);
    
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error al registrar empaque: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function obtenerEmpaquesPorPV(Request $request)
    {
        $validated = $request->validate([
            'pv_codigo'     => 'required|string',
            'empacador_id'  => 'required|integer'
        ]);
    
        try {
            $pvId = $validated['pv_codigo'];
            $empacadorId = $validated['empacador_id'];
    
            $empaques = TerminacionEmpaqueRegistroEmpaque::obtenerEmpaquesPorPV($pvId, $empacadorId);
    
            // Reestructurar los datos
            $agrupados = $empaques->groupBy('numero_empaque')->map(function ($itemsPorEmpaque) {
                return [
                    'numero_empaque'    => $itemsPorEmpaque->first()->numero_empaque,
                    'tipo_empaque'      => $itemsPorEmpaque->first()->tipo_empaque,
                    'pv'                => $itemsPorEmpaque->first()->pv_id,
                    'op'                => $itemsPorEmpaque->first()->op_id,
                    'oc'                => $itemsPorEmpaque->first()->oc_id,
                    'cliente'           => $itemsPorEmpaque->first()->cliente,
                    'empacador_id'      => $itemsPorEmpaque->first()->empacador_id,
                    'items'             => $itemsPorEmpaque->map(function ($item) {
                        return [
                            'item_id'       => $item->item_id,
                            'item_hash'     => $item->item_hash,
                            'id_color'      => $item->id_color,
                            'id_talla'      => $item->id_talla,
                            'descripcion'   => $item->descripcion,
                            'cantidad'      => $item->cantidad,
                            'fecha_empaque' => $item->fecha_empaque,
                            'comentario'    => $item->comentario,
                        ];
                    })->values()
                ];
            })->values();
    
            return response()->json([
                'success' => true,
                'data'    => $agrupados
            ]);
    
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error'   => 'Error al obtener empaques: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function EmpaquesPorPV(Request $request)
    {
        $validated = $request->validate([
            'pv_codigo'     => 'required|string'
        ]);
    
        try {
            $pvId = $validated['pv_codigo'];
    
            $empaques = TerminacionEmpaqueRegistroEmpaque::EmpaquesPorPV($pvId);
    
            // Reestructurar los datos
            $agrupados = $empaques->groupBy('numero_empaque')->map(function ($itemsPorEmpaque) {
                return [
                    'numero_empaque'    => $itemsPorEmpaque->first()->numero_empaque,
                    'tipo_empaque'      => $itemsPorEmpaque->first()->tipo_empaque,
                    'pv'                => $itemsPorEmpaque->first()->pv_id,
                    'op'                => $itemsPorEmpaque->first()->op_id,
                    'oc'                => $itemsPorEmpaque->first()->oc_id,
                    'cliente'           => $itemsPorEmpaque->first()->cliente,
                    'empacador_id'      => $itemsPorEmpaque->first()->empacador_id,
                    'items'             => $itemsPorEmpaque->map(function ($item) {
                        return [
                            'item_id'       => $item->item_id,
                            'item_hash'     => $item->item_hash,
                            'id_color'      => $item->id_color,
                            'id_talla'      => $item->id_talla,
                            'descripcion'   => $item->descripcion,
                            'cantidad'      => $item->cantidad,
                            'fecha_empaque' => $item->fecha_empaque,
                            'comentario'    => $item->comentario,
                        ];
                    })->values()
                ];
            })->values();
    
            return response()->json([
                'success' => true,
                'data'    => $agrupados
            ]);
    
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error'   => 'Error al obtener empaques: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obtener historial de empaque de una PV
     */
    public function obtenerHistorialEmpaquePV($pvCodigo)
    {
        try {
            $historial = TerminacionEmpaqueRegistroEmpaque::obtenerHistorialEmpaquePV($pvCodigo);
            
            return response()->json($historial);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obtener estadísticas de empaque de un empacador
     */
    public function obtenerEstadisticasEmpacador(Request $request, $empacadorId)
    {
        $validated = $request->validate([
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio'
        ]);
    
        try {
            $estadisticas = TerminacionEmpaqueRegistroEmpaque::obtenerEstadisticasEmpacador(
                $empacadorId,
                $validated['fecha_inicio'] ?? null,
                $validated['fecha_fin'] ?? null
            );
    
            return response()->json($estadisticas);
    
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Eliminar un registro de empaque (si es necesario)
     */
    public function eliminarRegistroEmpaque($registroId)
    {
        try {
            $registro = TerminacionEmpaqueRegistroEmpaque::find($registroId);
            
            if (!$registro) {
                return response()->json([
                    'success' => false,
                    'error' => 'Registro de empaque no encontrado'
                ], 404);
            }
    
            $registro->delete();
    
            return response()->json([
                'success' => true,
                'message' => 'Registro de empaque eliminado correctamente'
            ]);
    
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obtener resumen de empaque por fechas
     */
    public function obtenerResumenEmpaqueFechas(Request $request)
    {
        $validated = $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            'empacador_id' => 'nullable|integer'
        ]);
    
        try {
            $whereClause = "WHERE reg.fecha_empaque >= ? AND reg.fecha_empaque <= ?";
            $params = [$validated['fecha_inicio'], $validated['fecha_fin']];
    
            if (isset($validated['empacador_id'])) {
                $whereClause .= " AND reg.empacador_id = ?";
                $params[] = $validated['empacador_id'];
            }
    
            $sql = "
                SELECT 
                    DATE(reg.fecha_empaque) as fecha,
                    reg.empacador_id,
                    COUNT(reg.id) as total_registros,
                    SUM(reg.cantidad) as total_cantidad,
                    COUNT(DISTINCT reg.pv_id) as pvs_diferentes,
                    COUNT(DISTINCT reg.item_id) as items_diferentes
                FROM registro_empaque reg
                {$whereClause}
                GROUP BY DATE(reg.fecha_empaque), reg.empacador_id
                ORDER BY fecha DESC, reg.empacador_id
            ";
    
            $resumen = DB::select($sql, $params);
    
            return response()->json($resumen);
    
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Validar cantidades antes de registrar empaque
     */
    public function validarCantidadesEmpaque(Request $request)
    {
        $validated = $request->validate([
            'registros' => 'required|array',
            'registros.*.pv_id' => 'required|string',
            'registros.*.item_id' => 'required|string',
            'registros.*.cantidad' => 'required|numeric|min:0.01',
            'registros.*.empacador_id' => 'required|integer'
        ]);
    
        try {
            $validaciones = [];
    
            foreach ($validated['registros'] as $registro) {
                // Obtener cantidad ya empacada
                $cantidadEmpacada = TerminacionEmpaqueRegistroEmpaque::obtenerTotalEmpacadoPorItem(
                    $registro['item_id'], 
                    $registro['pv_id']
                );
    
                // Obtener cantidad asignada
                $distribucion = TerminacionEmpaqueOpPvDistribucion::where('pv_id', $registro['pv_id'])
                    ->where('item_id', $registro['item_id'])
                    ->first();
    
                $cantidadAsignada = $distribucion ? $distribucion->cantidad_asignada : 0;
                $cantidadTeorica = $distribucion ? $distribucion->cantidad_teorica : 0;
    
                $nuevaCantidadTotal = $cantidadEmpacada + $registro['cantidad'];
    
                $validaciones[] = [
                    'item_id' => $registro['item_id'],
                    'pv_id' => $registro['pv_id'],
                    'cantidad_actual_empacada' => $cantidadEmpacada,
                    'cantidad_a_registrar' => $registro['cantidad'],
                    'nueva_cantidad_total' => $nuevaCantidadTotal,
                    'cantidad_asignada' => $cantidadAsignada,
                    'cantidad_teorica' => $cantidadTeorica,
                    'excede_asignada' => $nuevaCantidadTotal > $cantidadAsignada,
                    'excede_teorica' => $nuevaCantidadTotal > $cantidadTeorica,
                    'valido' => $nuevaCantidadTotal <= $cantidadAsignada && $nuevaCantidadTotal <= $cantidadTeorica
                ];
            }
    
            return response()->json([
                'success' => true,
                'validaciones' => $validaciones
            ]);
    
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    public function getDashboardData(Request $request)
    {
        try {
            // Preparar filtros
            $filtros = [
                'fecha_inicio'  => $request->input('fecha_inicio'),
                'fecha_fin'     => $request->input('fecha_fin'),
                'empacador'     => $request->input('empacador')
            ];
    
            $filtros = array_filter($filtros, function($value) {
                return !empty($value);
            });
    
            // 1. Obtener KPIs principales
            $kpis = TerminacionEmpaqueRegistroEmpaque::obtenerKPIs($filtros);
    
            // 2. Obtener registros por día (últimos 7 días con costo)
            $registrosPorDia = TerminacionEmpaqueRegistroEmpaque::obtenerRegistrosPorDia($filtros);
    
            // 3. Obtener estadísticas por empacador
            $porEmpacador = TerminacionEmpaqueRegistroEmpaque::obtenerEstadisticasPorEmpacador($filtros);
    
            // 4. Obtener registros detallados
            $detalle = TerminacionEmpaqueRegistroEmpaque::obtenerRegistrosDetallados($filtros);
    
            return response()->json([
                'kpis'              => $kpis,
                'registros_por_dia' => $registrosPorDia,
                'por_empacador'     => $porEmpacador,
                'detalle'           => $detalle
            ]);
    
        } catch (\Exception $e) {
            \Log::error('Error en getDashboardData: ' . $e->getMessage());
            return response()->json([
                'error' => ('Error interno del servidor'. $e->getMessage())
            ], 500);
        }
    }
    
    public function getDashboardOPs(Request $request)
    {
        $fecha = $request->input('fecha');
        $estado = $request->input('estado');
        $numeroOP = $request->input('numero_op');
    
        $ops = TerminacionEmpaqueOpsInternas::getDashboardData($fecha, $estado, $numeroOP);
        
        $ops->transform(function ($op) {
            // costo total de la OP y desglose por PV
            $costoRealData = TerminacionEmpaqueOps::costoRealDeOP($op->codigo);
        
            $op->costo_real = $costoRealData['total'];
        
            // PVs
            if (!empty($op->pvs)) {
                $pvs = [];
                foreach ($op->pvs as $pv) {
                    $codigoPv = preg_replace('/[^0-9]/', '', $pv->codigo ?? $pv['codigo']);
                    
                    // armar PV con costo_real
                    $pvData = array_merge((array) $pv, [
                        'costo_real' => $costoRealData['pvs'][$codigoPv] ?? 0,
                    ]);
        
                    // si tiene PTs, recorrerlas y agregar costo_real
                    if (!empty($pv->pts)) {
                        $pts = [];
                        foreach ($pv->pts as $pt) {
                            $codigoPt = preg_replace('/[^0-9]/', '', $pt->pt_codigo ?? $pt['pt_codigo']);
                            $pts[] = array_merge((array) $pt, [
                                'costo_real' => TerminacionEmpaqueOps::costoRealDePT($codigoPt),
                            ]);
                        }
                        $pvData['pts'] = $pts;
                    }
        
                    $pvs[] = $pvData;
                }
                $op->pvs = $pvs;
            }
        
            return $op;
        });
    
        // KPIs
        $kpis = [
            'total_ops' => $ops->count(),
            'ops_completadas' => $ops->where('progreso', '>=', 100)->count(),
            'ops_en_proceso' => $ops->where('progreso', '>', 0)->where('progreso', '<', 100)->count(),
            'ops_pendientes' => $ops->where('progreso', '<=', 0)->count(),
        ];
    
        return response()->json([
            'success' => true,
            'kpis' => $kpis,
            'ops' => $ops
        ]);
    }

    /**
     * Obtiene detalle completo de una OP
     */
    public function getDetalleCompleto($opCodigo)
    {
        $detalle = TerminacionEmpaqueOpsInternas::getDetalleCompleto($opCodigo);

        if (!$detalle) {
            return response()->json(['success' => false, 'message' => 'OP no encontrada'], 404);
        }

        return response()->json([
            'success' => true,
            'op' => $detalle['op'],
            'pvs' => $detalle['pvs'],
            'pts' => $detalle['pts'],
            'empaque_registros' => $detalle['empaque_registros']
        ]);
    }

    /**
     * Obtiene datos para generar QR de una OP
     */
    public function getQRData($opCodigo)
    {
        $qrData = TerminacionEmpaqueOpsInternas::getQRData($opCodigo);

        if (!$qrData) {
            return response()->json(['success' => false, 'message' => 'OP no encontrada'], 404);
        }

        return response()->json([
            'success' => true,
            'qr_data' => $qrData
        ]);
    }

    /**
     * Exporta QRs múltiples como ZIP
     */
    public function exportarQRs(Request $request)
    {
        $opIds = $request->input('op_ids', []);
        
        if (empty($opIds)) {
            return response()->json(['success' => false, 'message' => 'No se proporcionaron IDs de OPs'], 400);
        }

        // Aquí implementarías la lógica para generar múltiples QRs
        // y crear un archivo ZIP con todos ellos
        
        // Por ahora, retornamos un mensaje de éxito
        return response()->json([
            'success' => true,
            'message' => 'QRs exportados exitosamente',
            'ops_count' => count($opIds)
        ]);
    }

    /**
     * Actualiza el estado de una OP
     */
    public function actualizarEstado(Request $request, $opCodigo)
    {
        $nuevoEstado = $request->input('estado');
        
        $updated = TerminacionEmpaqueOpsInternas::actualizarEstado($opCodigo, $nuevoEstado);

        if ($updated) {
            return response()->json([
                'success' => true,
                'message' => 'Estado actualizado correctamente'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Error actualizando el estado'
        ], 400);
    }

    /**
     * Obtiene el progreso de empaque de una OP
     */
    public function getProgreso($opCodigo)
    {
        $progreso = TerminacionEmpaqueOpsInternas::getProgreso($opCodigo);

        return response()->json([
            'success' => true,
            'progreso' => $progreso
        ]);
    }
}

