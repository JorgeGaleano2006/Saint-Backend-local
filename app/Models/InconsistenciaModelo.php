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
        'patronaje_que_aprobo'
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

    public static function obtenerInconsistenciaUsuario($id_Sdp)
    {
        return self::where('solicitante', $id_Sdp)
            ->orderByDesc('fecha_inconsistencia')
            ->get();
    }


    /**
     * 🔹 Anular inconsistencia en base de datos
     */
    public static function anular($id_inco, $razon_anulacion, $id_usuario)
    {
        return self::where('id_inconsistencia', $id_inco)
            ->update([
                'estado_inconsistencia' => 'Anulada',
                'razon_anulacion' => $razon_anulacion,
                'persona_que_anulo' => $id_usuario,
                'fecha_anulacion' => now()
            ]);
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

        // ✅ Obtener datos sin aplicar casts automáticos
        return self::select(
            'inconsistencias.*',
            'departamentos.nombre_departamento',
            'usuarios.nombres',
            'usuarios.apellidos'
        )
            ->join('departamentos', 'inconsistencias.departamento', '=', 'departamentos.id_departamento')
            ->join('usuarios', 'inconsistencias.solicitante', '=', 'usuarios.id_usuario')
            ->where('inconsistencias.etapa', $etapa)
            ->where('inconsistencias.estado_inconsistencia', 'Abierta')
            ->orderByDesc('inconsistencias.id')
            ->get();
    }




    /**
     * 🔹 Aprobar inconsistencia en base de datos
     */
    // public function aprobacionLider($id_Sdp, $tipo_inconsistencia)
    // {
    //     // Determinar la etapa según el tipo de inconsistencia
    //     $etapa = 'calidad'; // Por defecto

    //     switch ($tipo_inconsistencia) {
    //         case 'documental_trazo':
    //             $etapa = 'trazo';
    //             break;
    //         case 'patronaje':
    //             $etapa = 'patronaje';
    //             break;
    //         case 'documental_contabilidad':
    //             $etapa = 'contabilidad';
    //             break;
    //             // Los demás casos ya están cubiertos por el valor por defecto 'calidad'
    //     }

    //     return $this->update([
    //         'etapa' => $etapa,
    //         'lider_que_aprobo' => $id_Sdp,
    //         'fecha_lider' => now(),
    //         'observacion' => null
    //     ]);
    // }

    /**
     * Deniega una inconsistencia con motivo.
     */
    public function denegarLider($id_usuario, $motivo)
    {
        return $this->update([
            'estado_inconsistencia' => 'Denegada',
            'observacion' => $motivo
        ]);
    }





    //FLUJOS //

    private static function obtenerFlujo($tipo_inconsistencia)
    {
        $flujos = [
            'documental trazo' => ['lider', 'trazo', 'calidad', 'logistica', 'terminada'],
            'error_patronaje' => ['lider', 'patronaje', 'calidad', 'logistica', 'terminada'],
            'documental calidad' => ['lider', 'calidad', 'terminada'],
            'documental_contabilidad' => ['lider', 'contabilidad', 'cartera', 'terminada'],
            'default' => ['lider', 'calidad', 'logistica', 'terminada']
        ];

        return $flujos[$tipo_inconsistencia] ?? $flujos['default'];
    }

    /**
     * Obtiene la siguiente etapa según el flujo
     */
    private static function obtenerSiguienteEtapa($tipo_inconsistencia, $etapa_actual)
    {
        $flujo = self::obtenerFlujo($tipo_inconsistencia);
        $indice_actual = array_search($etapa_actual, $flujo);

        if ($indice_actual === false) {
            return $flujo[1] ?? 'calidad';
        }

        $siguiente_indice = $indice_actual + 1;
        return $flujo[$siguiente_indice] ?? 'terminada';
    }

    /**
     * Aprueba la etapa actual y avanza a la siguiente
     */
    public function aprobarEtapaActual($id_usuario)
    {
        $etapa_actual = $this->etapa ?? 'lider';
        $tipo_inconsistencia = $this->tipo_inconsistencia;

        $siguiente_etapa = self::obtenerSiguienteEtapa($tipo_inconsistencia, $etapa_actual);

        // Determinar qué campos actualizar según la etapa
        $datos_actualizacion = [
            'etapa' => $siguiente_etapa,
        ];

        // Guardar quién aprobó según la etapa
        switch ($etapa_actual) {
            case 'lider':
                $datos_actualizacion['lider_que_aprobo'] = $id_usuario;
                $datos_actualizacion['fecha_lider'] = Carbon::now('America/Bogota');
                break;
            case 'calidad':
                $datos_actualizacion['calidad_que_aprobo'] = $id_usuario;
                $datos_actualizacion['fecha_calidad'] = Carbon::now('America/Bogota');
                break;
            case 'logistica':
                $datos_actualizacion['logistica_que_aprobo'] = $id_usuario;
                $datos_actualizacion['fecha_logistica'] = Carbon::now('America/Bogota');
                break;
            case 'trazo':
                $datos_actualizacion['trazo_que_aprobo'] = $id_usuario;
                $datos_actualizacion['fecha_trazo'] = Carbon::now('America/Bogota');
                break;
            case 'patronaje':
                $datos_actualizacion['patronaje_que_aprobo'] = $id_usuario;
                $datos_actualizacion['fecha_patronaje'] = Carbon::now('America/Bogota');
                break;
            case 'contabilidad':
                $datos_actualizacion['contabilidad_que_aprobo'] = $id_usuario;
                $datos_actualizacion['fecha_contabilidad'] = Carbon::now('America/Bogota');
                break;
            case 'cartera':
                $datos_actualizacion['cartera_que_aprobo'] = $id_usuario;
                $datos_actualizacion['fecha_cartera'] = Carbon::now('America/Bogota');
                break;
        }

        // Si llega a "terminada", cambiar el estado
        if ($siguiente_etapa === 'terminada') {
            $datos_actualizacion['estado_inconsistencia'] = 'Aprobada';
        }

        $datos_actualizacion['observacion'] = null; // Limpiar observaciones al aprobar

        return $this->update($datos_actualizacion);
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
    return self::select(
        'id',
        'id_inconsistencia',
        'fecha_inconsistencia',
        'fecha_lider',
        'fecha_trazo',           // ⚠️ Asegúrate de tener este campo
        'fecha_patronaje',       // ⚠️ Asegúrate de tener este campo
        'fecha_calidad',
        'fecha_logistica',
        'fecha_contabilidad',    // ⚠️ Si lo usas
        'fecha_cartera',         // ⚠️ Si lo usas (o fecha_aprobacion_cartera)
        'fecha_de_consumo',
        'etapa',
        'estado_inconsistencia',
        'persona_que_anulo',
        'tipo_inconsistencia',
        'fecha_aprobacion_cartera'
    )
    ->where('id_inconsistencia', $id)
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
