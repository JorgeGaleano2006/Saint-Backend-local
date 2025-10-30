<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class UsuarioOperarioRenueva extends Model
{
    protected $connection = 'providencia_renueva_bigbag'; 

    public static function obtenerOperarios()
    {
        return DB::connection('pgsql') 
            ->table('users')
            ->join('users_roles', 'users.id', '=', 'users_roles.users_id')
            ->select(
                'users.id',
                DB::raw("CONCAT(users.first_name, ' ', users.last_name) as nombre_completo")
            )
            ->where('users_roles.roles_id', 18)
            ->get();
    }
}
