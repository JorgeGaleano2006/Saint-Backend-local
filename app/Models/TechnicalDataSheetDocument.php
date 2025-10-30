<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class TechnicalDataSheetDocument extends Model
{
    // Conexión a la base de datos PostgreSQL
    protected $connection = 'pgsql';

    // Tabla asociada
    protected $table = 'technical_data_sheet_documents';

    // Si la tabla no usa timestamps
    public $timestamps = false;

    // Campos que se pueden asignar masivamente
    protected $fillable = [
        'id_register_item_document',
        'rute_document',
        'version_document'
    ];

    // 🔹 Obtener la última versión registrada para un documento
    public static function obtenerUltimaVersion($id)
    {
        return self::where('id_register_item_document', $id)->max('version_document');
    }

    // 🔹 Obtener el documento actual (última versión)
    public static function obtenerDocumentoActual($id)
    {
        return self::where('id_register_item_document', $id)
            ->orderByDesc('version_document')
            ->first();
    }

    // 🔹 Obtener las últimas N versiones
    public static function obtenerUltimasVersiones($id, $limit = 3)
    {
        return self::where('id_register_item_document', $id)
            ->orderByDesc('version_document')
            ->limit($limit)
            ->get();
    }

    // 🔹 Obtener URL temporal desde S3
    public function obtenerUrlFirmada()
    {
        if (Storage::disk('s3')->exists($this->rute_document)) {
            return Storage::disk('s3')->temporaryUrl($this->rute_document, now()->addMinutes(10));
        }

        return null;
    }
}
