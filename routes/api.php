<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Llamado de los Middleware
use App\Http\Middleware\SanitizeInput; // Sanitizacion de entradas (sanitiza todos los request GET, POST, etc para evitar inyecciones XSS y otros ataques)

// Llamado de los Controladores
use App\Http\Controllers\TechnicalDataSheetDocumentController;
use App\Http\Controllers\TechnicalDatasheetsController;
use App\Http\Controllers\TerminacionEmpaqueController;
use App\Http\Controllers\InventarioController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\BigBagController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\InconsistenciasController;
use App\Http\Controllers\DashboardIncController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| Todas las rutas que declares aquí quedarán automáticamente
| precedidas por /api.  Ej.: /api/op/activas
*/

/** 🔹 Rutas protegidas por Sanctum (ejemplo) */
Route::get('/user', function (Request $request) {
     return $request->user();
})->middleware('auth:sanctum');

/* ==========================================================
|  Generales
|========================================================== */


/* ===============================
|  Inicio de sesion de usuarios.
|=============================== */

/* Login */
Route::post('/login', [AuthController::class, 'login']);

/* Refresh token */
Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('refresh.jwt');

/* Rutas protegidas */
Route::middleware(['jwt'])->group(function () {

     Route::get('/me', [AuthController::class, 'me']);
});



/** 🟢 Obtener clientes */
Route::get('/clientes/{word}', [ClienteController::class, 'obtenerClientes']);

// Rutas de usuarios 
/** 🟢 Obtener usuarios por roles */
Route::post('/users/by-roles', [UserController::class, 'obtenerPorRoles']);

/* ========================================================================================
|  Rutas para Órdenes de Producción (OP), Pedidos de Venta (PV) y Producto Terminado (PT)
|========================================================================================== */

/** 🟢 OPs activas (últimos 2 años) */
Route::get('/op/activas', [TerminacionEmpaqueController::class, 'listarOpsActivas']);

/** 🟢 PVs asociadas a una OP (por número de OP) */
Route::get('/op/{numeroOp}/pvs', [TerminacionEmpaqueController::class, 'listarPVsPorOP'])
     ->whereNumber('numeroOp');   // asegura que solo números coincidan

/** 🟢 Ítems detallados de una PV (por número de PV) */
Route::get('/pv/{numeroPv}/items', [TerminacionEmpaqueController::class, 'listarItemsDePV'])
     ->whereNumber('numeroPv');   // asegura que solo números coincidan

/** 🟢 Ítems detallados de una PV (por número de PV) */
Route::get('{numeroOp}/pv/{numeroPv}/items', [TerminacionEmpaqueController::class, 'listarItemsDePVOP'])
     ->whereNumber('numeroPv');   // asegura que solo números coincidan

/** 🟢 Registrar recepción de una OP */
Route::post('/recepcion-items', [TerminacionEmpaqueController::class, 'registrarRecepcionItems']);

/** 🟢 Registrar recepción de una PT */
Route::post('/recepcion-items-pt', [TerminacionEmpaqueController::class, 'registrarRecepcionItemsPT']);

/** 🟢 Cambiar recepción de un item */
Route::post('/pv/item/actualizar-ubicacion', [TerminacionEmpaqueController::class, 'actualizarUbicacion']);

/** 🟢 Generar los hashes de los items */
Route::post('/generar-hashes', [TerminacionEmpaqueController::class, 'generarHashes']);

/** 🟢 Obtener cantidad recibida en la base de datos local OP */
Route::post('/consultar-cantidades-hash', [TerminacionEmpaqueController::class, 'ConsultarCantidadesHash']);

/** 🟢 Obtener cantidad recibida en la base de datos local PT */
Route::post('/consultar-cantidades-pt-hash', [TerminacionEmpaqueController::class, 'ConsultarCantidadesPtHash']);

/** 🟢 Obtener estado de las PTs */
Route::post('/pt/estado', [TerminacionEmpaqueController::class, 'verificarEstadoPTs']);

/** 🟢 OPs pendientes (son las Ops activas y que esten pendientes internamente) */
Route::get('/op/pendientes', [TerminacionEmpaqueController::class, 'ObtenerOPsPendientes']);

/** 🟢 Revisa los item relacionados a las OP y PV para saber cuales estan pendientes por asignacion */
Route::post('/op/items-pendientes', [TerminacionEmpaqueController::class, 'obtenerOPsConItemsPendiente']);

/** 🟢 Revisa los item relacionados a la PV para saber cuales estan pendientes por asignacion */
Route::post('/pv/items-pendientes-pv', [TerminacionEmpaqueController::class, 'obtenerItemsPendientesPorPV']);

/** 🟢 Registrar la asignacion de los items a lasPV */
Route::post('/registrar-asignaciones', [TerminacionEmpaqueController::class, 'registrarAsignaciones']);

/** 🟢 Obtener PVs que estan pendientes por asignar a un empacador */
Route::get('/pvs/pendientes', [TerminacionEmpaqueController::class, 'obtenerPVsPendientes']);

