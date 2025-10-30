<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReporteLlegadaEmpaqueModelo extends Model
{
    use HasFactory;

    // 🔗 Conexión específica
    protected $connection = 'providencia_renueva_bigbag';

    protected $table = 'reporte_llegada_empaques';
    protected $primaryKey = 'id_reporte_llegada_empaque';

    protected $fillable = [
        'fecha_ingreso',
        'hora_llegada',
        'planta',
        'num_remision',
        'cant_relacionada',
        'nom_operario',
        'firma_operario',
        'observaciones',
        'nom_conductor',
        'placa_vehiculo',
        'nom_transportador',
        'firma_conductor',
        'cantidad_fisico',
        'diferencia_reportada',
        'num_recepcion',
        'user_id',
        'estado',
        'last_name',
        'firts_name',
        'fecha_creacion',
        'hora_creacion',
        'cliente'
    ];

    protected $casts = [
        'fecha_ingreso' => 'date',
        'fecha_creacion' => 'date',
        'hora_llegada' => 'datetime:H:i:s',
        'hora_creacion' => 'datetime:H:i:s',
    ];

    // Relación con precintos
    public function precintos()
    {
        return $this->hasMany(EntregaPrecintoBigbagModelo::class, 'id_reporte_llegada', 'id_reporte_llegada_empaque');
    }

    // Scopes para filtros
    public function scopeByDateRange($query, $start, $end)
    {
        return $query->whereBetween('fecha_ingreso', [$start, $end]);
    }

    //======= DASHBOARD ======//

    public static function getOverview($startDate, $endDate)
    {
        $overview = self::whereBetween('fecha_ingreso', [$startDate, $endDate])
            ->selectRaw('
                COUNT(DISTINCT id_reporte_llegada_empaque) as total_reportes,
                SUM(cantidad_fisico) as total_empaques,
                COUNT(DISTINCT cliente) as clientes_unicos,
                COUNT(DISTINCT fecha_ingreso) as dias_con_recepcion,
                MAX(CONCAT(fecha_creacion, " ", hora_creacion)) as ultima_actualizacion
            ')
            ->first();

        $totalPrecintos = DB::connection('providencia_renueva_bigbag')
            ->table('reporte_llegada_empaques as r')
            ->leftJoin('entrega_precintos_bigbag as e', 'r.id_reporte_llegada_empaque', '=', 'e.id_reporte_llegada')
            ->whereBetween('r.fecha_ingreso', [$startDate, $endDate])
            ->sum('e.cantidad');

        $overview->total_precintos = $totalPrecintos ?? 0;

        return $overview;
    }

    public static function getEstados($startDate, $endDate)
    {
        return self::whereBetween('fecha_ingreso', [$startDate, $endDate])
            ->groupBy('estado')
            ->selectRaw('estado, COUNT(*) as total')
            ->get();
    }

    public static function getClientes()
    {
        return self::distinct()
            ->whereNotNull('cliente')
            ->where('cliente', '!=', '')
            ->orderBy('cliente')
            ->pluck('cliente');
    }

    public static function getTopClientes($startDate, $endDate, $limit = 10)
    {
        return self::whereBetween('fecha_ingreso', [$startDate, $endDate])
            ->whereNotNull('cliente')
            ->where('cliente', '!=', '')
            ->groupBy('cliente')
            ->selectRaw('cliente, SUM(cantidad_fisico) as total_empaques')
            ->orderByDesc('total_empaques')
            ->limit($limit)
            ->get();
    }

    public static function getPorFecha($startDate, $endDate, $cliente = null)
    {
        $query = self::whereBetween('fecha_ingreso', [$startDate, $endDate]);
        
        if ($cliente) {
            $query->where('cliente', $cliente);
        }

        return $query->groupBy('fecha_ingreso')
            ->selectRaw('fecha_ingreso, SUM(cantidad_fisico) as total_empaques, COUNT(*) as total_reportes')
            ->orderBy('fecha_ingreso')
            ->get();
    }

    //======= FIRMAS ======//

    public static function obtenerFirma($recepcionId, $tipoFirma)
    {
        if (!in_array($tipoFirma, ['operario', 'conductor'])) {
            return null;
        }

        $campoFirma = $tipoFirma === 'operario' ? 'firma_operario' : 'firma_conductor';
        $recepcion = self::find($recepcionId);

        if (!$recepcion || !$recepcion->{$campoFirma}) {
            return null;
        }

        return [
            'ruta'          => $recepcion->{$campoFirma},
            'tipo_firma'    => $tipoFirma,
            'num_recepcion' => $recepcion->num_recepcion,
        ];
    }
}
