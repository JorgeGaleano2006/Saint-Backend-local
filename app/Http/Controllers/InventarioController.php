<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\InventarioZonas;
use App\Models\InventarioItemZonas;
use App\Models\InventarioMovimientosInventario;
use App\Models\InventarioLogs; // ✅ Modelo para registrar logs
use Illuminate\Validation\ValidationException;
use Exception;

class InventarioController extends Controller
{
    /**
     * Obtener resumen general de bodegas con cobertura de zonas
     */
    public function obtenerResumenBodegas()
    {
        try {
            $bodegas = InventarioMovimientosInventario::resumenBodegas();

            foreach ($bodegas as $bodega) {
                $itemsConZona = InventarioItemZonas::where('codigo_bodega', $bodega->codigo)
                    ->distinct('id_f400')
                    ->count('id_f400');

                $bodega->items_con_zona = $itemsConZona;
                $bodega->cobertura_zonas = $bodega->total_items > 0
                    ? round(($itemsConZona / $bodega->total_items) * 100, 2)
                    : 0;
            }

            return response()->json([
                'success' => true,
                'data' => $bodegas
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener resumen de bodegas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Listar ítems de una bodega
     */
    public function obtenerItemsPorBodega($codigoBodega)
    {
        try {
            $items = InventarioMovimientosInventario::listarPorBodega($codigoBodega);

            return response()->json([
                'success' => true,
                'data' => $items
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener ítems de la bodega',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Asignar zona a ítems (creación de relación)
     */
    public function asignarZona(Request $request)
    {
        try {
            $payload = $request->all();
            $usuarioId = $request->user()->id ?? $request->input('usuario_id');

            InventarioItemZonas::asignarZonas($payload);

            // Log de acción
            InventarioLogs::registrar(
                $usuarioId,
                'asignar_zona',
                'inventario_item_zonas',
                'Asignación de zonas a ítems',
                null,
                $payload
            );

            return response()->json([
                'success' => true,
                'message' => 'Zonas asignadas correctamente'
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al asignar zonas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar una asignación de zona de un ítem
     */
    public function eliminarZonaItem(Request $request)
    {
        try {
            $validated = $request->validate([
                'codigo_item' => 'required|string',
                'codigo_bodega' => 'required|string',
                'id_f400' => 'required|string',
                'id_zona' => 'required|integer',
                'usuario_id' => 'required|integer'
            ]);

            $zonaAntes = InventarioItemZonas::where([
                'codigo_item' => $validated['codigo_item'],
                'codigo_bodega' => $validated['codigo_bodega'],
                'id_zona' => $validated['id_zona']
            ])->first();

            $eliminado = InventarioItemZonas::eliminarZona(
                $validated['codigo_item'],
                $validated['codigo_bodega'],
                $validated['id_zona']
            );

            if (!$eliminado) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró la asignación'
                ], 404);
            }

            // Log de eliminación
            InventarioLogs::registrar(
                $validated['usuario_id'],
                'eliminar',
                'inventario_item_zonas',
                'Eliminación de asignación de zona',
                $zonaAntes,
                null
            );

            return response()->json([
                'success' => true,
                'message' => 'Zona eliminada correctamente'
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar zona',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Listar zonas con estadísticas
     */
    public function obtenerZonas()
    {
        try {
            $zonas = InventarioZonas::withCount(['itemsAsignados as total_items'])->get();

            return response()->json([
                'success' => true,
                'data' => $zonas
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener zonas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear una nueva zona
     */
    public function crearZona(Request $request)
    {
        try {
            $usuarioId = $request->user()->id ?? $request->input('usuario_id');

            $validated = $request->validate([
                'nombre' => 'required|string|max:50',
                'descripcion' => 'nullable|string|max:255'
            ]);

            $zona = InventarioZonas::crearZona($validated);

            // Log de creación
            InventarioLogs::registrar(
                $usuarioId,
                'crear',
                'inventario_zonas',
                "Creación de nueva zona: {$zona->nombre}",
                null,
                $zona->toArray()
            );

            return response()->json([
                'success' => true,
                'message' => 'Zona creada correctamente',
                'data' => $zona
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear zona',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar una zona existente
     */
    public function actualizarZona(Request $request, $id)
    {
        try {
            $usuarioId = $request->user()->id ?? $request->input('usuario_id');
            $zona = InventarioZonas::findOrFail($id);
            $datosAntes = $zona->toArray();

            $validated = $request->validate([
                'nombre' => 'required|string|max:50',
                'descripcion' => 'nullable|string|max:255'
            ]);

            $zona->update([
                'nombre' => $validated['nombre'],
                'descripcion' => $validated['descripcion'] ?? null,
                'updated_at' => now()
            ]);

            // Log de edición
            InventarioLogs::registrar(
                $usuarioId,
                'editar',
                'inventario_zonas',
                "Zona actualizada: {$zona->nombre}",
                $datosAntes,
                $zona->toArray()
            );

            return response()->json([
                'success' => true,
                'message' => 'Zona actualizada correctamente',
                'data' => $zona
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar zona',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar una zona
     */
    public function eliminarZona(Request $request, $id)
    {
        try {
            $usuarioId = $request->user()->id ?? $request->input('usuario_id');
            $zona = InventarioZonas::findOrFail($id);
            $datosAntes = $zona->toArray();

            InventarioItemZonas::where('id_zona', $id)->delete();
            $zona->delete();

            // Log de eliminación
            InventarioLogs::registrar(
                $usuarioId,
                'eliminar',
                'inventario_zonas',
                "Eliminación de zona: {$zona->nombre}",
                $datosAntes,
                null
            );

            return response()->json([
                'success' => true,
                'message' => 'Zona eliminada correctamente'
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar zona',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
