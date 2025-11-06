<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DashboardIncModel extends Model
{
    protected $connection = 'solicitud_de_permisos_local';
    protected $table = 'inconsistencias';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_inconsistencia',
        'fecha_inconsistencia',
        'Cliente',
        'departamento',
        'solicitante',
        'tipo_inconsistencia',
        'etapa',
        'estado_inconsistencia',
        'precio_total_inconsistencia',
        'estado_consumo',
        'fecha_de_consumo',
        'fecha_lider',
        'fecha_calidad',
        'fecha_logistica',
        'lider_que_aprobo',
        'calidad_que_aprobo',
        'logistica_que_aprobo',
        'usuario_que_consumio',
        'persona_que_anulo',
        'tipo_de_orden'
    ];

    // Relaciones
    public function usuarioSolicitante()
    {
        return $this->belongsTo(InconsistenciaUsuario::class, 'solicitante', 'id_usuario');
    }

    public function departamentoRelacion()
    {
        return $this->belongsTo(InconsistenciaDepartamento::class, 'departamento', 'id_departamento');
    }

    public function liderAprobo()
    {
        return $this->belongsTo(InconsistenciaUsuario::class, 'lider_que_aprobo', 'id_usuario');
    }

    public function calidadAprobo()
    {
        return $this->belongsTo(InconsistenciaUsuario::class, 'calidad_que_aprobo', 'id_usuario');
    }

    public function logisticaAprobo()
    {
        return $this->belongsTo(InconsistenciaUsuario::class, 'logistica_que_aprobo', 'id_usuario');
    }

    public function usuarioConsumio()
    {
        return $this->belongsTo(InconsistenciaUsuario::class, 'usuario_que_consumio', 'id_usuario');
    }

    public function personaQueAnulo()
    {
        return $this->belongsTo(InconsistenciaUsuario::class, 'persona_que_anulo', 'id_usuario');
    }

    // ==================== MÉTRICAS DE PRODUCTIVIDAD ====================

    public static function getMetricasProductividad($filtros = [])
    {
        try {
            $query = self::query();
            $query = self::aplicarFiltros($query, $filtros);

            return [
                'total_inconsistencias' => $query->count(),
                'por_etapa' => self::getInconsistenciasPorEtapa($filtros),
                'tiempos_aprobacion' => self::getTiemposPromedioPorEtapa($filtros),
                'duracion_total_proceso' => self::getDuracionTotalProceso($filtros),
                'porcentaje_en_espera' => self::getPorcentajeEnEspera($filtros),
                'promedio_por_usuario' => self::getPromedioInconsistenciasPorUsuario($filtros),
                'promedio_por_departamento' => self::getPromedioInconsistenciasPorDepartamento($filtros),
                'top_usuarios_reportes_denegados' => self::getTopReporteUsers($filtros)
            ];
        } catch (\Exception $e) {
            Log::error("Error en getMetricasProductividad: " . $e->getMessage());
            return ['error' => 'Error al obtener métricas de productividad'];
        }
    }

    private static function getInconsistenciasPorEtapa($filtros)
    {
        $query = self::select('etapa', DB::raw('COUNT(*) as cantidad'))
            ->groupBy('etapa');
        $query = self::aplicarFiltros($query, $filtros);
        return $query->get();
    }

    private static function getTiemposPromedioPorEtapa($filtros)
    {
        $query = self::query();
        $query = self::aplicarFiltros($query, $filtros);

        return [
            'lider' => $query->clone()
                ->whereNotNull('fecha_lider')
                ->whereNotNull('fecha_inconsistencia')
                ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, fecha_inconsistencia, fecha_lider)) as promedio')
                ->value('promedio'),

            'calidad' => $query->clone()
                ->whereNotNull('fecha_calidad')
                ->whereNotNull('fecha_lider')
                ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, fecha_lider, fecha_calidad)) as promedio')
                ->value('promedio'),

            'logistica' => $query->clone()
                ->whereNotNull('fecha_logistica')
                ->whereNotNull('fecha_calidad')
                ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, fecha_calidad, fecha_logistica)) as promedio')
                ->value('promedio')
        ];
    }

    private static function getDuracionTotalProceso($filtros)
    {
        $query = self::whereNotNull('fecha_de_consumo')
            ->whereNotNull('fecha_inconsistencia')
            ->selectRaw('AVG(TIMESTAMPDIFF(DAY, fecha_inconsistencia, fecha_de_consumo)) as promedio_dias');

        $query = self::aplicarFiltros($query, $filtros);
        return $query->value('promedio_dias');
    }

    private static function getPorcentajeEnEspera($filtros)
    {
        $query = self::query();
        $query = self::aplicarFiltros($query, $filtros);
        $total = $query->count();
        $enEspera = $query->clone()->where('etapa', 'espera')->count();
        return $total > 0 ? round(($enEspera / $total) * 100, 2) : 0;
    }

    private static function getPromedioInconsistenciasPorUsuario($filtros)
    {
        $query = self::select('solicitante', DB::raw('COUNT(*) as cantidad'))
            ->groupBy('solicitante')
            ->with(['usuarioSolicitante' => function ($q) {
                $q->select('id_usuario', 'nombres', 'apellidos');
            }]);

        $query = self::aplicarFiltros($query, $filtros);
        return $query->orderByDesc('cantidad')->limit(10)->get();
    }

    private static function getPromedioInconsistenciasPorDepartamento($filtros)
    {
        $query = self::select('departamento', DB::raw('COUNT(*) as cantidad'))
            ->whereNotNull('departamento')
            ->groupBy('departamento')
            ->with(['departamentoRelacion' => function ($q) {
                $q->select('id_departamento', 'nombre_departamento');
            }]);

        $query = self::aplicarFiltros($query, $filtros);
        return $query->orderByDesc('cantidad')->get();
    }

    // ==================== MÉTRICAS DE COSTOS ====================

    public static function getMetricasCostos($filtros = [])
    {
        try {
            $query = self::query();
            $query = self::aplicarFiltros($query, $filtros);

            return [
                'costo_total' => $query
                    ->where('etapa', 'terminada')
                    ->where('estado_inconsistencia', 'Cerrada')
                    ->sum('precio_total_inconsistencia'),

                'costo_promedio' => $query->where('etapa', 'terminada')
                    ->where('estado_inconsistencia', 'Cerrada')
                    ->avg('precio_total_inconsistencia'),
                'costo_por_tipo' => self::getCostoPorTipo($filtros),
                'costo_por_cliente' => self::getCostoPorCliente($filtros),
                'costo_por_departamento' => self::getCostoPorDepartamento($filtros),
                'top_5_tipos_costosos' => self::getTop5TiposCostosos($filtros)
            ];
        } catch (\Exception $e) {
            Log::error("Error en getMetricasCostos: " . $e->getMessage());
            return ['error' => 'Error al obtener métricas de costos'];
        }
    }

    private static function getCostoPorTipo($filtros)
    {
        $query = self::select('tipo_inconsistencia', DB::raw('SUM(precio_total_inconsistencia) as total'))
            ->whereNotNull('tipo_inconsistencia')
            ->groupBy('tipo_inconsistencia');

        $query = self::aplicarFiltros($query, $filtros);
        return $query->orderByDesc('total')->get();
    }

    private static function getCostoPorCliente($filtros)
    {
        $query = self::select('Cliente', DB::raw('SUM(precio_total_inconsistencia) as total'))
            ->whereNotNull('Cliente')
            ->groupBy('Cliente');

        $query = self::aplicarFiltros($query, $filtros);
        return $query->orderByDesc('total')->limit(10)->get();
    }

    private static function getCostoPorDepartamento($filtros)
    {
        $query = self::select('departamento', DB::raw('SUM(precio_total_inconsistencia) as total'))
            ->whereNotNull('departamento')
            ->groupBy('departamento')
            ->with(['departamentoRelacion' => function ($q) {
                $q->select('id_departamento', 'nombre_departamento');
            }]);

        $query = self::aplicarFiltros($query, $filtros);
        return $query->orderByDesc('total')->get();
    }

    private static function getTop5TiposCostosos($filtros)
    {
        $query = self::select('tipo_inconsistencia', DB::raw('SUM(precio_total_inconsistencia) as total'))
            ->whereNotNull('tipo_inconsistencia')
            ->groupBy('tipo_inconsistencia');

        $query = self::aplicarFiltros($query, $filtros);
        return $query->orderByDesc('total')->limit(5)->get();
    }

    // ==================== MÉTRICAS DE CONSUMO ====================

    public static function getMetricasConsumo($filtros = [])
    {
        try {
            $query = self::query();
            $query = self::aplicarFiltros($query, $filtros);

            $consumidas = $query->clone()->where('estado_consumo', 'CONSUMIDO')->count();
            $pendientes = $query->clone()->where('estado_consumo', 'POR CONSUMIR')->count();

            return [
                'consumidas' => $consumidas,
                'pendientes' => $pendientes,
                'tiempo_promedio_consumo' => self::getTiempoPromedioConsumo($filtros),
                'usuarios_responsables_consumo' => self::getUsuariosResponsablesConsumo($filtros)
            ];
        } catch (\Exception $e) {
            Log::error("Error en getMetricasConsumo: " . $e->getMessage());
            return ['error' => 'Error al obtener métricas de consumo'];
        }
    }

    private static function getTiempoPromedioConsumo($filtros)
    {
        $query = self::where('estado_consumo', 'CONSUMIDO')
            ->whereNotNull('fecha_de_consumo')
            ->whereNotNull('fecha_logistica')
            ->selectRaw('AVG(TIMESTAMPDIFF(DAY, fecha_logistica, fecha_de_consumo)) as promedio_dias');

        $query = self::aplicarFiltros($query, $filtros);
        return $query->value('promedio_dias');
    }

    private static function getUsuariosResponsablesConsumo($filtros)
    {
        $query = self::select('usuario_que_consumio', DB::raw('COUNT(*) as cantidad'))
            ->where('estado_consumo', 'CONSUMIDO')
            ->whereNotNull('usuario_que_consumio')
            ->groupBy('usuario_que_consumio')
            ->with(['usuarioConsumio' => function ($q) {
                $q->select('id_usuario', 'nombres', 'apellidos');
            }]);

        $query = self::aplicarFiltros($query, $filtros);
        return $query->orderByDesc('cantidad')->limit(10)->get();
    }

    // ==================== MÉTRICAS DE GESTIÓN HUMANA ====================

    public static function getMetricasGestionHumana($filtros = [])
    {
        try {
            return [
                'usuarios_con_mas_inconsistencias' => self::getUsuariosConMasInconsistencias($filtros),
                'usuarios_con_mas_aprobaciones' => self::getUsuariosConMasAprobaciones($filtros),
                'departamentos_con_mayor_carga' => self::getDepartamentosConMayorCarga($filtros)
            ];
        } catch (\Exception $e) {
            Log::error("Error en getMetricasGestionHumana: " . $e->getMessage());
            return ['error' => 'Error al obtener métricas de gestión humana'];
        }
    }

    private static function getUsuariosConMasInconsistencias($filtros)
    {
        $query = self::select('solicitante', DB::raw('COUNT(*) as cantidad'))
            ->groupBy('solicitante')
            ->with(['usuarioSolicitante' => function ($q) {
                $q->select('id_usuario', 'nombres', 'apellidos');
            }]);

        $query = self::aplicarFiltros($query, $filtros);
        return $query->orderByDesc('cantidad')->limit(10)->get();
    }

    private static function getUsuariosConMasAprobaciones($filtros)
    {
        $liderQuery = self::select('lider_que_aprobo as usuario', DB::raw('COUNT(*) as cantidad'))
            ->whereNotNull('lider_que_aprobo')
            ->groupBy('lider_que_aprobo');

        $liderQuery = self::aplicarFiltros($liderQuery, $filtros);

        return $liderQuery->with(['liderAprobo' => function ($q) {
            $q->select('id_usuario', 'nombres', 'apellidos');
        }])->orderByDesc('cantidad')->limit(10)->get();
    }

    private static function getDepartamentosConMayorCarga($filtros)
    {
        $query = self::select('departamento', DB::raw('COUNT(*) as cantidad'))
            ->whereNotNull('departamento')
            ->groupBy('departamento')
            ->with(['departamentoRelacion' => function ($q) {
                $q->select('id_departamento', 'nombre_departamento');
            }]);

        $query = self::aplicarFiltros($query, $filtros);
        return $query->orderByDesc('cantidad')->get();
    }

    // ==================== TOP USUARIOS QUE REPORTAN DENEGADAS ====================

    public static function getTopReporteUsers($filtros = [])
    {
        try {
            $query = self::query()
                ->where('estado_inconsistencia', 'Denegada')
                ->whereNotNull('persona_que_anulo');

            // Aplicar filtros ANTES de agrupar
            $query = self::aplicarFiltros($query, $filtros);

            // Ahora hacer el select y group by
            $query = $query->select(
                'persona_que_anulo',
                DB::raw('COUNT(*) as total_inconsistencias_anuladas')
            )
                ->groupBy('persona_que_anulo')
                ->with(['personaQueAnulo' => function ($q) {
                    $q->select('id_usuario', 'nombres', 'apellidos');
                }]);

            return $query->orderByDesc('total_inconsistencias_anuladas')->get();
        } catch (\Exception $e) {
            Log::error("Error en getTopReporteUsers: " . $e->getMessage());
            return ['error' => 'Error al obtener top de usuarios con reportes denegados'];
        }
    }

    // ==================== FILTROS ====================

    public static function aplicarFiltros($query, $filtros)
    {
        if (!empty($filtros['fecha_inicio']) && !empty($filtros['fecha_fin'])) {
            $query->whereBetween('fecha_inconsistencia', [$filtros['fecha_inicio'], $filtros['fecha_fin']]);
        }
        if (!empty($filtros['departamento'])) {
            $query->where('departamento', $filtros['departamento']);
        }
        if (!empty($filtros['cliente'])) {
            $query->where('Cliente', $filtros['cliente']);
        }
        if (!empty($filtros['tipo_inconsistencia'])) {
            $query->where('tipo_inconsistencia', $filtros['tipo_inconsistencia']);
        }
        if (!empty($filtros['etapa'])) {
            $query->where('etapa', $filtros['etapa']);
        }
        if (!empty($filtros['solicitante'])) {
            $query->where('solicitante', $filtros['solicitante']);
        }
        if (!empty($filtros['estado_consumo'])) {
            $query->where('estado_consumo', $filtros['estado_consumo']);
        }
        if (!empty($filtros['tipo_de_orden'])) {
            $query->where('tipo_de_orden', $filtros['tipo_de_orden']);
        }
        return $query;
    }

    // ==================== DATOS PARA FILTROS ====================

    public static function getDepartamentosUnicos()
    {
        return InconsistenciaDepartamento::select('id_departamento', 'nombre_departamento')
            ->orderBy('nombre_departamento')
            ->get();
    }

    public static function getClientesUnicos()
    {
        return self::select('Cliente')
            ->whereNotNull('Cliente')
            ->where('Cliente', '!=', '')
            ->distinct()
            ->orderBy('Cliente')
            ->pluck('Cliente');
    }

    public static function getTiposInconsistenciaUnicos()
    {
        return self::select('tipo_inconsistencia')
            ->whereNotNull('tipo_inconsistencia')
            ->where('tipo_inconsistencia', '!=', '')
            ->distinct()
            ->orderBy('tipo_inconsistencia')
            ->pluck('tipo_inconsistencia');
    }

    public static function getUsuariosActivos()
    {
        return InconsistenciaUsuario::select('id_usuario', 'nombres', 'apellidos')
            ->where('estado', 'activo')
            ->orderBy('nombres')
            ->get();
    }
}
