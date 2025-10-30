<?php

namespace App\Http\Controllers;

use App\Models\BigBagModel;
use App\Models\RecepcionesBigbagModelo;
use App\Models\ActivityModel;
use App\Models\ColorconsecutivoModelo;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\PrecintosAsignadoModelo;
use Illuminate\Support\Facades\Storage;
use App\Models\ReporteLlegadaEmpaqueModelo;
use App\Models\EntregaPrecintoBigbagModelo;
use Carbon\Carbon;
use App\Models\UsuarioOperarioRenueva;
use App\Models\RepartirPrecintoModelo;
use Exception;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

date_default_timezone_set('America/Bogota');



class BigBagController extends Controller
{
    private $model;
    private $precintoModelo;
    private $recepcionesModelo;
    private $operarioModelo;
    private $precintosAsignadoModelo;
    private $reporteLlegadaModelo;
    private $entregaPrecintoModelo;
    private $colorConsecutivoModelo;

    // Configuraciones
    private const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB
    private const ALLOWED_IMAGE_TYPES = ['jpeg', 'jpg', 'png', 'gif', 'webp'];
    private const ALLOWED_ACTIONS = ['version_actual', 'versiones'];

    public function __construct()
    {
        $this->model = new BigBagModel();
        $this->precintoModelo = new RepartirPrecintoModelo();
        $this->recepcionesModelo = new RecepcionesBigbagModelo();
        $this->operarioModelo = new UsuarioOperarioRenueva();
        $this->precintosAsignadoModelo = new PrecintosAsignadoModelo();
        $this->reporteLlegadaModelo = new ReporteLlegadaEmpaqueModelo();
        $this->entregaPrecintoModelo = new EntregaPrecintoBigbagModelo();
        $this->colorConsecutivoModelo = new ColorconsecutivoModelo();
    }

    /**
     * Valida formato de placa colombiana
     */
    private function validarPlaca($placa)
    {
        $placa = strtoupper(trim($placa));
        return preg_match('/^[A-Z]{3}[0-9]{2}[0-9A-Z]$/', $placa) ? $placa : null;
    }

    /**
     * Valida formato de fecha
     */
    private function validarFecha($fecha)
    {
        try {
            return Carbon::parse($fecha)->format('Y-m-d');
        } catch (Exception $e) {
            return null;
        }
    }

    // ========== Ingreso documentos ============

    private function generarNumeroRecepcion()
    {
        $documento = '06';
        $archivo = 'ultimo_numero.txt';

        try {
            $num = $this->model->obtenerUltimoNumero($archivo) ?: 1;
            $numeroRecepcion = "REC-{$documento}-{$num}";
            $this->model->guardarProximoNumero($archivo, $num + 1);
            return $numeroRecepcion;
        } catch (Exception $e) {
            Log::error('Error generando número de recepción: ' . $e->getMessage());
            throw new Exception('Error interno al generar número de recepción');
        }
    }

    private function validarDatos($datos)
    {
        $validator = Validator::make($datos, [
            'user_id' => 'required|integer|min:1',
            'fecha_ingreso' => 'required|date|before_or_equal:today',
            'hora_ingreso' => 'required|date_format:H:i',
            'planta' => 'required|string|max:100',
            'remision' => 'required|string|max:50',
            'cantidad_relacionada' => 'required|numeric|min:0|max:999999',
            'nom_operario' => 'required|string|max:100',
            'observaciones' => 'required|string|max:1000',
            'nom_conductor' => 'required|string|max:100',
            'placa_vehiculo' => 'required|string|max:10',
            'empresa_transporte' => 'required|string|max:100',
            'cantidad_fisico' => 'required|numeric|min:0|max:999999',
            'diferencia_reportada' => 'nullable|string|max:500',
            'cliente' => 'nullable|string|max:100',
        ]);

        return $validator->fails() ? $validator->errors()->all() : [];
    }

