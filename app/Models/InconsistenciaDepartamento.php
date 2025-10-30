<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Departamento extends Model
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
        return $this->hasMany(Usuario::class, 'id_departamento');
    }

    public function inconsistencias()
    {
        return $this->hasMany(Inconsistencia::class, 'id_departamento');
    }
}
