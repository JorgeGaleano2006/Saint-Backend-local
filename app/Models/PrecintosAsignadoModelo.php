<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Model;

class PrecintosAsignadoModelo extends Model
{
    protected $table = 'entrega_precintos_bigbag';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $connection = 'providencia_renueva_bigbag'; // 👈 Nueva conexión

    // Campos que se pueden asignar masivamente
    protected $fillable = [
        'novedades_precintos',
        'firmado_por'
    ];

   public static function obtenerPrecintosPorResponsable($userId)
{
    return DB::connection('providencia_renueva_bigbag')
        ->table('entrega_precintos_bigbag as epb')
        ->join(
            'reporte_llegada_empaques as rle',
            'epb.id_reporte_llegada',
            '=',
            'rle.id_reporte_llegada_empaque'
        )
        ->select(
            'epb.*',
            // Información adicional de la recepción
            'rle.num_recepcion',
            'rle.fecha_ingreso',
            'rle.hora_llegada',
            'rle.planta',
            'rle.num_remision',
            'rle.nom_conductor',
            'rle.placa_vehiculo',
            'rle.cliente'
        )
        ->where('epb.id_responsable', $userId)
        ->orderByDesc('epb.created_at')
        ->get();
}

    public static function obtenerPrecintoConNovedad($precintoId)
    {
        return DB::connection('providencia_renueva_bigbag') // 👈 Actualizado
            ->table('entrega_precintos_bigbag')
            ->where('id', $precintoId)
            ->first();
    }

    public static function actualizarNovedadYFirma($precintoId, $novedad, $firmaUrl, $userId)
    {
        return DB::connection('providencia_renueva_bigbag') // 👈 Actualizado
            ->table('entrega_precintos_bigbag')
            ->where('id', $precintoId)
            ->update([
                'novedades_precintos' => $novedad,
                'firmado_por' => $firmaUrl
            ]);
    }
}
