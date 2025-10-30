<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TerminacionEmpaqueModel extends Model
{
    protected $connection = 'siesa'; // conexiиоn definida en config/database.php
    protected $table = 't850_mf_op_docto'; // nombre exacto de la tabla
    protected $primaryKey = 'f850_rowid'; // la PK real de esta tabla
    public $timestamps = false; // si no tiene columnas created_at y updated_at
}