/** 🟢 Obtener asignaciones de cada empacador */
Route::post('/empacadores/asignaciones', [TerminacionEmpaqueController::class, 'obtenerAsignacionesMultiples']);

/** 🟢 Asignar una PV a un empacador */
Route::post('/empacadores/asignar-pv', [TerminacionEmpaqueController::class, 'asignarPVAEmpacador']);

/** 🟢 Asignar una PV a un empacador */
Route::delete('/empacadores/desasignar-pv', [TerminacionEmpaqueController::class, 'desasignarPV']);

/** 🟢 Obtener PVs asignadas de los empacadores */
Route::get('/empacadores/{empacadorId}/pvs-asignadas', [TerminacionEmpaqueController::class, 'obtenerPVsAsignadas'])
     ->whereNumber('empacadorId');

/** 🟢 Obtener items de PVs asignadas de los empacadores */
Route::post('/pv/empacadorId/items-empaque', [TerminacionEmpaqueController::class, 'obtenerItemsPVEmpaque']);

/** 🟢 Registrar empaques a PVs */
Route::post('/empaque/registrar', [TerminacionEmpaqueController::class, 'registrarEmpaque']);

/** 🟢 Obtener empaques de PV y Empacador */
Route::post('/empaque/por-pv', [TerminacionEmpaqueController::class, 'obtenerEmpaquesPorPV']);

/** 🟢 Obtener empaques de PV*/
Route::post('/empaques/por-pv', [TerminacionEmpaqueController::class, 'EmpaquesPorPV']);

/** 🟢 Dashboard data */
Route::post('/empaque/dashboard-data', [TerminacionEmpaqueController::class, 'getDashboardData']);

// NUEVAS RUTAS PARA DASHBOARD DE OPs
Route::post('/empaque/dashboard-ops', [TerminacionEmpaqueController::class, 'getDashboardOPs']);

// Rutas para OPs específicas
Route::get('/op/{id}/detalle-completo', [TerminacionEmpaqueController::class, 'getDetalleCompleto']);
Route::get('/op/{id}/pvs-pts', [TerminacionEmpaqueController::class, 'getPvsPts']);
Route::get('/op/{id}/qr-data', [TerminacionEmpaqueController::class, 'getQRData']);
Route::get('/op/{id}/progreso', [TerminacionEmpaqueController::class, 'getProgreso']);
Route::get('/op/dashboard-list', [TerminacionEmpaqueController::class, 'getDashboardList']);

// Actualización de estado de OP
Route::post('/op/{id}/actualizar-estado', [TerminacionEmpaqueController::class, 'actualizarEstado']);

// Exportación de QRs
Route::post('/op/export-qrs', [TerminacionEmpaqueController::class, 'exportarQRs']);


/* ===============================
|  Fichas Técnicas - Documentos
|=============================== */

// 🟢 Subir documento de ficha tecnica
Route::post('/document/save', [TechnicalDataSheetDocumentController::class, 'SaveDocumentTechnicalDataSheets']);

// 🟢 Obtener documento por ID de ficha tecnica
Route::get('/get-document-technical-data-sheet/{id}', [TechnicalDataSheetDocumentController::class, 'GetDocumentByRegisterTechnicalDataSheets']);

// 🟢 obtener historial de versiones del documento
Route::get('/document/last-versions/{id}', [TechnicalDataSheetDocumentController::class, 'GetLastDocumentVersions']);


/* ===============================
|  Fichas Técnicas - Lista - Paginacion - Filtros
|=============================== */

Route::post('/technicaldatasheet/list', [TechnicalDatasheetsController::class, 'ListTechnicalDataSheets']);



/* ===============================
|  Renueva - Documentos
|=============================== */


// �0�8 guardar recepcion
Route::post('/renueva/guardarRecepcion', [BigBagController::class, 'crearRecepcion']);

// �0�8  A�0�9adir novedad y firma

Route::post('/precintos-asignados/novedad-firma', [BigBagController::class, 'guardarNovedadFirma']);


