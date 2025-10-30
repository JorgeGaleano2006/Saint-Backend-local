<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TechnicalDataSheetsProducCategoryModel extends Model
{
    protected $connection = 'pgsql'; // Si usas la misma conexión
    protected $table = 'product_category';
    public $timestamps = false;

    protected $fillable = [
        'id_product_category',
        'description'
    ];
}
