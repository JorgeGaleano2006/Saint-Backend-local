<?php

namespace App\Http\Controllers;

use App\Models\InconsistenciaModelo;
use App\Models\InconsistenciaSiesa;
use App\Models\InconsistenciaSiesa2;
use App\Models\InconsistenciaUsuario;
use App\Models\InconsistenciaDepartamento;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class InconsistenciasController extends Controller
{




    //===========CONTROLADORES PARA EL FORMULARIO ===============//


    /**
     * Obtener información del usuario por correo
     */
    public function obtenerInfo(Request $request)
    {
        $request->validate([
            'correo_usuario' => 'required|email'
        ]);

        $info = InconsistenciaModelo::procesoUsuario($request->correo_usuario);
        return response()->json(['info' => $info]);
    }

    /**
     * Registrar nueva inconsistencia
     */
    public function generar(Request $request)
    {
        // Validación
        $validator = Validator::make($request->all(), [
            'cliente' => 'required|string',
            'id_departamento' => 'required',
            'correo_solicitante' => 'required|email',
            'inconsistencia' => 'required|string',
            'cantidad_solicitada_op' => 'nullable|numeric',
            'cantidad_inco' => 'nullable|numeric',
            'unidad_medida' => 'nullable|string',
            'item' => 'nullable|string',
            'tipo_inco' => 'nullable|string',
            'precio_unitario' => 'nullable|numeric',
            'precio_total' => 'nullable|numeric',
            'situacion' => 'nullable|string',
            'accion' => 'required_if:inconsistencia,documental_contabilidad',
            'imagenes.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Error de validación',
                'messages' => $validator->errors()
            ], 400);
        }

        // Normalizar precios
        $precioUnitario = number_format((float)str_replace(',', '', $request->precio_unitario ?? 0), 2, '.', '');
        $precioTotal = number_format((float)str_replace(',', '', $request->precio_total ?? 0), 2, '.', '');

        $id = InconsistenciaModelo::obtenerUltimoCodigoInconsistencia();
        $solicitante = InconsistenciaModelo::procesoUsuario($request->correo_solicitante);

        // Validar imágenes obligatorias
        $tiposQueRequierenImagen = [
            'prenda_imperfectos',
            'dano_maquina',
            'retal_incompleto',
            'imperfeccion_tela',
            'insumo_imperfecto',
            'empate_tendido',
            'faltante_rollo',
            'perdida_insumos',
            'perdida_piezas',
            'devolucion_materiales'
        ];

        if (in_array($request->inconsistencia, $tiposQueRequierenImagen)) {
            if (!$request->hasFile('imagenes')) {
                return response()->json([
                    'error' => 'Debes adjuntar al menos una imagen para este tipo de inconsistencia.'
                ], 400);
            }
        }

        // Preparar datos
        $data = [
            'fecha_inconsistencia' => Carbon::now('America/Bogota'),
            'Cliente' => $request->cliente,
            'departamento' => $request->id_departamento,
            'id_inconsistencia' => $id,
            'solicitante' => $solicitante['id_usuario'] ?? null,
            'jefe_inmediato' => $solicitante['lider_id'] ?? null,
            'tipo_inconsistencia' => $request->inconsistencia,
            'cantidad_solicitada_op' => $request->cantidad_solicitada_op,
            'cantidad_inconsistencia' => $request->cantidad_inco,
            'unidad_medida' => $request->unidad_medida,
            'item' => $request->item,
            'tipo_de_orden' => $request->tipo_inco,
            'precio_unitario' => $precioUnitario,
            'precio_total_inconsistencia' => $precioTotal,
            'descripcion_inconsistencia' => $request->situacion
        ];

        // Agregar acción si es necesario
        if ($request->inconsistencia == 'documental_contabilidad') {
            $data['accion_inconsistencia'] = $request->accion;
        }

        // Guardar imágenes
        $imagenes = [];
        if ($request->hasFile('imagenes')) {
            $imagenes = $this->guardarEvidencias($request, $id);
        }

        $data['evidencias'] = json_encode($imagenes);

        // Guardar inconsistencia
        $resultado = InconsistenciaModelo::registrarInconsistencia($data, $solicitante);

        return response()->json([
            'success' => $resultado,
            'id' => $id
        ]);
    }

    /**
     * Listar inconsistencias por correo de usuario
     */
    public function listarPorUsuario(Request $request)
    {
        $request->validate([
            'correo' => 'required|email'
        ]);

        $resultado = InconsistenciaModelo::listarPorCorreo($request->correo);
        return response()->json($resultado);
    }

    /**
     * Aprobar inconsistencia
     */
    public function aprobar(Request $request)
    {
        $request->validate([
            'id_inconsistencia' => 'required',
            'id_usuario' => 'required',
            'tipo_inconsistencia' => 'required|string',
            'etapa' => 'required|string',
            'espera' => 'nullable|boolean',
            'observacion_logistica' => 'nullable|string'
        ]);

        $data = [
            'id_inconsistencia' => $request->id_inconsistencia,
            'id_usuario' => $request->id_usuario,
            'tipo_inconsistencia' => $request->tipo_inconsistencia,
            'etapa' => $request->etapa,
            'espera' => $request->boolean('espera'),
            'observacion_logistica' => $request->observacion_logistica
        ];

        $resultado = InconsistenciaModelo::aprobarInconsistencia($data);
        return response()->json($resultado);
    }

    /**
     * Anular inconsistencia
     */
    public function anular(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
            'motivo' => 'required|string',
            'id_Sdp' => 'required|integer'
        ]);

        $resultado = InconsistenciaModelo::anularInconsistencia(
            $request->id,
            $request->motivo,
            $request->id_Sdp
        );

        return response()->json(['success' => $resultado]);
    }

    /**
     * Listar inconsistencias por estado/roles
     */
    public function listarPorEstado(Request $request)
    {
        $request->validate([
            'roles' => 'required|json',
            'id_usuario' => 'required'
        ]);

        $roles = json_decode($request->roles, true);
        $resultado = InconsistenciaModelo::listarPorRoles($roles, $request->id_usuario);

        return response()->json($resultado);
    }

    /**
     * Listar histórico de inconsistencias
     */
    public function listarHistorico(Request $request)
    {
        $request->validate([
            'proceso' => 'required|string'
        ]);

        $resultado = InconsistenciaModelo::listarHistorico($request->proceso);
        return response()->json($resultado);
    }

    /**
     * Guardar evidencias (imágenes)
     */
    private function guardarEvidencias(Request $request, $codigo)
    {
        $imagenes = [];

        if ($request->hasFile('imagenes')) {
            foreach ($request->file('imagenes') as $imagen) {
                $nombreArchivo = $codigo . '_' . time() . '_' . uniqid() . '.' . $imagen->getClientOriginalExtension();
                $path = $imagen->storeAs('evidencias/inconsistencias', $nombreArchivo, 'public');
                $imagenes[] = $path;
            }
        }

        return $imagenes;
    }



    // jorge

    /**
     * Obtener el último código de inconsistencia
     */
    public function obtenerUltimoCodigo()
    {
        $codigo = InconsistenciaModelo::obtenerUltimoCodigoInconsistencia();
        return response()->json(['codigo' => $codigo]);
    }

    /**
     * Obtener el código de orden de compra (OP,OPR,OPM)
     */
    public function ObtenerCodigoOrden(Request $request)
    {
        $request->validate([
            'orden_compra' => 'required|string'
        ]);

        // Llamamos al modelo y obtenemos los resultados
        $codigos = InconsistenciaSiesa::obtenerCodigoOrden(
            $request->orden_compra
        );

        // Si no hay resultados, retornamos un mensaje amigable
        if ($codigos->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontraron órdenes para el cliente y tipo de orden indicados.'
            ], 404);
        }

        // Retornamos los datos al front
        return response()->json([
            'success' => true,
            'data' => $codigos
        ]);
    }

    /**
     * Obtener las pv de la orden de compra para listar items 
     */

    public function ObtenerPVItems(Request $request)
    {
        $request->validate([
            'codigo' => 'required|string',
            'cliente' => 'required|string'
        ]);

        try {
            // 🔹 Extraer solo números del código recibido
            $codigoNumerico = preg_replace('/[^0-9\s]/', '', $request->codigo);

            if (empty(trim($codigoNumerico))) {
                return response()->json([
                    'success' => false,
                    'message' => 'El código proporcionado no contiene un número válido',
                    'data' => []
                ], 400);
            }

            // 🔹 Paso 1: Obtener todas las PVs asociadas al código
            $pedidosVenta = InconsistenciaSiesa::obtenerPv($codigoNumerico);

            if ($pedidosVenta->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron pedidos de venta para el código proporcionado',
                    'data' => []
                ], 404);
            }

            // 🔹 Paso 2: Extraer los números individuales o rangos
            // Ejemplo: "43791 43792 43800" -> [43791, 43792, 43800]
            preg_match_all('/\d{4,6}/', $request->codigo, $matches);
            $numerosExtraidos = collect($matches[0])->map(fn($n) => (int)$n)->values();

            $numerosPV = [];

            if ($numerosExtraidos->count() >= 2) {
                // Caso de rango: tomar el último como límite superior y empezar desde el siguiente del primero
                $inicio = $numerosExtraidos->first() + 1;
                $fin = $numerosExtraidos->last();

                for ($i = $inicio; $i <= $fin; $i++) {
                    $numerosPV[] = (string)$i;
                }
            } else {
                // Caso simple: procesar como antes (bloques o individuales)
                $numerosPV = $pedidosVenta->pluck('pvs')
                    ->filter()
                    ->flatMap(function ($pv) {
                        $numeros = preg_replace('/[^0-9]/', '', $pv);
                        $pvs = [];
                        $longitud = strlen($numeros);

                        for ($i = 0; $i < $longitud; $i += 5) {
                            $bloque = substr($numeros, $i, 5);
                            if (strlen($bloque) === 5) {
                                $pvs[] = $bloque;
                            }
                        }
                        return $pvs;
                    })
                    ->unique()
                    ->values()
                    ->all();
            }

            if (empty($numerosPV)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron números de PV válidos',
                    'data' => []
                ], 404);
            }

            // 🔹 Paso 3: Obtener items filtrados por cliente
            $items = InconsistenciaSiesa2::obtenerItemsPorPVsYCliente($numerosPV, $request->cliente);

            return response()->json([
                'success' => true,
                'message' => 'Items obtenidos correctamente',
                'data' => [
                    'pedidos_venta' => $pedidosVenta,
                    'numeros_pv_procesados' => $numerosPV,
                    'items' => $items,
                    'total_items' => $items->count()
                ]
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Error en ObtenerPVItems: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los items',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    //guardar datos del formulario 

    public function GenerarInconsistencia(Request $request)
    {
        try {
            // Obtener el tipo de inconsistencia
            $tipoInconsistencia = $request->input('inconsistencia');

            // Definir reglas de validación base
            $rules = [
                'fecha' => 'required|date',
                'cliente' => 'required|string|max:255',
                'nombre_proceso' => 'required|string|max:255',
                'id_departamento' => 'required',
                'codigo_inconsistencia' => 'required|string|max:100',
                'correo_solicitante' => 'required|email',
                'inconsistencia' => 'required|string|max:100',
                'cantidad_solicitada_op' => 'required|string',
                'cantidad_inco' => 'required|string',
                'unidad_medida' => 'required|string|in:unidades,metros,centimetros',
                'item' => 'required|string',
                'nombre_item' => 'nullable|string|max:255',
                'tipo_inco' => 'required|string|in:OP,OPR,OPM',
                'codigo' => 'required|string|max:100',
                'precio_unitario' => 'required|string',
                'precio_total' => 'nullable|string',
                'situacion' => 'required|string',
                'accion' => 'nullable|string'
            ];

            // Agregar validación de imágenes si el tipo lo requiere
            if (InconsistenciaModelo::requiereImagen($tipoInconsistencia)) {
                $rules['imagenes'] = 'required|array|min:1';
                $rules['imagenes.*'] = 'required|file|mimes:jpeg,jpg,png,pdf|max:10240';
            } else {
                $rules['imagenes'] = 'nullable|array';
                $rules['imagenes.*'] = 'nullable|file|mimes:jpeg,jpg,png,pdf|max:10240';
            }

            // Validación especial para documental_contabilidad
            if ($tipoInconsistencia === 'documental_contabilidad') {
                $rules['accion'] = 'required|string';
            }

            // Validar datos
            $validator = Validator::make($request->all(), $rules, [
                'imagenes.required' => 'Este tipo de inconsistencia requiere al menos una imagen',
                'imagenes.*.mimes' => 'Solo se permiten archivos de tipo: jpeg, jpg, png, pdf',
                'imagenes.*.max' => 'Cada archivo no debe superar los 10MB',
                'accion.required' => 'La acción es obligatoria para este tipo de inconsistencia'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            // ✅ Procesar las imágenes SOLO si existen
            $rutasImagenes = [];
            if ($request->hasFile('imagenes')) {
                $rutasImagenes = $this->guardarImagenes(
                    $request->file('imagenes'),
                    $request->input('codigo_inconsistencia')
                );
            }

            // Limpiar valores numéricos (eliminar comas de formato)
            $cantidadSolicitada = $this->limpiarNumero($request->input('cantidad_solicitada_op'));
            $cantidadInco = $this->limpiarNumero($request->input('cantidad_inco'));
            $precioUnitario = $this->limpiarNumero($request->input('precio_unitario'));
            $precioTotal = $this->limpiarNumero($request->input('precio_total'));

            // ✅ Preparar datos para guardar
            $datosInconsistencia = [
                'fecha_inconsistencia' => Carbon::now('America/Bogota'),
                'Cliente' => $request->input('cliente'),
                'jefe_inmediato' => $request->input('jefe_inmediato'),
                'departamento' => $request->input('id_departamento'),
                'codigo_inconsistencia' => $request->input('codigo_inconsistencia'),
                'correo_solicitante' => $request->input('correo_solicitante'),
                'tipo_inconsistencia' => $tipoInconsistencia,
                'cantidad_solicitada_op' => $cantidadSolicitada,
                'cantidad_inconsistencia' => $cantidadInco,
                'unidad_medida' => $request->input('unidad_medida'),
                'item' => $request->input('item'),
                'nombre_item' => $request->input('nombre_item'),
                'tipo_de_orden' => $request->input('tipo_inco') . ' ' . $request->input('codigo'),
                'precio_unitario' => $precioUnitario,
                'precio_total_inconsistencia' => $precioTotal,
                'situacion' => $request->input('situacion'),
                'evidencias' => !empty($rutasImagenes) ? json_encode($rutasImagenes) : null,
                'estado_inconsistencia' => 'abierta',
                'id_inconsistencia' => $request->input('codigo_inconsistencia'),
                'solicitante' => $request->input('id_solicitante'),
                'descripcion_inconsistencia' => $request->input('situacion')
            ];

            // ✅ Solo agregar acción si viene en el request
            if ($request->filled('accion')) {
                $datosInconsistencia['accion_inconsistencia'] = $request->input('accion');
            }

            // Crear la inconsistencia
            $inconsistencia = InconsistenciaModelo::create($datosInconsistencia);

            return response()->json([
                'success' => true,
                'message' => 'Inconsistencia registrada correctamente',
                'data' => [
                    'id' => $inconsistencia->id,
                    'codigo_inconsistencia' => $inconsistencia->codigo_inconsistencia
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], 500);
        }
    }


    /**
     * Guarda las imágenes en el sistema de archivos
     */
    /**
     * Guarda las imágenes en el sistema de archivos
     */
    private function guardarImagenes($imagenes, $codigo_inconsistencia)
    {
        // ✅ Validar que $imagenes no sea null o vacío
        if (empty($imagenes) || !is_array($imagenes)) {
            return [];
        }

        $rutasGuardadas = [];
        $carpetaBase = 'inconsistencias/' . $codigo_inconsistencia;

        // Crear la carpeta si no existe
        if (!file_exists(public_path($carpetaBase))) {
            mkdir(public_path($carpetaBase), 0755, true);
        }

        foreach ($imagenes as $index => $imagen) {
            // Generar nombre único para el archivo
            $nombreOriginal = pathinfo($imagen->getClientOriginalName(), PATHINFO_FILENAME);
            $extension = $imagen->getClientOriginalExtension();
            $timestamp = Carbon::now('America/Bogota')->format('YmdHis');
            $nombreArchivo = $nombreOriginal . '_' . $timestamp . '_' . $index . '.' . $extension;

            // Guardar el archivo
            $rutaCompleta = $carpetaBase . '/' . $nombreArchivo;
            $imagen->move(public_path($carpetaBase), $nombreArchivo);

            // Guardar la ruta relativa
            $rutasGuardadas[] = $rutaCompleta;
        }

        return $rutasGuardadas;
    }

    /**
     * Limpia un número formateado (elimina comas y convierte a float)
     */
    private function limpiarNumero($valor)
    {
        if (empty($valor)) {
            return 0;
        }

        // Eliminar comas y convertir a float
        return floatval(str_replace(',', '', $valor));
    }

    public function obtenerUltimoCodigoIn()
    {
        try {
            $ultimaInconsistencia = InconsistenciaModelo::latest('id_inconsistencia')->first();

            if ($ultimaInconsistencia) {
                // Extraer el número del código y sumar 1
                $numero = (int) filter_var($ultimaInconsistencia->codigo_inconsistencia, FILTER_SANITIZE_NUMBER_INT);
                $nuevoCodigo = 'INC-' . str_pad($numero + 1, 6, '0', STR_PAD_LEFT);
            } else {
                $nuevoCodigo = 'INC-000001';
            }

            return response()->json([
                'success' => true,
                'codigo' => $nuevoCodigo
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el código',
                'codigo' => 'INC-000001' // Código por defecto
            ]);
        }
    }


    public function VerInconsistencia($idUsuario)
    {
        if (!is_numeric($idUsuario)) {
            return response()->json([
                'success' => false,
                'message' => 'ID de usuario inválido'
            ], 400);
        }

        $inconsistencias = InconsistenciaModelo::obtenerInconsistenciaUsuario($idUsuario);

        if ($inconsistencias->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontraron inconsistencias'
            ], 404);
        }

        // Transformar las evidencias a URLs completas
        $inconsistencias = $inconsistencias->map(function ($inconsistencia) {
            if ($inconsistencia->evidencias) {
                $evidencias = json_decode($inconsistencia->evidencias, true);

                if (is_array($evidencias)) {
                    $inconsistencia->evidencias_urls = array_map(function ($ruta) {
                        // Como están en public, usamos url() directamente
                        return url($ruta);
                    }, $evidencias);
                } else {
                    $inconsistencia->evidencias_urls = [];
                }
            } else {
                $inconsistencia->evidencias_urls = [];
            }

            return $inconsistencia;
        });

        return response()->json([
            'success' => true,
            'data' => $inconsistencias
        ]);
    }

    /**
     * 🔹 Anular una inconsistencia
     */
    public function anularInconsistencia(Request $request)
    {

        $request->validate([
            'id_inco' => 'required|integer',
            'razon_anulacion' => 'required|string',
            'id_usuario' => 'required|integer',
        ]);

        // // Verifica que la inconsistencia exista
        // $inconsistencia = InconsistenciaModelo::find($request->id_inco);

        // if (!$inconsistencia) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Inconsistencia no encontrada.'
        //     ], 404);
        // }

        // Lógica de negocio: delegar actualización al modelo
        $actualizada = InconsistenciaModelo::anular(
            $request->id_inco,
            $request->razon_anulacion,
            $request->id_usuario
        );

        if ($actualizada) {
            return response()->json([
                'success' => true,
                'message' => 'Inconsistencia anulada correctamente.'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'No fue posible anular la inconsistencia.'
        ], 500);
    }



    /**
     * Listar Incosistencias por Departamento
     */
    public function listarInconsistenciasPorDepartamento(Request $request)
    {
        try {
            // ✅ Solo recibir el rol
            $rol = $request->query('rol');

            // Validación
            if (!$rol) {
                return response()->json([
                    'success' => false,
                    'message' => 'El parámetro "rol" es requerido.'
                ], 400);
            }

            // Normalizar el rol a minúsculas
            $rolNormalizado = strtolower(trim($rol));

            // ✅ Llamar al modelo
            $inconsistencias = InconsistenciaModelo::listarPorRol($rolNormalizado);

            return response()->json([
                'success' => true,
                'data' => $inconsistencias
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al listar las inconsistencias.',
                'error' => $e->getMessage()
            ], 500);
        }
    }







    /**
     * 🔹 Aprobar una inconsistencia
     */
    /**
     * 🔹 Aprobar o denegar una inconsistencia
     */
    public function accionInconsistencia(Request $request)
    {
        $validated = $request->validate([
            'id_inconsistencia' => 'required|integer',
            'id_Sdp' => 'required|integer',
            'accion' => 'required|string|in:aprobar,denegar',
            'motivo' => 'nullable|string'
        ]);

        $id = $validated['id_inconsistencia'];
        $id_usuario = $validated['id_Sdp'];
        $accion = $validated['accion'];
        $motivo = $validated['motivo'] ?? null;

        // Buscar inconsistencia
        $inconsistencia = InconsistenciaModelo::where('id_inconsistencia', $id)->first();

        if (!$inconsistencia) {
            return response()->json(['success' => false, 'message' => 'Inconsistencia no encontrada.'], 404);
        }

        // Decidir la acción
        $resultado = false;
        if ($accion === 'aprobar') {
            $resultado = $inconsistencia->aprobarEtapaActual($id_usuario);
        } elseif ($accion === 'denegar') {
            $resultado = $inconsistencia->denegar($id_usuario, $motivo);
        }

        if ($resultado) {
            return response()->json([
                'success' => true,
                'message' => 'Acción ejecutada correctamente.',
                'nueva_etapa' => $inconsistencia->fresh()->etapa
            ]);
        } else {
            return response()->json(['success' => false, 'message' => 'No se pudo ejecutar la acción.']);
        }
    }



    // HISTORICO INCONSISTENCIAS //

    public function historicoInconsistencias(Request $request)
    {
        $mes = $request->get('mes');
        $year = $request->get('year') ?? date('Y');

        $datos = InconsistenciaModelo::obtenerPorMes($mes, $year);
        return response()->json($datos);
    }

public function obtenerTiemposProceso($id)
{
    $inco = InconsistenciaModelo::obtenerTiemposProceso($id);

    if (!$inco) {
        return response()->json(['error' => 'Inconsistencia no encontrada'], 404);
    }

    // 🔹 Estado general
    if (!is_null($inco->persona_que_anulo)) {
        $estado = 'Anulada';
    } elseif ($inco->etapa != 'terminada') {
        $estado = 'En proceso';
    } else {
        $estado = 'Terminada';
    }

    // 🔹 Definir flujos según tipo de inconsistencia
    $flujos = [
        'documental trazo' => ['lider', 'trazo', 'calidad', 'logistica'],
        'error_patronaje' => ['lider', 'patronaje', 'calidad', 'logistica'],
        'documental_calidad' => ['lider', 'calidad'],
        'documental_contabilidad' => ['lider', 'contabilidad', 'cartera'],
        'default' => ['lider', 'calidad', 'logistica']
    ];

    // Determinar el flujo según el tipo de inconsistencia
    $tipoInco = strtolower(trim($inco->tipo_inconsistencia ?? ''));
    $flujoActual = $flujos[$tipoInco] ?? $flujos['default'];

    // 🔹 Determinar fecha de finalización según el tipo
    $fechaFinalizacion = ($tipoInco === 'documental_contabilidad')
        ? $inco->fecha_aprobacion_cartera
        : $inco->fecha_de_consumo;

    // 🔹 Calcular tiempos dinámicamente según el flujo
    $tiempos = $this->calcularTiemposFlujo($inco, $flujoActual, $fechaFinalizacion);

    // 🔹 Construir objeto de fechas dinámicamente
    $fechas = [
        'creacion' => $inco->fecha_inconsistencia,
    ];

    // ✅ Agregar todas las fechas del flujo
    foreach ($flujoActual as $etapa) {
        $campoFecha = "fecha_$etapa";
        $fechas[$etapa] = $inco->$campoFecha ?? null;
    }

    $fechas['terminado'] = $fechaFinalizacion;

    return response()->json([
        'id' => $inco->id,
        'codigo' => $inco->id_inconsistencia,
        'estado' => $estado,
        'tipo_inconsistencia' => $inco->tipo_inconsistencia,
        'flujo' => $flujoActual,
        'etapa_actual' => $inco->etapa,
        'fechas' => $fechas,
        'tiempos' => $tiempos,
        'debug_inco' => $inco->toArray(),
    ]);
}

private function calcularTiemposFlujo($inco, $flujo, $fechaFinalizacion)
{
    $tiempos = [];
    $fechaInicio = $inco->fecha_inconsistencia;
    $fechaAnterior = $fechaInicio;

    foreach ($flujo as $etapa) {
        $campoFecha = "fecha_$etapa";
        $fechaActual = $inco->$campoFecha ?? null;

        if ($fechaActual && $fechaAnterior) {
            $tiempos[$etapa] = $this->diferenciaHoras($fechaAnterior, $fechaActual);
            $fechaAnterior = $fechaActual;
        } else {
            $tiempos[$etapa] = null;
        }
    }

    // Calcular tiempo desde la última etapa hasta la finalización
    if ($fechaAnterior && $fechaFinalizacion) {
        $tiempos['finalizacion'] = $this->diferenciaHoras($fechaAnterior, $fechaFinalizacion);
    }

    // Calcular tiempo total
    $tiempos['total'] = $this->diferenciaHoras($fechaInicio, $fechaFinalizacion);

    return $tiempos;
}

private function diferenciaHoras($inicio, $fin)
{
    if (!$inicio || !$fin) {
        return null;
    }

    $inicio = Carbon::parse($inicio);
    $fin = Carbon::parse($fin);

    $diffMinutos = $inicio->diffInMinutes($fin);
    $diffHoras = intdiv($diffMinutos, 60);
    $minutos = $diffMinutos % 60;
    $diffDias = $inicio->diffInDays($fin);

    return [
        'dias' => $diffDias,
        'horas' => $diffHoras,
        'minutos' => $minutos,
        'total_minutos' => $diffMinutos,
    ];
}



    //====== INCONSISTENCIAS POR CONSUMIR ======== /

   public function InconsistenciaConsumo()
{
    try {
        $inconsistencias = InconsistenciaModelo::obtenerInconsistenciasListasParaConsumir();
        
        return response()->json($inconsistencias, 200);
        
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Error al obtener las inconsistencias listas para consumir',
            'error' => $e->getMessage()
        ], 500);
    }
}


public function consumirInconsistencia(Request $request)
{
    try {
        $idInconsistencia = $request->input('id_inconsistencia');
        $tipoConsumo = $request->input('tipo_consumo');

        // 🧩 Validaciones básicas
        if (!$idInconsistencia || !$tipoConsumo) {
            return response()->json([
                'success' => false,
                'message' => 'Faltan parámetros requeridos.'
            ], 400);
        }

        // 👇 Validar tipos de consumo permitidos
        $tiposPermitidos = ['consumo', 'gasto', 'devolucion'];
        if (!in_array($tipoConsumo, $tiposPermitidos)) {
            return response()->json([
                'success' => false,
                'message' => 'Tipo de consumo no válido. Valores permitidos: consumo, gasto, devolucion.'
            ], 400);
        }

        // 📦 Construir el objeto de detalles según el tipo
        $detallesConsumo = ['tipo_consumo' => ucfirst($tipoConsumo)];

        switch ($tipoConsumo) {
            case 'consumo':
                $codigoTrn = $request->input('codigo_trn');
                $codigoConsumo = $request->input('codigo_consumo');
                
                if (!$codigoTrn || !$codigoConsumo) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Para tipo "consumo" se requieren codigo_trn y codigo_consumo.'
                    ], 400);
                }
                
                $detallesConsumo['trn'] = $codigoTrn;
                $detallesConsumo['codigo_consumo'] = $codigoConsumo;
                break;

            case 'gasto':
                $codigoSrc = $request->input('codigo_validacion');
                
                if (!$codigoSrc) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Para tipo "gasto" se requiere codigo_validacion (SRC).'
                    ], 400);
                }
                
                $detallesConsumo['codigo_src'] = $codigoSrc;
                break;

            case 'devolucion':
                $codigoEi = $request->input('codigo_validacion');
                
                if (!$codigoEi) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Para tipo "devolucion" se requiere codigo_validacion (EI).'
                    ], 400);
                }
                
                $detallesConsumo['codigo_ei'] = $codigoEi;
                break;
        }

        // 💾 Registrar el consumo
        $resultado = InconsistenciaModelo::RegistrarConsumo($idInconsistencia, $detallesConsumo);

        if ($resultado) {
            return response()->json([
                'success' => true,
                'message' => 'El consumo fue registrado correctamente.',
                'detalles' => $detallesConsumo
            ], 200);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró la inconsistencia o no se pudo actualizar.'
            ], 404);
        }

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
        ], 500);
    }
}
}



