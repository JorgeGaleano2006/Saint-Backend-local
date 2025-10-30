<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ActivityModel extends Model
{
    // 🔗 Conexión específica
    protected $connection = 'providencia_renueva_bigbag';

    protected $table = 'recepciones_log';
    
    // Si no usas timestamps automáticos de Laravel
    public $timestamps = false;
    
    protected $fillable = [
        // Agrega aquí los campos que quieras que sean mass assignable
    ];

    /**
     * Obtener todas las actividades
     */
    public static function traerActividades()
    {
        return self::all()->toArray();
    }

    /**
     * Obtener versiones por número de recepción
     */
    public static function traerVersiones($num_recepcion)
    {
        return DB::connection('providencia_renueva_bigbag')
            ->table('versiones_recepcion')
            ->where('num_recepcion', $num_recepcion)
            ->orderBy('id_version', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Obtener versión actual por número de recepción
     */
    public static function traerVersionActual($num_recepcion)
    {
        return DB::connection('providencia_renueva_bigbag')
            ->table('reporte_llegada_empaques')
            ->select('cant_relacionada', 'cantidad_fisico', 'diferencia_reportada')
            ->where('num_recepcion', $num_recepcion)
            ->get()
            ->toArray();
    }

    /**
     * Obtener actividades con sus versiones (método optimizado)
     */
    public static function traerActividadesConVersiones()
    {
        $actividades = self::traerActividades();
        
        foreach ($actividades as &$actividad) {
            if (isset($actividad['num_recepcion'])) {
                $actividad['versiones'] = self::traerVersiones($actividad['num_recepcion']);
                $actividad['version_actual'] = self::traerVersionActual($actividad['num_recepcion']);
            } else {
                $actividad['versiones'] = [];
                $actividad['version_actual'] = [];
            }
        }
        
        return $actividades;
    }
}
