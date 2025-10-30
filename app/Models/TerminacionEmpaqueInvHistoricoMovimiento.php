<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TerminacionEmpaqueInvHistoricoMovimiento extends Model
{
    protected $connection = 'terminacion_empaque';
    protected $table = 'inv_historial_movimientos';
    public $timestamps = false;

    protected $fillable = [
        'tipo',
        'op_codigo',
        'pv_codigo',
        'item_hash',
        'referencia',
        'id_item',
        'descripcion',
        'id_color',
        'id_talla',
        'cantidad',
        'usuario',
        'created_at'
    ];
}
