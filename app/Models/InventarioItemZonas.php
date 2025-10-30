<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class InventarioItemZonas extends Model
{
    protected $connection = 'conteo_inventario';
    protected $table = 'item_zona';
    protected $fillable = [
        'codigo_item',
        'id_f400',
        'codigo_bodega',
        'id_zona',
    ];
    public $timestamps = false;
    
    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    public function zona()
    {
        return $this->belongsTo(InventarioZonas::class, 'id_zona');
    }

    /**
     * Asigna zonas a un conjunto de ítems (permite múltiples zonas por ítem)
     */
    public static function asignarZonas(array $payload): void
    {
        foreach ($payload as $item) {
            // Verificar si ya existe esta combinación exacta
            $existe = self::where('codigo_item', $item['codigo_item'])
                ->where('codigo_bodega', $item['codigo_bodega'])
                ->where('id_zona', $item['id_zona'])
                ->where('id_f400', $item['id_f400'])
                ->exists();

            // Solo crear si no existe (evita duplicados)
            if (!$existe) {
                self::create([
                    'codigo_item' => $item['codigo_item'],
                    'codigo_bodega' => $item['codigo_bodega'],
                    'id_zona' => $item['id_zona'],
                    'id_f400' => $item['id_f400'],
                    'assigned_at' => now(),
                ]);
            }
        }
    }

    /**
     * Obtener TODAS las zonas de un ítem (en lugar de una sola)
     */
    public static function obtenerZonasDeItem(string $codigoItem, string $codigoBodega, string $id_f400)
    {
        return self::where('codigo_item', $codigoItem)
            ->where('codigo_bodega', $codigoBodega)
            ->where('id_f400', $id_f400)
            ->with('zona')
            ->get()
            ->pluck('zona') // 🔹 Extrae solo el objeto zona
            ->filter(); // 🔹 Elimina nulls por si hay zonas sin relación
    }

    /**
     * Eliminar una zona específica de un ítem
     */
    public static function eliminarZona(string $codigoItem, string $codigoBodega, string $id_f400, int $idZona): bool
    {
        return self::where('codigo_item', $codigoItem)
            ->where('codigo_bodega', $codigoBodega)
            ->where('id_zona', $idZona)
            ->where('id_f400', $id_f400)
            ->delete() > 0;
    }
}