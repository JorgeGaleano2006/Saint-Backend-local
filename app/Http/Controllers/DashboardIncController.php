<?php

namespace App\Http\Controllers;

use App\Models\DashboardIncModel;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DashboardIncController extends Controller
{
    public function getProductividad(Request $request): JsonResponse
    {
        $filtros = $request->only([
            'fecha_inicio',
            'fecha_fin',
            'departamento',
            'cliente',
            'tipo_inconsistencia',
            'etapa',
            'solicitante',
            'estado_consumo',
            'tipo_de_orden'
        ]);

        $metricas = DashboardIncModel::getMetricasProductividad($filtros);

        return response()->json([
            'success' => true,
            'data' => $metricas
        ]);
    }

    public function getCostos(Request $request): JsonResponse
    {
        $filtros = $request->only([
            'fecha_inicio',
            'fecha_fin',
            'departamento',
            'cliente',
            'tipo_inconsistencia',
            'etapa',
            'solicitante',
            'estado_consumo',
            'tipo_de_orden'
        ]);

        $metricas = DashboardIncModel::getMetricasCostos($filtros);

        return response()->json([
            'success' => true,
            'data' => $metricas
        ]);
    }

    public function getConsumo(Request $request): JsonResponse
    {
        $filtros = $request->only([
            'fecha_inicio',
            'fecha_fin',
            'departamento',
            'cliente',
            'tipo_inconsistencia',
            'etapa',
            'solicitante',
            'estado_consumo',
            'tipo_de_orden'
        ]);

        $metricas = DashboardIncModel::getMetricasConsumo($filtros);

        return response()->json([
            'success' => true,
            'data' => $metricas
        ]);
    }

    public function getGestionHumana(Request $request): JsonResponse
    {
        $filtros = $request->only([
            'fecha_inicio',
            'fecha_fin',
            'departamento',
            'cliente',
            'tipo_inconsistencia',
            'etapa',
            'solicitante',
            'estado_consumo',
            'tipo_de_orden'
        ]);

        $metricas = DashboardIncModel::getMetricasGestionHumana($filtros);

        return response()->json([
            'success' => true,
            'data' => $metricas
        ]);
    }

    public function getDepartamentos(): JsonResponse
    {
        $departamentos = DashboardIncModel::getDepartamentosUnicos();

        return response()->json([
            'success' => true,
            'data' => $departamentos
        ]);
    }

    public function getClientes(): JsonResponse
    {
        $clientes = DashboardIncModel::getClientesUnicos();

        return response()->json([
            'success' => true,
            'data' => $clientes
        ]);
    }

    public function getTiposInconsistencia(): JsonResponse
    {
        $tipos = DashboardIncModel::getTiposInconsistenciaUnicos();

        return response()->json([
            'success' => true,
            'data' => $tipos
        ]);
    }

    public function getUsuarios(): JsonResponse
    {
        $usuarios = DashboardIncModel::getUsuariosActivos();

        return response()->json([
            'success' => true,
            'data' => $usuarios
        ]);
    }

    public function getDashboardData(Request $request): JsonResponse
    {
        $filtros = $request->only([
            'fecha_inicio',
            'fecha_fin',
            'departamento',
            'cliente',
            'tipo_inconsistencia',
            'etapa',
            'solicitante',
            'estado_consumo',
            'tipo_de_orden'
        ]);

        $data = [
            'productividad' => DashboardIncModel::getMetricasProductividad($filtros),
            'costos' => DashboardIncModel::getMetricasCostos($filtros),
            'consumo' => DashboardIncModel::getMetricasConsumo($filtros),
            'gestion_humana' => DashboardIncModel::getMetricasGestionHumana($filtros)
        ];

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function getTopReporteUsers(Request $request): JsonResponse
    {
        $filtros = $request->only([
            'fecha_inicio',
            'fecha_fin',
            'departamento',
            'cliente',
            'tipo_inconsistencia',
            'etapa',
            'solicitante',
            'estado_consumo',
            'tipo_de_orden'
        ]);

        $topUsers = DashboardIncModel::getTopReporteUsers($filtros);

        return response()->json([
            'success' => true,
            'data' => $topUsers
        ]);
    }
}