    private function manejarFirmaDataURL($nombreCampo, Request $request, $numeroRecepcion)
    {
        if (!$request->filled($nombreCampo)) {
            return null;
        }

        try {
            $dataURL = $request->input($nombreCampo);

            // Validar formato base64
            if (!preg_match('/^data:image\/(\w+);base64,/', $dataURL, $matches)) {
                throw new Exception("Formato de firma inválido para {$nombreCampo}");
            }

            $imageExtension = strtolower($matches[1]);

            if (!in_array($imageExtension, self::ALLOWED_IMAGE_TYPES)) {
                throw new Exception("Tipo de imagen no permitido: {$imageExtension}");
            }

            $firmaBase64 = substr($dataURL, strpos($dataURL, ',') + 1);

            if (!base64_decode($firmaBase64, true)) {
                throw new Exception("Datos base64 inválidos para {$nombreCampo}");
            }

            $imageData = base64_decode($firmaBase64);

            if ($imageData === false) {
                throw new Exception("Error al decodificar la imagen {$nombreCampo}");
            }

            if (strlen($imageData) > self::MAX_FILE_SIZE) {
                throw new Exception("La imagen {$nombreCampo} excede 5MB");
            }

            $numeroRecepcionLimpio = preg_replace('/[^A-Za-z0-9\-_]/', '', $numeroRecepcion);
            $tipoFirma = $nombreCampo === 'firma' ? 'operario' : 'conductor';
            $filename = 'firmas-recepciones/' . uniqid() . '_' . $tipoFirma . '_' . $numeroRecepcionLimpio . '.' . $imageExtension;

            Storage::disk('s3')->put($filename, $imageData, 'private');

            return $filename;
        } catch (Exception $e) {
            Log::error("Error procesando firma {$nombreCampo}: " . $e->getMessage());
            throw $e;
        }
    }

