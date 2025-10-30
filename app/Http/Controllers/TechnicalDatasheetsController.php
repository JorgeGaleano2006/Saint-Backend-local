<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\TechnicalDatasheetsModel;
use Exception;

class TechnicalDatasheetsController extends Controller
{
    //
   public function ListTechnicalDataSheets(Request $request)
    {
        try {
            $statusValue = $request->input('status');
    
            if (!$statusValue) {
                return response()->json([
                    'success' => false,
                    'message' => 'El campo status es obligatorio.'
                ], 400);
            }
    
            // Validar que el status esté permitido
            // $allowedStatuses = [
            //     'PRIMERA REVISION',
            //     'SEGUNDA REVISION',
            //     'DESARROLLO',
            //     'TERMINADO',
            //     'CALIDAD'
            // ];
    
            // if (!in_array($statusValue, $allowedStatuses)) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'El valor de status no es válido.'
            //     ], 400);
            // }
    
            $data = TechnicalDatasheetsModel::getFilteredTechnicalDataSheets($statusValue);
    
            return response()->json([
                'success' => true,
                'message' => 'Fichas técnicas obtenidas correctamente.',
                'data' => $data
            ], 200);
    
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al obtener las fichas técnicas.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

}
