<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\InventarioItemZonas;
use Illuminate\Support\Facades\DB;

class InventarioMovimientosInventario extends Model
{
    protected $connection = 'siesa';
    protected $table = 't470_cm_movto_invent';
    protected $primaryKey = 'f470_rowid';
    public $incrementing = false;
    public $timestamps = false;

    /**
     * Listar bodegas con cantidad de ítems y existencias totales
     */
    public static function resumenBodegas()
    {
        return DB::connection('siesa')
            ->table('t400_cm_existencia AS e')
            ->select([
                'b.f150_id AS codigo',
                'b.f150_descripcion AS nombre_bodega',
                DB::raw('COUNT(DISTINCT e.f400_rowid_item_ext) AS total_items'),
                DB::raw('SUM(e.f400_cant_existencia_1) AS total_existencias')
            ])
            ->join('t150_mc_bodegas AS b', 'b.f150_rowid', '=', 'e.f400_rowid_bodega')
            ->where('e.f400_cant_existencia_1', '>', 0)
            ->whereIn('b.f150_id', ['MP001', 'MP003', 'BT001'])
            ->groupBy('b.f150_id', 'b.f150_descripcion')
            ->orderBy('b.f150_id', 'ASC')
            ->get();
    }
    
    public static function listarPorBodega(string $codigoBodega)
    {
        // Subconsulta que devuelve el último movimiento por ítem y bodega
        $ultimoMovimiento = DB::connection('siesa')
            ->table(DB::raw("
                (
                    SELECT 
                        m.f470_rowid_item_ext,
                        m.f470_rowid_bodega,
                        m.f470_costo_prom_uni,
                        m.f470_costo_prom_tot,
                        ROW_NUMBER() OVER (PARTITION BY m.f470_rowid_item_ext, m.f470_rowid_bodega ORDER BY m.f470_id_fecha DESC, m.f470_rowid DESC) AS rn
                    FROM t470_cm_movto_invent m
                ) ult
            "))
            ->where('ult.rn', 1);
    
        $items = DB::connection('siesa')
            ->table('t400_cm_existencia AS e')
            ->select([
                'i.f120_id AS id_item',
                'i.f120_referencia AS referencia',
                'i.f120_descripcion AS descripcion',
                'e.f400_fecha_ult_entrada AS fecha',
                'e.f400_cant_existencia_1 AS cantidad',
                'e.f400_rowid_item_ext AS id_f400',
                'i.f120_id_unidad_inventario AS unidad_medida',
                DB::raw('ISNULL(ult.f470_costo_prom_uni, e.f400_costo_prom_uni) AS costo_prom_unitario'),
                DB::raw('ISNULL(ult.f470_costo_prom_tot, e.f400_costo_prom_tot) AS costo_prom_total'),
                'b.f150_id AS codigo_bodega',
                'b.f150_descripcion AS nombre_bodega',
                'col_det.f117_descripcion AS color',
                'tal_det.f119_id AS id_talla'
            ])
            ->join('t150_mc_bodegas AS b', 'b.f150_rowid', '=', 'e.f400_rowid_bodega')
            ->join('t121_mc_items_extensiones AS ext', 'ext.f121_rowid', '=', 'e.f400_rowid_item_ext')
            ->join('t120_mc_items AS i', 'i.f120_rowid', '=', 'ext.f121_rowid_item')
            ->leftJoinSub($ultimoMovimiento, 'ult', function ($join) {
                $join->on('ult.f470_rowid_item_ext', '=', 'e.f400_rowid_item_ext')
                     ->on('ult.f470_rowid_bodega', '=', 'e.f400_rowid_bodega');
            })
            ->leftJoin('t117_mc_extensiones1_detalle AS col_det', function ($join) {
                $join->on('col_det.f117_id', '=', 'ext.f121_id_ext1_detalle')
                     ->on('col_det.f117_id_extension1', '=', 'ext.f121_id_extension1');
            })
            ->leftJoin('t119_mc_extensiones2_detalle AS tal_det', function ($join) {
                $join->on('tal_det.f119_id', '=', 'ext.f121_id_ext2_detalle')
                     ->on('tal_det.f119_id_extension2', '=', 'ext.f121_id_extension2');
            })
    
            ->where('b.f150_id', '=', $codigoBodega)
            ->where('e.f400_cant_existencia_1', '>', 0)
            ->orderBy('i.f120_id', 'DESC')
            ->get();
    
        // 🔸 Obtener zonas
        foreach ($items as $item) {
            $zonasAsignadas = InventarioItemZonas::obtenerZonasDeItem($item->id_item, $item->codigo_bodega, $item->id_f400);
    
            if ($zonasAsignadas->isEmpty()) {
                $item->zonas = [[
                    'id' => null,
                    'nombre' => 'Sin zona',
                    'descripcion' => null
                ]];
            } else {
                $item->zonas = $zonasAsignadas->map(function($zona) {
                    return [
                        'id' => $zona->id,
                        'nombre' => $zona->nombre,
                        'descripcion' => $zona->descripcion ?? null
                    ];
                })->toArray();
            }
        }
    
        return $items;
    }
}