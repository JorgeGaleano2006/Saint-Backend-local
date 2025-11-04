<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB; // ✅ Importa la clase DB
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class InconsistenciaModelo extends Model
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
        'jefe_inmediato',
        'tipo_inconsistencia',
        'cantidad_solicitada_op',
        'cantidad_inconsistencia',
        'item',
        'tipo_de_orden',
        'precio_unitario',
        'precio_total_inconsistencia',
        'evidencias',
        'descripcion_inconsistencia',
        'etapa',
        'accion_inconsistencia',
        'estado_inconsistencia',
        'razon_anulacion',
        'persona_que_anulo',
        'lider_que_aprobo',
        'extra_que_aprobo',
        'calidad_que_aprobo',
        'logistica_que_aprobo',
        'observacion_logistica',
        'fecha_lider',
        'fecha_anulacion',
        'fecha_calidad',
        'fecha_logistica',
        'fecha_extra',
        'persona_espera',
        'estado_consumo',
        'detalles_consumo',
        'usuario_que_consumio',
        'fecha_de_consumo',
        'fecha_patronaje',
        'patronaje_que_aprobo',
        'estado_orden'
    ];


    //========= MODELOS PARA EL FORMULARIO INCONSISTENCIA =========//

    public static function obtenerInconsistenciasConTiempo($consecutivo)
    {



        return self::select([
            'inconsistencias.id_inconsistencia',
            'inconsistencias.fecha_inconsistencia',
            'inconsistencias.fecha_lider',
            'inconsistencias.fecha_calidad',
            'inconsistencias.fecha_logistica',
            'inconsistencias.fecha_anulacion',
            'inconsistencias.etapa',
            DB::raw('CASE WHEN inconsistencias.fecha_anulacion IS NOT NULL THEN inconsistencias.etapa ELSE NULL END AS anulada'),
            DB::raw("CONCAT(u_lider.nombres, ' ', u_lider.apellidos) AS lider_aprobador"),
            DB::raw("CONCAT(u_calidad.nombres, ' ', u_calidad.apellidos) AS calidad_aprobador"),
            DB::raw("CONCAT(u_logistica.nombres, ' ', u_logistica.apellidos) AS logistica_aprobador"),
        ])
            ->leftJoin('usuarios as u_lider', 'inconsistencias.lider_que_aprobo', '=', 'u_lider.id_usuario')
            ->leftJoin('usuarios as u_calidad', 'inconsistencias.calidad_que_aprobo', '=', 'u_calidad.id_usuario')
            ->leftJoin('usuarios as u_logistica', 'inconsistencias.logistica_que_aprobo', '=', 'u_logistica.id_usuario')
            ->whereYear('inconsistencias.fecha_inconsistencia', now()->year)
            ->where('inconsistencias.id_inconsistencia', $consecutivo)
            ->get()
            ->toArray();
    }


    public static function consumirInconsistencia($idInconsistencia, $idUsuario, $detallesConsumo)
    {
        try {
            $actualizados = DB::table('inconsistencias')
                ->where('id', $idInconsistencia)
                ->where('etapa', 'terminada')
                ->whereNull('razon_anulacion')
                ->whereNull('persona_que_anulo')
                ->update([
                    'estado_consumo' => 'CONSUMIDO',
                    'usuario_que_consumio' => $idUsuario,
                    'fecha_de_consumo' => now(),
                    'detalles_consumo' => $detallesConsumo,
                ]);

            return $actualizados > 0;
        } catch (\Throwable $e) {
            // ✅ No muestra error ni guarda logs, solo devuelve false
            return false;
        }
    }

    //jorge

    public static function obtenerUltimoCodigoInconsistencia()
    {
        // Obtener el valor máximo del campo id_inconsistencia
        $ultimoIdentificador = self::max('id_inconsistencia');

        // Si la tabla está vacía, empezar desde 20300
        if (empty($ultimoIdentificador)) {
            return 20300;
        }

        // Si ya hay registros, incrementamos en 1
        return $ultimoIdentificador + 1;
    }



    public static function obtenerCodigoOrden($cliente, $tipoDeOrden)
    {

        return DB::table('t850_mf_op_docto')
            ->select([
                'f850_referencia_1 AS cliente',
                'f850_consec_docto AS id',
                'f850_consec_docto AS codigo',
                'f850_fecha_ts_creacion AS fecha_creacion',
                'f850_ind_estado AS estado',
                'f850_notas AS tipo_orden',
            ])
            ->where('f850_id_cia', '01')
            ->whereNotIn('f850_ind_estado', [9])
            ->where('f850_fecha_ts_creacion', '>=', DB::raw('DATEADD(YEAR, -2, GETDATE())'))
            ->where('f850_referencia_1', $cliente)
            ->where('f850_id_tipo_docto', $tipoDeOrden)
            ->orderByDesc('f850_fecha_ts_creacion')
            ->get();
    }


    /**
     * 🔹 Guardar datos del formulario
     */


    protected $casts = [
        'fecha' => 'date',
        'precio_unitario' => 'decimal:2',
        'precio_total' => 'decimal:2',
        // 'cantidad_solicitada_op' => 'decimal:2',
        'cantidad_inco' => 'decimal:2',
        'imagenes' => 'array'
    ];

    // Tipos de inconsistencias que requieren imagen
    public static $tiposQueRequierenImagen = [
        'prenda_imperfectos',
        'dano_maquina',
        'retal_incompleto',
        'imperfeccion_tela',
        'insumo_imperfecto',
        'empate_tendido',
        'faltante_rollo',
        'perdida_insumos',
        'perdida_piezas',
        'devolucion_materiales',
        'documental_contabilidad'
    ];

    /**
     * Verifica si un tipo de inconsistencia requiere imágenes
     */
    public static function requiereImagen($tipo)
    {
        return in_array($tipo, self::$tiposQueRequierenImagen);
    }


    //===== MODELO PARA VER MIS INCONSISTENCIAS =======//

    /**
     * 🔹 listar inconsistencia de base de datos
     */

   public static function obtenerTrazabilidadPorUsuario($idUsuario)
{
    $data = self::select([
        'inconsistencias.id',
        'inconsistencias.id_inconsistencia',
        'inconsistencias.fecha_inconsistencia',
        'inconsistencias.Cliente',
        'inconsistencias.tipo_inconsistencia',
        'inconsistencias.cantidad_solicitada_op',
        'inconsistencias.cantidad_inconsistencia',
        'inconsistencias.item',
        'inconsistencias.tipo_de_orden',
        'inconsistencias.precio_unitario',
        'inconsistencias.precio_total_inconsistencia',
        'inconsistencias.descripcion_inconsistencia',
        'inconsistencias.etapa',
        'inconsistencias.estado_inconsistencia',
        'inconsistencias.accion_inconsistencia',
        'inconsistencias.estado_consumo',
        'inconsistencias.evidencias',
        'inconsistencias.razon_anulacion',
        'inconsistencias.fecha_anulacion',

        // Campos del solicitante y demás usuarios
        'solicitante.nombres as solicitante_nombres',
        'solicitante.apellidos as solicitante_apellidos',
        'jefe.nombres as jefe_nombres',
        'jefe.apellidos as jefe_apellidos',
        'anulador.nombres as anulador_nombres',
        'anulador.apellidos as anulador_apellidos',
        'lider.nombres as lider_nombres',
        'lider.apellidos as lider_apellidos',
        'extra.nombres as extra_nombres',
        'extra.apellidos as extra_apellidos',
        'calidad.nombres as calidad_nombres',
        'calidad.apellidos as calidad_apellidos',
        'logistica.nombres as logistica_nombres',
        'logistica.apellidos as logistica_apellidos',
        'cartera.nombres as cartera_nombres',
        'cartera.apellidos as cartera_apellidos',
        'patronaje.nombres as patronaje_nombres',
        'patronaje.apellidos as patronaje_apellidos',
        'contabilidad.nombres as contabilidad_nombres',
        'contabilidad.apellidos as contabilidad_apellidos',
        'trazo.nombres as trazo_nombres',
        'trazo.apellidos as trazo_apellidos',
        'espera_persona.nombres as espera_nombres',
        'espera_persona.apellidos as espera_apellidos',
        'consumidor.nombres as consumidor_nombres',
        'consumidor.apellidos as consumidor_apellidos',

        'inconsistencias.fecha_lider',
        'inconsistencias.fecha_extra',
        'inconsistencias.fecha_calidad',
        'inconsistencias.fecha_logistica',
        'inconsistencias.observacion_logistica',
        'inconsistencias.fecha_aprobacion_cartera',
        'inconsistencias.fecha_patronaje',
        'inconsistencias.fecha_contabilidad',
        'inconsistencias.fecha_trazo',
        'inconsistencias.fecha_espera',
        'inconsistencias.fecha_de_consumo',
        'inconsistencias.detalles_consumo',
        'd.nombre_departamento'
    ])
    ->leftJoin('usuarios as solicitante', 'inconsistencias.solicitante', '=', 'solicitante.id_usuario')
    ->leftJoin('usuarios as jefe', 'inconsistencias.jefe_inmediato', '=', 'jefe.id_usuario')
    ->leftJoin('usuarios as anulador', 'inconsistencias.persona_que_anulo', '=', 'anulador.id_usuario')
    ->leftJoin('usuarios as lider', 'inconsistencias.lider_que_aprobo', '=', 'lider.id_usuario')
    ->leftJoin('usuarios as extra', 'inconsistencias.extra_que_aprobo', '=', 'extra.id_usuario')
    ->leftJoin('usuarios as calidad', 'inconsistencias.calidad_que_aprobo', '=', 'calidad.id_usuario')
    ->leftJoin('usuarios as logistica', 'inconsistencias.logistica_que_aprobo', '=', 'logistica.id_usuario')
    ->leftJoin('usuarios as cartera', 'inconsistencias.cartera_que_aprobo', '=', 'cartera.id_usuario')
    ->leftJoin('usuarios as patronaje', 'inconsistencias.patronaje_que_aprobo', '=', 'patronaje.id_usuario')
    ->leftJoin('usuarios as contabilidad', 'inconsistencias.contabilidad_que_aprobo', '=', 'contabilidad.id_usuario')
    ->leftJoin('usuarios as trazo', 'inconsistencias.trazo_que_aprobo', '=', 'trazo.id_usuario')
    ->leftJoin('usuarios as espera_persona', 'inconsistencias.persona_espera', '=', 'espera_persona.id_usuario')
    ->leftJoin('usuarios as consumidor', 'inconsistencias.usuario_que_consumio', '=', 'consumidor.id_usuario')
    ->leftJoin('departamentos as d', 'inconsistencias.departamento', '=', 'd.id_departamento')
    ->where('inconsistencias.solicitante', $idUsuario)
    ->orderBy('inconsistencias.fecha_inconsistencia', 'desc')
    ->orderBy('inconsistencias.id', 'desc')
    ->get();

    // 🔹 Concatenamos nombres + apellidos en PHP antes de devolver
    return $data->map(function ($item) {
        $item->nombre_solicitante = trim(($item->solicitante_nombres ?? '') . ' ' . ($item->solicitante_apellidos ?? ''));
        $item->nombre_jefe_inmediato = trim(($item->jefe_nombres ?? '') . ' ' . ($item->jefe_apellidos ?? ''));
        $item->nombre_persona_que_anulo = trim(($item->anulador_nombres ?? '') . ' ' . ($item->anulador_apellidos ?? ''));
        $item->nombre_lider_aprobo = trim(($item->lider_nombres ?? '') . ' ' . ($item->lider_apellidos ?? ''));
        $item->nombre_extra_aprobo = trim(($item->extra_nombres ?? '') . ' ' . ($item->extra_apellidos ?? ''));
        $item->nombre_calidad_aprobo = trim(($item->calidad_nombres ?? '') . ' ' . ($item->calidad_apellidos ?? ''));
        $item->nombre_logistica_aprobo = trim(($item->logistica_nombres ?? '') . ' ' . ($item->logistica_apellidos ?? ''));
        $item->nombre_cartera_aprobo = trim(($item->cartera_nombres ?? '') . ' ' . ($item->cartera_apellidos ?? ''));
        $item->nombre_patronaje_aprobo = trim(($item->patronaje_nombres ?? '') . ' ' . ($item->patronaje_apellidos ?? ''));
        $item->nombre_contabilidad_aprobo = trim(($item->contabilidad_nombres ?? '') . ' ' . ($item->contabilidad_apellidos ?? ''));
        $item->nombre_trazo_aprobo = trim(($item->trazo_nombres ?? '') . ' ' . ($item->trazo_apellidos ?? ''));
        $item->nombre_persona_espera = trim(($item->espera_nombres ?? '') . ' ' . ($item->espera_apellidos ?? ''));
        $item->nombre_usuario_consumio = trim(($item->consumidor_nombres ?? '') . ' ' . ($item->consumidor_apellidos ?? ''));
        return $item;
    });
}


    /**
     * Anula una inconsistencia (método ya existente)
     * 
     * @param int $idInconsistencia
     * @param string $razonAnulacion
     * @param int $idUsuario
     * @return bool
     */
    public static function anular($idInconsistencia, $razonAnulacion, $idUsuario)
    {
        return self::where('id', $idInconsistencia)
            ->update([
                'estado_inconsistencia' => 'Anulada',
                'razon_anulacion' => $razonAnulacion,
                'persona_que_anulo' => $idUsuario,
                'fecha_anulacion' => now()
            ]) > 0;
    }



    /**
     *  Listar inconsistencias por departamento
     */

