<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Exception;
use App\Models\TechnicalDataSheetsProducCategoryModel;

class TechnicalDatasheetsModel extends Model
{
   // Usamos la conexiÃ³n a Saint que es PostgreSQL
    protected $connection = 'pgsql';

    // Nombre de la tabla de los documentos de la ficha tÃ©cnica
    protected $table = 'technical_data_sheet';

    // Si no estÃ¡s usando timestamps (created_at, updated_at)
    public $timestamps = false;
    protected $fillable = [
        'id',
        'additional',
        'back',
        'bill_materials',
        'boot',
        'rib',
        'button',
        'buttonhole',
        'characteristic_image_1',
        'characteristic_image_2',
        'characteristic_image_3',
        'characteristic_image_4',
        'closed_sides',
        'company_name',
        'composition',
        'contrast_fabric',
        'critical_points',
        'crotch',
        'cuffs',
        'customer_description',
        'cuts',
        'darts',
        'date_creation',
        'edit_comments',
        'embroidery',
        'figured',
        'finished',
        'front_adjustment',
        'gender',
        'hem',
        'hood',
        'id_company',
        'id_item',
        'id_item_customer',
        'ironing',
        'item_description',
        'last_update',
        'lining',
        'logo_description',
        'logo_technical_data_sheet',
        'loops',
        'main_fabric',
        'measurement_table',
        'neckline',
        'observations',
        'opening',
        'packaging',
        'pins',
        'pockets',
        'prewash',
        'product_image_1',
        'product_image_2',
        'purses',
        'qa_comments',
        'reflective',
        'shirt_collar',
        'shoulder_union',
        'shoulders',
        'side_pulls',
        'side_stand',
        'sleeve_connection',
        'sleeves',
        'stamped',
        'status',
        'stitches',
        'stitching',
        'straps',
        'technical_data_sheet_type',
        'user_approved',
        'user_created',
        'user_validation',
        '"version"',
        'waistband',
        'zipper',
        'id_product_category'
    ];


    // ðŸ”¹ Consulta filtrada
    public static function getFilteredTechnicalDataSheets($status)
    {
        try {
            // 1️⃣ Traer datos planos
            $data = self::from('technical_data_sheet as t')
                ->select(
                    'id',
                    'company_name',
                    'date_creation',
                    'last_update',
                    'id_item',
                    'item_description',
                    'status',
                    'technical_data_sheet_type',
                    'pc.id_product_category',
                    'pc.description as category_description'
                )
                ->join('product_category as pc', 'pc.id_product_category', '=', 't.id_product_category')
                ->whereIn('t.technical_data_sheet_type', [
                    'FICHA TECNICA',
                    'FICHAS TECNICAS',
                    'OPM'
                ])
                ->where('t.status', $status)
                ->orderBy('t.id', 'desc')
                ->get();
    
            // 2️⃣ Construir el JSON en PHP
            $data->transform(function ($item) {
                $item->productCategory = [
                    'id_product_category' => $item->id_product_category,
                    'description' => $item->category_description
                ];
                unset($item->id_product_category, $item->category_description);
                return $item;
            });
    
            return $data;
    
        } catch (Exception $e) {
            throw new Exception("Error al obtener fichas técnicas: " . $e->getMessage());
        }
    }

}