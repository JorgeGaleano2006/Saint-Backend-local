<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    // Nombre de la tabla
    protected $table = 'roles';
    // Clave primaria
    protected $primaryKey = 'id';
    //campos de la tabla
    protected $fillable = ['name'];
    public $timestamps = false;

    public function users()
    {
        return $this->belongsToMany(User::class, 'users_roles', 'roles_id', 'users_id');
    }
}
