<?php



namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class BigBagModel
{
    protected $connection = 'providencia_renueva_bigbag'; // conexión específica

    // === Manejo de archivos para número de recepción ===
    public function obtenerUltimoNumero($archivo)
    {
        $rutaArchivo = storage_path('app/' . $archivo);
        if (!File::exists($rutaArchivo)) {
            File::put($rutaArchivo, 1);
            return 1;
        }
        return (int) File::get($rutaArchivo);
    }

    public function guardarProximoNumero($archivo, $nuevoNum)
    {
        $rutaArchivo = storage_path('app/' . $archivo);
        File::put($rutaArchivo, $nuevoNum);
    }

    // === Inserción de recepción ===
    public function insertarRecepcion($datos)
    {
        try {
            $db = DB::connection($this->connection); // usar la conexión específica
            $db->beginTransaction();

            $id = $db->table('reporte_llegada_empaques')->insertGetId([
                'user_id' => $datos['user_id'],
                'fecha_ingreso' => $datos['fecha_ingreso'],
                'hora_llegada' => $datos['hora_ingreso'],
                'planta' => $datos['planta'],
                'num_remision' => $datos['remision'],
                'cant_relacionada' => $datos['cantidad_relacionada'],
                'nom_operario' => $datos['nom_operario'],
                'observaciones' => $datos['observaciones'],
                'nom_conductor' => $datos['nom_conductor'],
                'placa_vehiculo' => $datos['placa_vehiculo'],
                'nom_transportador' => $datos['empresa_transporte'],
                'cantidad_fisico' => $datos['cantidad_fisico'],
                'diferencia_reportada' => $datos['diferencia_reportada'],
                'cliente' => $datos['cliente'],
                'firma_operario' => $datos['ruta_firma'],
                'firma_conductor' => $datos['ruta_firma_conductor'],
                'num_recepcion' => $datos['num_recepcion'],
                'fecha_creacion' => now(),
                'hora_creacion' => now()
            ]);

            // $usuario = $db->table('usuarios')->where('id_usuario', $datos['user_id'])->first();
            
            $usuario = $datos['nom_operario'];
            $partes = explode(' ', $usuario);
            $firts_name = $partes[0];
            $last_name = $partes[1];

            $this->insertarLogRecepcion([
                'user_id' => $datos['user_id'],
                'num_recepcion' => $datos['num_recepcion'],
                'accion' => 'Subió',
                'firts_name' => $firts_name ?? null,
                'last_name' => $last_name ?? null,
                'comentario' => null
            ]);

            $db->commit();

            return ['success' => true, 'id' => $id];
        } catch (\Exception $e) {
            DB::connection($this->connection)->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // === Inserción en log (método mejorado) ===
    public function insertarLogRecepcion($datosLog)
    {
        try {
            DB::connection($this->connection)->table('recepciones_log')->insert([
                'user_id' => $datosLog['user_id'],
                'num_recepcion' => $datosLog['num_recepcion'],
                'accion' => $datosLog['accion'],
                'fecha_accion' => now(),
                'firts_name' => $datosLog['firts_name'] ?? null,
                'last_name' => $datosLog['last_name'] ?? null,
                'comentario' => $datosLog['comentario'] ?? null
            ]);

            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // === Método para registrar ediciones ===
    public function registrarEdicionRecepcion($datos)
    {
        try {
            $db = DB::connection($this->connection);
            $usuario = $db->table('usuarios')->where('id_usuario', $datos['user_id'])->first();

            $this->insertarLogRecepcion([
                'user_id' => $datos['user_id'],
                'num_recepcion' => $datos['num_recepcion'],
                'accion' => 'Editó',
                'firts_name' => $usuario->primer_nombre ?? null,
                'last_name' => $usuario->segundo_nombre ?? null,
                'comentario' => $datos['comentario'] ?? null
            ]);

            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // === Consultas de recepciones ===
    public function obtenerRecepcionesPorUsuario($userId)
    {
        return DB::connection($this->connection)
            ->table('recepciones_bigbag')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
