<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventarioZonas extends Model
{
    protected $connection = 'conteo_inventario';
    protected $table = 'zonas';
    
    protected $fillable = [
        'nombre',
        'descripcion',
        'activo'
    ];

    public $timestamps = true;

    protected $casts = [
        'activo' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public static function crearZona(array $data)
    {
        return self::create([
            'nombre' => $data['nombre'],
            'descripcion' => $data['descripcion'] ?? null,
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    /**
     * Relación con items asignados a esta zona
     */
    public function itemsAsignados()
    {
        return $this->hasMany(InventarioItemZonas::class, 'id_zona');
    }

    /**
     * Obtener todas las zonas activas
     */
    public static function zonasActivas()
    {
        return self::where('activo', true)
            ->orderBy('nombre', 'asc')
            ->get();
    }

    /**
     * Obtener zonas con conteo de items
     */
    public static function conEstadisticas()
    {
        return self::withCount('itemsAsignados as total_items')
            ->orderBy('nombre', 'asc')
            ->get();
    }
}