public static function listarPorRol($rol)
{
    $rolNormalizado = strtolower(trim($rol));

    $mapaEtapas = [
        'lider aprobador (inconsistencias)' => 'lider',
        'matriz reemplazo(inconsistencias)' => 'matriz',
        'calidad (inconsistencias)' => 'calidad',
        'contabilidad (inconsistencias)' => 'contabilidad',
        'logisitica (inconsistencias)' => 'logistica',
        'patronaje (inconsistencias)' => 'patronaje',
        'cartera (inconsistencias)' => 'cartera',
    ];

    $etapa = $mapaEtapas[$rolNormalizado] ?? null;

    if (!$etapa) {
        return collect([]);
    }

    // ✅ Construir la consulta base
    $query = self::select(
        'inconsistencias.*',
        'departamentos.nombre_departamento',
        'usuarios.nombres',
        'usuarios.apellidos'
    )
        ->join('departamentos', 'inconsistencias.departamento', '=', 'departamentos.id_departamento')
        ->join('usuarios', 'inconsistencias.solicitante', '=', 'usuarios.id_usuario');

    // ✅ Si es logística, traer solo etapa "logistica" pero con estados "abierta" O "espera"
    if ($etapa === 'logistica') {
        $query->where('inconsistencias.etapa', 'logistica')
              ->whereIn('inconsistencias.estado_inconsistencia', ['abierta', 'espera']);
    } else {
        // ✅ Para los demás roles, su etapa correspondiente y solo estado "abierta"
        $query->where('inconsistencias.etapa', $etapa)
              ->where('inconsistencias.estado_inconsistencia', 'abierta');
    }

    return $query->orderByDesc('inconsistencias.id')->get();
}




  /**
     * Actualiza el estado y observación de la inconsistencia
     */
    public function actualizarEstado(array $datos)
    {
        return $this->update($datos);
    }

    /**
     * Actualiza la etapa y datos de aprobación
     */
    public function actualizarEtapa(array $datos)
    {
        return $this->update($datos);
    }

    /**
     * Pone la inconsistencia en espera
     */
    public function actualizarEspera(array $datos)
    {
        return $this->update($datos);
    }

  /**
     * Deniega la inconsistencia
     */
    public function denegar($id_usuario, $motivo)
    {
        return $this->update([
            'estado_inconsistencia' => 'Denegada',
            'observacion' => $motivo
        ]);
    }




    // HISTORICO INCONSISTENCIAS //

    public static function obtenerPorMes($mes, $year = null)
    {
        $year = $year ?? date('Y');

        return self::join('usuarios', 'usuarios.id_usuario', '=', 'inconsistencias.solicitante')
            ->whereMonth('inconsistencias.fecha_inconsistencia', $mes)
            ->whereYear('inconsistencias.fecha_inconsistencia', $year)
            ->orderByDesc('inconsistencias.id')
            ->select(
                'inconsistencias.*',
                'usuarios.nombres as nombre_solicitante',
                'usuarios.apellidos as apellido_solicitante'
            )
            ->get();
    }


 public static function obtenerTiemposProceso($id)
{
    return self::selectRaw("
        i.id,
        i.id_inconsistencia,
        i.fecha_inconsistencia,
        i.fecha_lider,
        i.fecha_trazo,
        i.fecha_patronaje,
        i.fecha_calidad,
        i.fecha_logistica,
        i.fecha_contabilidad,
        i.fecha_cartera,
        i.fecha_de_consumo,
        i.etapa,
        i.estado_inconsistencia,
        i.persona_que_anulo,
        i.tipo_inconsistencia,
        i.fecha_aprobacion_cartera,

        CONCAT(ul.nombres, ' ', ul.apellidos)  AS nombre_lider,
        CONCAT(ut.nombres, ' ', ut.apellidos)  AS nombre_trazo,
        CONCAT(up.nombres, ' ', up.apellidos)  AS nombre_patronaje,
        CONCAT(uc.nombres, ' ', uc.apellidos)  AS nombre_calidad,
        CONCAT(ulg.nombres, ' ', ulg.apellidos) AS nombre_logistica,
        CONCAT(uco.nombres, ' ', uco.apellidos) AS nombre_contabilidad,
        CONCAT(uca.nombres, ' ', uca.apellidos) AS nombre_cartera,
        CONCAT(ucon.nombres, ' ', ucon.apellidos) AS nombre_consumo
    ")
    ->from('inconsistencias AS i')
    ->leftJoin('usuarios AS ul',  'i.lider_que_aprobo',        '=', 'ul.id_usuario')
    ->leftJoin('usuarios AS ut',  'i.trazo_que_aprobo',        '=', 'ut.id_usuario')
    ->leftJoin('usuarios AS up',  'i.patronaje_que_aprobo',    '=', 'up.id_usuario')
    ->leftJoin('usuarios AS uc',  'i.calidad_que_aprobo',      '=', 'uc.id_usuario')
    ->leftJoin('usuarios AS ulg', 'i.logistica_que_aprobo',    '=', 'ulg.id_usuario')
    ->leftJoin('usuarios AS uco', 'i.contabilidad_que_aprobo', '=', 'uco.id_usuario')
    ->leftJoin('usuarios AS uca', 'i.cartera_que_aprobo',      '=', 'uca.id_usuario')
    ->leftJoin('usuarios AS ucon', 'i.usuario_que_consumio',   '=', 'ucon.id_usuario')
    ->where('i.id_inconsistencia', $id)
    ->first();
}


    //======= INCONSISTENCIAS PARA CONSUMIR =========//
public static function obtenerInconsistenciasListasParaConsumir()
{
    return self::select(
            'inconsistencias.*',
            'solicitante.nombres as nombre_solicitante',
            'solicitante.apellidos as apellido_solicitante',
            'jefe.nombres as nombre_jefe',
            'jefe.apellidos as apellido_jefe',
            'departamentos.nombre_departamento as nombre_departamento'
        )
        ->join('usuarios as solicitante', 'solicitante.id_usuario', '=', 'inconsistencias.solicitante')
        ->join('usuarios as jefe', 'jefe.id_usuario', '=', 'inconsistencias.jefe_inmediato')
        ->join('departamentos', 'departamentos.id_departamento', '=', 'inconsistencias.departamento')
        ->where('inconsistencias.etapa', 'terminada')
        ->where('inconsistencias.estado_consumo', 'POR CONSUMIR')
        ->whereNotIn('inconsistencias.tipo_inconsistencia', ['documental_calidad', 'documental_contabilidad'])
        ->orderBy('inconsistencias.fecha_inconsistencia', 'desc')
        ->get();
}



public static function RegistrarConsumo($idInconsistencia, $detallesConsumo)
{
    return self::where('id_inconsistencia', $idInconsistencia)
        ->update([
            'detalles_consumo' => json_encode($detallesConsumo), // 👈 Convertir a JSON
            'fecha_de_consumo' => Carbon::now('America/Bogota'),
            'estado_consumo' => 'CONSUMIDO'
        ]);
}
}
