<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventarioLogs extends Model
{
    protected $connection = 'conteo_inventario';
    protected $table = 'inventario_logs';
    public $timestamps = false;

    protected $fillable = [
        'usuario_id',
        'accion',
        'modulo',
        'descripcion',
        'datos_antes',
        'datos_despues',
        'created_at'
    ];

    public static function registrar($usuarioId, $accion, $modulo, $descripcion = null, $antes = null, $despues = null)
    {
        self::create([
            'usuario_id' => $usuarioId,
            'accion' => $accion,
            'modulo' => $modulo,
            'descripcion' => $descripcion,
            'datos_antes' => $antes ? json_encode($antes) : null,
            'datos_despues' => $despues ? json_encode($despues) : null,
            'created_at' => now(),
        ]);
    }
}