    public function obtenerFirmaDigital($recepcionId, $tipoFirma)
    {
        try {
            if (!is_numeric($recepcionId) || $recepcionId < 1) {
                return response()->json(['error' => 'ID de recepción inválido'], 400);
            }

            $firmaData = ReporteLlegadaEmpaqueModelo::obtenerFirma($recepcionId, $tipoFirma);

            if (!$firmaData) {
                return response()->json(['error' => 'Firma no encontrada'], 404);
            }

            if (!preg_match('/^firmas-recepciones\/[a-zA-Z0-9_\-]+\.(jpeg|jpg|png|gif|webp)$/', $firmaData['ruta'])) {
                return response()->json(['error' => 'Ruta de archivo no válida'], 400);
            }

            if (!Storage::disk('s3')->exists($firmaData['ruta'])) {
                return response()->json(['error' => 'Archivo no disponible'], 404);
            }

            $url = Storage::disk('s3')->temporaryUrl($firmaData['ruta'], now()->addHour());

            return response()->json([
                'success' => true,
                'url' => $url,
                'tipo_firma' => $firmaData['tipo_firma'],
                'num_recepcion' => $firmaData['num_recepcion']
            ]);
        } catch (Exception $e) {
            Log::error('Error obteniendo firma digital: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno al obtener la firma'], 500);
        }
    }

    private function recopilarDatos(Request $request)
    {
        $userId = $request->input('userId');

        if (!is_numeric($userId) || $userId < 1) {
            throw new Exception("El ID del usuario es requerido y debe ser válido");
        }

        return [
            'user_id' => (int) $userId,
            'fecha_ingreso' => $this->validarFecha($request->input('fechaIngreso')),
            'hora_ingreso' => $request->input('horaIngreso'),
            'planta' => $request->input('planta', ''),
            'remision' => $request->input('remision', ''),
            'cantidad_relacionada' => (float) $request->input('cantidadRelacionada', 0),
            'nom_operario' => $request->input('nomOperario', ''),
            'observaciones' => $request->input('observaciones', ''),
            'nom_conductor' => $request->input('nomConductor', ''),
            'placa_vehiculo' => $this->validarPlaca($request->input('placaVehiculo', '')),
            'empresa_transporte' => $request->input('empresaTransporte', ''),
            'cantidad_fisico' => (float) $request->input('cantidadFisico', 0),
            'diferencia_reportada' => $request->input('diferenciaReportada', ''),
            'cliente' => $request->input('cliente', ''),
        ];
    }

    public function crearRecepcion(Request $request)
    {
        try {
            $datos = $this->recopilarDatos($request);

            $errores = $this->validarDatos($datos);
            if (!empty($errores)) {
                return response()->json([
                    'success' => false,
                    'mensaje' => 'Errores de validación',
                    'errores' => $errores
                ], 422);
            }

            $numeroRecepcion = $this->generarNumeroRecepcion();

            $rutaFirma = $this->manejarFirmaDataURL('firma', $request, $numeroRecepcion);
            $rutaFirmaConductor = $this->manejarFirmaDataURL('firmaConductor', $request, $numeroRecepcion);

            $datos['ruta_firma'] = $rutaFirma;
            $datos['ruta_firma_conductor'] = $rutaFirmaConductor;
            $datos['num_recepcion'] = $numeroRecepcion;

            $resultadoInsercion = $this->model->insertarRecepcion($datos);

            if (!$resultadoInsercion['success']) {
                return response()->json([
                    'success' => false,
                    'mensaje' => 'Error al guardar la recepción',
                    'error' => $resultadoInsercion['error']
                ], 500);
            }

            $idRecepcion = $resultadoInsercion['id'];

            $datosLog = [
                'user_id' => $datos['user_id'],
                'num_recepcion' => $numeroRecepcion,
                'accion' => 'Subió'
            ];

            $resultadoLog = $this->model->insertarLogRecepcion($datosLog);

            if (!$resultadoLog['success']) {
                Log::warning("Error al insertar en recepciones_log: " . $resultadoLog['error']);
            }

            return response()->json([
                'success' => true,
                'mensaje' => 'Recepción guardada exitosamente',
                'datos' => [
                    'id' => $idRecepcion,
                    'numero_recepcion' => $numeroRecepcion,
                    'fecha_creacion' => now()->format('Y-m-d H:i:s'),
                    'user_id' => $datos['user_id']
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Error creando recepción: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'mensaje' => 'Error interno al crear la recepción'
            ], 500);
        }
    }

    // ========== Activity documentos ============

    public function obtenerActividades(Request $request): JsonResponse
    {
        try {
            if ($request->has('action')) {
                return $this->handleAction($request);
            }

            $actividades = ActivityModel::traerActividadesConVersiones();

            return response()->json([
                'success' => true,
                'data' => $actividades,
                'message' => 'Actividades obtenidas correctamente'
            ]);
        } catch (Exception $e) {
            Log::error('Error obteniendo actividades: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno al obtener las actividades'
            ], 500);
        }
    }

    private function handleAction(Request $request): JsonResponse
    {
        $action = $request->get('action');

        if (!in_array($action, self::ALLOWED_ACTIONS)) {
            return response()->json([
                'success' => false,
                'message' => 'Acción no válida'
            ], 400);
        }

        switch ($action) {
            case 'version_actual':
                return $this->getVersionActual($request);
            case 'versiones':
                return $this->getVersiones($request);
            default:
                return response()->json([
                    'success' => false,
                    'message' => 'Acción no válida'
                ], 400);
        }
    }

    private function getVersionActual(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'num_recepcion' => 'required|string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de entrada inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $num_recepcion = $request->get('num_recepcion');
            $version_actual = ActivityModel::traerVersionActual($num_recepcion);

            return response()->json([
                'success' => true,
                'data' => $version_actual,
                'message' => 'Versión actual obtenida correctamente'
            ]);
        } catch (Exception $e) {
            Log::error('Error obteniendo versión actual: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno al obtener la versión actual'
            ], 500);
        }
    }

    private function getVersiones(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'num_recepcion' => 'required|string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de entrada inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $num_recepcion = $request->get('num_recepcion');
            $versiones = ActivityModel::traerVersiones($num_recepcion);

            return response()->json([
                'success' => true,
                'data' => $versiones,
                'message' => 'Versiones obtenidas correctamente'
            ]);
        } catch (Exception $e) {
            Log::error('Error obteniendo versiones: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno al obtener las versiones'
            ], 500);
        }
    }

    // ========== Funcionalidad de Precintos ============

    public function registrarPrecinto(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_reporte_llegada' => 'required|integer',
            'fechaEntrega' => 'required|date',
            'area' => 'required|string|max:100',
            'nombre' => 'required|string|max:100',
            'cedula' => 'required|string|max:20',
            'cantidad' => 'required|integer|min:1|max:9999',
            'numeroPrecinto' => 'required|string|max:50',
            'rango' => 'required|string|max:100',
            'color_consecutivo' => 'nullable|string|max:50',
            'observaciones' => 'nullable|string|max:255',
            'id_operario' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'mensaje' => 'Datos de entrada inválidos',
                'errores' => $validator->errors()->all()
            ], 422);
        }

        try {
            $data = [
                'id_reporte_llegada' => (int) $request->input('id_reporte_llegada'),
                'fechaEntrega' => $request->input('fechaEntrega'),
                'area' => $request->input('area'),
                'nombre' => $request->input('nombre'),
                'cedula' => $request->input('cedula'),
                'cantidad' => (int) $request->input('cantidad'),
                'numeroPrecinto' => $request->input('numeroPrecinto'),
                'rango' => $request->input('rango'),
                'color_consecutivo' => $request->input('color_consecutivo', ''),
                'observaciones' => $request->input('observaciones', ''),
                'id_operario' => (int) $request->input('id_operario')
            ];

            $resultado = $this->precintoModelo->guardarPrecinto($data);

            return response()->json($resultado);
        } catch (Exception $e) {
            Log::error('Error registrando precinto: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'mensaje' => 'Error interno al registrar el precinto'
            ], 500);
        }
    }

    public function obtenerPrecintos($idReporte)
    {
        if (!is_numeric($idReporte) || $idReporte < 1) {
            return response()->json([
                'success' => false,
                'mensaje' => 'ID de recepción inválido'
            ], 400);
        }

        try {
            $resultado = $this->precintoModelo->obtenerPrecintosPorReporte($idReporte);

            if (!$resultado['success']) {
                return response()->json($resultado);
            }

            $precintos = $resultado['data'];

            foreach ($precintos as &$precinto) {
                if (!empty($precinto->fecha_entrega)) {
                    try {
                        $precinto->fecha_entrega_formateada = Carbon::parse($precinto->fecha_entrega)->format('d/m/Y H:i');
                    } catch (Exception $e) {
                        $precinto->fecha_entrega_formateada = 'Fecha inválida';
                    }
                }
            }

            return response()->json([
                'success' => true,
                'data' => $precintos,
                'total' => count($precintos),
                'mensaje' => 'Precintos obtenidos correctamente'
            ]);
        } catch (Exception $e) {
            Log::error('Error obteniendo precintos: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'mensaje' => 'Error interno al obtener los precintos'
            ], 500);
        }
    }

    // ========== Funcionalidad de Recepciones BigBag ============

    public function verRecepciones()
    {
        try {
            $documentos = $this->recepcionesModelo->obtenerDatos();
            return response()->json($documentos);
        } catch (Exception $e) {
            Log::error('Error obteniendo recepciones: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno al obtener las recepciones'
            ], 500);
        }
    }

    public function actualizarRecepciones(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'num_recepcion' => 'required|string|max:50',
            'cant_relacionada' => 'required|numeric|min:0',
            'cantidad_fisico' => 'required|numeric|min:0',
            'diferencia_reportada' => 'nullable|string|max:500',
            'usuario_id' => 'required|integer|min:1',
            'firts_name' => 'required|string|max:50',
            'last_name' => 'required|string|max:50',
            'justificacion' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de entrada inválidos',
                'errors' => $validator->errors()->all()
            ], 422);
        }

        try {
            $num_recepcion = $request->input('num_recepcion');
            $cant_relacionada = (float) $request->input('cant_relacionada');
            $cantidad_fisico = (float) $request->input('cantidad_fisico');
            $diferencia_reportada = $request->input('diferencia_reportada', '');
            $usuario_id = (int) $request->input('usuario_id');
            $firts_name = $request->input('firts_name');
            $last_name = $request->input('last_name');
            $justificacion = $request->input('justificacion', 'Sin justificación');

            $resultado = $this->recepcionesModelo->actualizarCantidades(
                $num_recepcion,
                $cant_relacionada,
                $cantidad_fisico,
                $diferencia_reportada,
                $usuario_id,
                $firts_name,
                $last_name,
                $justificacion
            );

            if ($resultado) {
                return response()->json([
                    'success' => true,
                    'message' => 'Cantidades actualizadas correctamente'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al actualizar las cantidades'
                ], 500);
            }
        } catch (Exception $e) {
            Log::error('Error actualizando recepciones: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno al actualizar las recepciones'
            ], 500);
        }
    }

    public function obtenerOperarios()
    {
        try {
            $operarios = $this->operarioModelo->obtenerOperarios();
            return response()->json($operarios);
        } catch (Exception $e) {
            Log::error('Error obteniendo operarios: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno al obtener los operarios'
            ], 500);
        }
    }

    // ========== Funcionalidad de Precintos Asignados ============

    public function precintoAsignado(Request $request)
    {
        $userId = $request->input('user_id');

        if (!is_numeric($userId) || $userId < 1) {
            return response()->json(['error' => 'ID de usuario inválido'], 400);
        }

        try {
            $datos = $this->precintosAsignadoModelo->obtenerPrecintosPorResponsable($userId);
            return response()->json($datos);
        } catch (Exception $e) {
            Log::error('Error obteniendo precintos asignados: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno al obtener los precintos'], 500);
        }
    }

    public function guardarNovedadFirma(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'precinto_id' => 'required|integer|min:1',
            'numero_precinto' => 'required|string|max:50',
            'user_id' => 'required|integer|min:1',
            'novedad' => 'required|string|max:1000',
            'firma_digital' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de entrada inválidos',
                'errors' => $validator->errors()->all()
            ], 422);
        }

        try {
            $precintoId = (int) $request->input('precinto_id');
            $numeroPrecinto = $request->input('numero_precinto');
            $novedad = $request->input('novedad');
            $firmaBase64 = $request->input('firma_digital');

            if (!preg_match('/^data:image\/(\w+);base64,/', $firmaBase64, $matches)) {
                return response()->json(['error' => 'Formato de firma inválido'], 400);
            }

            $imageExtension = strtolower($matches[1]);

            if (!in_array($imageExtension, self::ALLOWED_IMAGE_TYPES)) {
                return response()->json(['error' => 'Tipo de imagen no permitido'], 400);
            }

            $firmaBase64Clean = substr($firmaBase64, strpos($firmaBase64, ',') + 1);

            if (!base64_decode($firmaBase64Clean, true)) {
                return response()->json(['error' => 'Datos base64 inválidos'], 400);
            }

            $imageData = base64_decode($firmaBase64Clean);

            if ($imageData === false) {
                return response()->json(['error' => 'Error al decodificar la firma'], 400);
            }

            if (strlen($imageData) > self::MAX_FILE_SIZE) {
                return response()->json(['error' => 'Imagen demasiado grande (máximo 5MB)'], 400);
            }

            $numeroPrecintoLimpio = preg_replace('/[^A-Za-z0-9\-_]/', '', $numeroPrecinto);
            $filename = 'firmas-precintos/' . uniqid() . '_precinto_' . $numeroPrecintoLimpio . '.' . $imageExtension;

            Storage::disk('s3')->put($filename, $imageData, 'private');

            EntregaPrecintoBigbagModelo::actualizarNovedadYFirma($precintoId, $novedad, $filename);

            return response()->json([
                'success' => true,
                'message' => 'Novedad y firma guardadas correctamente.',
                'precinto_id' => $precintoId
            ], 201);
        } catch (Exception $e) {
            Log::error('Error guardando novedad y firma: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error interno al guardar la novedad y firma'
            ], 500);
        }
    }

    public function obtenerFirmaDigitalPrecinto($precintoId)
    {
        try {
            if (!is_numeric($precintoId) || $precintoId < 1) {
                return response()->json(['error' => 'ID de precinto inválido'], 400);
            }

            $precinto = EntregaPrecintoBigbagModelo::find($precintoId);

            if (!$precinto) {
                return response()->json(['error' => 'Precinto no encontrado'], 404);
            }

            if (empty($precinto->firmado_por)) {
                return response()->json(['error' => 'Firma no encontrada'], 404);
            }

            $firmaPath = $precinto->firmado_por;

            if (!preg_match('/^firmas-precintos\/[a-zA-Z0-9_\-]+\.(jpeg|jpg|png|gif|webp)$/', $firmaPath)) {
                return response()->json(['error' => 'Ruta de archivo no válida'], 400);
            }

            if (!Storage::disk('s3')->exists($firmaPath)) {
                return response()->json(['error' => 'Archivo no disponible'], 404);
            }

            $url = Storage::disk('s3')->temporaryUrl($firmaPath, now()->addHour());

            return response()->json([
                'success' => true,
                'url' => $url,
                'novedades_precintos' => $precinto->novedades_precintos ?? ''
            ]);
        } catch (Exception $e) {
            Log::error('Error obteniendo firma digital de precinto: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno al obtener la firma digital'], 500);
        }
    }

    /**
     * Obtiene el rango de fechas por defecto (últimos 30 días)
     */
    private function getDefaultRange()
    {
        $end = Carbon::today()->format('Y-m-d');
        $start = Carbon::today()->subDays(29)->format('Y-m-d');
        return [$start, $end];
    }

    /**
     * Valida y obtiene el rango de fechas de la request
     */
    private function getDateRange(Request $request)
    {
        [$defaultStart, $defaultEnd] = $this->getDefaultRange();

        $start = $request->get('start', $defaultStart);
        $end = $request->get('end', $defaultEnd);

        if (!$this->isValidDate($start) || !$this->isValidDate($end)) {
            return [$defaultStart, $defaultEnd];
        }

        return [$start, $end];
    }

    /**
     * Valida formato de fecha
     */
    private function isValidDate($date, $format = 'Y-m-d')
    {
        try {
            $d = Carbon::createFromFormat($format, $date);
            return $d && $d->format($format) === $date;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Obtiene todos los datos del dashboard
     */
    public function getAllData(Request $request)
    {
        try {
            [$start, $end] = $this->getDateRange($request);

            $overview = ReporteLlegadaEmpaqueModelo::getOverview($start, $end);
            $estados = ReporteLlegadaEmpaqueModelo::getEstados($start, $end);
            $clientes = ReporteLlegadaEmpaqueModelo::getClientes();
            $topClientes = ReporteLlegadaEmpaqueModelo::getTopClientes($start, $end, (int) $request->get('limit', 10));
            $porFecha = ReporteLlegadaEmpaqueModelo::getPorFecha($start, $end, $request->get('cliente'));
            $precintosColor = EntregaPrecintoBigbagModelo::getPorColor($start, $end);

            return response()->json([
                'success' => true,
                'data' => [
                    'overview' => $overview,
                    'estados' => $estados,
                    'clientes' => $clientes,
                    'top_clientes' => $topClientes,
                    'por_fecha' => $porFecha,
                    'precintosColor' => $precintosColor,
                    'range' => [
                        'start' => $start,
                        'end' => $end
                    ]
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Error obteniendo datos del dashboard: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error al obtener dashboard data'
            ], 500);
        }
    }

    public function index()
    {
        try {
            $operarios = UsuarioOperarioRenueva::obtenerOperarios();
            return response()->json($operarios);
        } catch (Exception $e) {
            Log::error('Error obteniendo índice de operarios: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno al obtener el índice de operarios'
            ], 500);
        }
    }

    public function obtenerConsecutivoColor()
    {
        try {
            $repo = new ColorconsecutivoModelo();
            $datos = $repo->obtenerColorConsecutivo();

            return response()->json($datos);
        } catch (Exception $e) {
            Log::error('Error obteniendo consecutivo por color: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno al obtener el consecutivo por color'
            ], 500);
        }
    }

    public function actualizarConsecutivo($color, $nuevoNumero)
    {
        try {
            if (!is_numeric($nuevoNumero) || $nuevoNumero < 1 || $nuevoNumero > 999999) {
                return response()->json([
                    'success' => false,
                    'message' => 'Número inválido'
                ], 400);
            }

            $modelo = new ColorconsecutivoModelo();
            $resultado = $modelo->actualizarNumeroActual($color, (int) $nuevoNumero);

            if ($resultado) {
                return response()->json([
                    'success' => true,
                    'message' => 'Número actualizado correctamente'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo actualizar el número'
                ], 400);
            }
        } catch (Exception $e) {
            Log::error('Error actualizando consecutivo: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno al actualizar el consecutivo'
            ], 500);
        }
    }

    /**
     * Sanitiza datos de salida para el frontend
     * Previene XSS al escapar entidades HTML
     */
    private function limpiarDatosSalida($data)
    {
        if (!is_array($data) && !is_object($data)) {
            return is_string($data)
                ? htmlspecialchars($data, ENT_QUOTES, 'UTF-8')
                : $data;
        }

        $stack = [&$data];

        while ($stack) {
            $current = array_pop($stack);

            if (is_array($current)) {
                foreach ($current as $key => &$value) {
                    if (is_array($value) || is_object($value)) {
                        $stack[] = &$value;
                    } elseif (is_string($value)) {
                        $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                    }
                }
            } elseif (is_object($current)) {
                foreach ($current as $key => &$value) {
                    if (is_array($value) || is_object($value)) {
                        $stack[] = &$value;
                    } elseif (is_string($value)) {
                        $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                    }
                }
            }
        }

        return $data;
    }
}