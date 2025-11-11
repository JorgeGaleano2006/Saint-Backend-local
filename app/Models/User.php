<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class User extends Model
{
    // Conexi贸n a la base de datos PostgreSQL
    protected $connection = 'pgsql';
    
    // Nombre de la tabla en PostgreSQL
    protected $table = 'users';

    // Clave primaria
    protected $primaryKey = 'id';

    // Si la tabla tiene timestamps
    public $timestamps = false;

    // Campos asignables masivamente
    protected $fillable = [
        'email',
        'enabled',
        'first_name',
        'last_name',
        'password',
        'name'
        
    ];

    // Ocultar campos sensibles
    protected $hidden = [
        'password'
    ];
    
     /**
     * Relación con roles (tabla pivote: users_roles)
     */
     public function roles()
    {
        return $this->belongsToMany(Role::class, 'users_roles', 'users_id', 'roles_id');
    }
    

    public static function obtenerUsuariosPorRoles(array $rolesId)
    {
        $usuarios = DB::connection('pgsql')
            ->table('users')
            ->join('users_roles', 'users.id', '=', 'users_roles.users_id')
            ->join('roles', 'roles.id', '=', 'users_roles.roles_id')
            ->select(
                'users.id',
                DB::raw("CONCAT(users.first_name, ' ', users.last_name) as nombre_completo"),
                'users.email',
                DB::raw("(SELECT json_object_agg(r.id, r.name)
                          FROM users_roles ur
                          INNER JOIN roles r ON r.id = ur.roles_id
                          WHERE ur.users_id = users.id
                         ) as roles")
            )
            ->whereExists(function ($query) use ($rolesId) {
                $query->select(DB::raw(1))
                      ->from('users_roles as ur2')
                      ->whereColumn('ur2.users_id', 'users.id')
                      ->whereIn('ur2.roles_id', $rolesId);
            })
            ->groupBy('users.id', 'users.first_name', 'users.last_name', 'users.email')
            ->get();
    
        return $usuarios->map(function ($usuario) {
            $usuario->roles = json_decode($usuario->roles, true);
            return $usuario;
        });
    }


   public static function disableUser($userId)
{
    return self::where('id', $userId)
        ->update(['enabled' => false]);
}

}