Route::middleware([SanitizeInput::class])->group(function () {

     // �0�8  guardar y obtener precintos 

     Route::post('/precintos', [BigBagController::class, 'registrarPrecinto']);
     Route::get('/precintos/{idReporte}', [BigBagController::class, 'obtenerPrecintos']);

     // �0�8 obtener firma recepciones, conductor y operario

     Route::get('/renueva/obtener-firma/{recepcionId}/{tipoFirma}', [App\Http\Controllers\BigBagController::class, 'obtenerFirmaDigital']);

     //  ver y actualizar recepciones 
     Route::get('/renueva/recepcion', [BigBagController::class, 'verRecepciones']);
     Route::put('/renueva/recepcion', [BigBagController::class, 'actualizarRecepciones']);

     // �0�8 obtener usuarios operarios
     Route::get('/usuarios-operarios', [BigBagController::class, 'index']);

     // �0�8 obtener rango actual precinto 

     Route::get('/color-consecutivo', [BigBagController::class, 'obtenerConsecutivoColor']);

     // �0�8 actualizar numero precinto
     Route::post('/guardar-consecutivo/{color}/{nuevoNumero}', [BigBagController::class, 'actualizarConsecutivo']);

     // �0�8 enviar id para obtener precintos del usuarios que se logea
     Route::post('/precintos-asignados', [BigBagController::class, 'precintoAsignado']);


     // �0�8 Obtener firma
     Route::get('/precintos-asignados/firma/{precintoId}', [BigBagController::class, 'obtenerFirmaDigitalPrecinto']);

     // �0�8 datos para el dashboard
     Route::get('/dashboard-data/datos', [BigBagController::class, 'getAllData']);


     // �0�8 obtener actividades y versiones de documentos -- * -- 

     Route::get('/activities', [BigBagController::class, 'obtenerActividades']);
});


/* ===============================
|  Inventario - Conteo
|=============================== */

// 🟢 Listar bodegas
Route::get('/inventario/resumen-bodegas', [InventarioController::class, 'obtenerResumenBodegas']);

// 🟢 Listar items de una bodega
Route::get('/bodegas/{codigo}/items', [InventarioController::class, 'obtenerItemsPorBodega']);

// 🟢 Asignar zonas a items de una bodega
Route::post('/inventario/asignar-zona-items', [InventarioController::class, 'asignarZona']);

// 🟢 Eliminar una zona a un item de una bodega
Route::delete('/inventario/eliminar-zona-item', [InventarioController::class, 'eliminarZonaItem']);

/* ===============================
|  ZONAS - CRUD Completo
|=============================== */
// 🟢 Listar todas las zonas
Route::get('/zonas', [InventarioController::class, 'obtenerZonas']);

// 🟢 Crear nueva zona
Route::post('/crear/zonas', [InventarioController::class, 'crearZona']);

// 🟢 Actualizar zona
Route::put('/zonas/{id}', [InventarioController::class, 'actualizarZona']);

// 🟢 Eliminar zona
Route::delete('/zonas/{id}', [InventarioController::class, 'eliminarZona']);




/*
|--------------------------------------------------------------------------
| API Routes - Inconsistencias
|--------------------------------------------------------------------------
*/


Route::prefix('inconsistencias')->group(function () {
     Route::get('/ultimo_codigo', [InconsistenciasController::class, 'obtenerUltimoCodigo']);
     Route::post('/codigo_orden', [InconsistenciasController::class, 'ObtenerCodigoOrden']);
     Route::post('/consultar-item', [InconsistenciasController::class, 'ObtenerPVItems']);
     Route::post('/generar_inconsistencia', [InconsistenciasController::class, 'GenerarInconsistencia']);
     Route::get('/usuario/{idUsuario}', [InconsistenciasController::class, 'VerInconsistencia']);
     Route::post('/anular_inconsistencia', [InconsistenciasController::class, 'anularInconsistencia']);
     Route::get('/listar_inconsistencias_departamento', [InconsistenciasController::class, 'listarInconsistenciasPorDepartamento']);
     Route::post('/accion_inconsistencia', [InconsistenciasController::class, 'accionInconsistencia']); // aprobacion o rechazo del lider
     Route::get('/historico', [InconsistenciasController::class,'historicoInconsistencias']);
     Route::get('{id}/tiempos-proceso', [InconsistenciasController::class, 'obtenerTiemposProceso']);
     Route::get('/listas-consumo', [InconsistenciasController::class, 'InconsistenciaConsumo']);
     Route::post('/consumir', [InconsistenciasController::class, 'consumirInconsistencia']);
     
});

//dashboard inconsistencias

Route::prefix('dashboardInc')->group(function () {
    // Métricas principales
    Route::get('/metricas/productividad', [DashboardIncController::class, 'getProductividad']);
    Route::get('/metricas/costos', [DashboardIncController::class, 'getCostos']);
    Route::get('/metricas/consumo', [DashboardIncController::class, 'getConsumo']);
    Route::get('/metricas/gestion-humana', [DashboardIncController::class, 'getGestionHumana']);
    
    // Datos para filtros
    Route::get('/filtros/departamentos', [DashboardIncController::class, 'getDepartamentos']);
    Route::get('/filtros/clientes', [DashboardIncController::class, 'getClientes']);
    Route::get('/filtros/tipos', [DashboardIncController::class, 'getTiposInconsistencia']);
    Route::get('/filtros/usuarios', [DashboardIncController::class, 'getUsuarios']);
    
    // Dashboard general
    Route::get('/dashboard', [DashboardIncController::class, 'getDashboardData']);
});



/* ===============================
|  MIDLEWARES - SANITIZACIÓN
|=============================== */

Route::middleware(SanitizeInput::class)->group(function () {

     // Aqui se agregan las funciones que necesitan sanitización en el request.

});
