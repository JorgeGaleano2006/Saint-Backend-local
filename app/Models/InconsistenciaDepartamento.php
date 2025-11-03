<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InconsistenciaDepartamento extends Model
{
    protected $connection = 'solicitud_de_permisos_local';
    protected $table = 'departamentos';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_departamento',
        'nombre_departamento',
        'id_lider',
    ];

    // Relaciones
    public function usuarios()
    {
        return $this->hasMany(InconsistenciaUsuario::class, 'id_departamento');
    }

    public function inconsistencias()
    {
        return $this->hasMany(InconsistenciaModelo::class, 'id_departamento');
    }
}
