<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InconsistenciaUsuario extends Model
{
    protected $connection = 'solicitud_de_permisos_local';
    protected $table = 'usuarios';
    protected $primaryKey = 'id_usuario';
    public $timestamps = false;

    protected $fillable = [
        'cedula',
        'nombres',
        'apellidos',
        'usuario',
        'contrasena',
        'correo',
        'id_departamento',
        'rol',
        'estado'
    ];

     public static function info_usuario($correo)
    {
        $usuario = self::select('departamentos.nombre_departamento as departamento')
            ->join('departamentos', 'usuarios.id_departamento', '=', 'departamentos.id_departamento')
            ->where('usuarios.correo', $correo)
            ->first();

        return $usuario->departamento ?? 'Departamento no definido';
    }
}
