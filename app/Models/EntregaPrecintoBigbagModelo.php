<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class EntregaPrecintoBigbagModelo extends Model
{
    use HasFactory;

    // 🔗 Conexión a la base de datos específica
    protected $connection = 'providencia_renueva_bigbag';

    protected $table = 'entrega_precintos_bigbag';
    
    protected $fillable = [
        'id_reporte_llegada',
        'color_consecutivo',
        'cantidad',
        'novedades_precintos',
        'firmado_por'
    ];

    // Relación con el reporte de llegada
    public function reporte()
    {
        return $this->belongsTo(
            ReporteLlegadaEmpaqueModelo::class,
            'id_reporte_llegada',
            'id_reporte_llegada_empaque'
        );
    }

    /**
     * Actualiza la novedad y firma digital de un precinto
     */
    public static function actualizarNovedadYFirma($precintoId, $novedad, $firmaPath)
    {
        return self::where('id', $precintoId)
            ->update([
                'novedades_precintos' => $novedad,
                'firmado_por' => $firmaPath,
                'updated_at' => now()
            ]);
    }

    /**
     * Obtiene distribución de precintos por color en un rango de fechas
     */
    public static function getPorColor($startDate, $endDate)
    {
        return DB::connection('providencia_renueva_bigbag')
            ->table('entrega_precintos_bigbag as e')
            ->join('reporte_llegada_empaques as r', 'e.id_reporte_llegada', '=', 'r.id_reporte_llegada_empaque')
            ->whereBetween('r.fecha_ingreso', [$startDate, $endDate])
            ->where('e.cantidad', '>', 0)
            ->groupBy('e.color_consecutivo')
            ->selectRaw('
                COALESCE(e.color_consecutivo, "Sin especificar") as color,
                SUM(e.cantidad) as total_precintos
            ')
            ->orderByDesc('total_precintos')
            ->get();
    }
}
