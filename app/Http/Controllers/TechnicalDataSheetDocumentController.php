<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\TechnicalDataSheetDocument;

class TechnicalDataSheetDocumentController extends Controller
{
    // 🔹 Subir documento y guardar en BD
    public function SaveDocumentTechnicalDataSheets(Request $request)
    {
        $request->validate([
            'id_register_item_document' => 'required|integer',
            'documento' => 'required|file|mimes:pdf|max:102400', // 100MB
        ]);

        $file = $request->file('documento');

        // Generar nombre único para el archivo
        $filename = 'fichas-tecnicas/' . uniqid() . '_' . $file->getClientOriginalName();

        // Subir archivo al bucket S3
        Storage::disk('s3')->put($filename, file_get_contents($file), 'private');

        // Obtener última versión y definir la siguiente
        $nuevaVersion = TechnicalDataSheetDocument::obtenerUltimaVersion($request->id_register_item_document);
        $nuevaVersion = $nuevaVersion ? $nuevaVersion + 1 : 1;

        // Guardar en base de datos
        $registro = TechnicalDataSheetDocument::create([
            'id_register_item_document' => $request->id_register_item_document,
            'rute_document' => $filename,
            'version_document' => $nuevaVersion
        ]);

        return response()->json([
            'message' => 'Documento guardado correctamente.',
            'id' => $registro->id,
            'version' => $nuevaVersion,
            'path' => $filename
        ], 201);
    }

    // 🔹 Obtener URL del documento actual
    public function GetDocumentByRegisterTechnicalDataSheets($id)
    {
        $documento = TechnicalDataSheetDocument::obtenerDocumentoActual($id);

        if (!$documento) {
            return response()->json(['error' => 'Documento no encontrado'], 404);
        }

        $url = $documento->obtenerUrlFirmada();

        if (!$url) {
            return response()->json(['error' => 'Archivo no disponible en S3'], 404);
        }

        return response()->json(['url' => $url]);
    }

    // 🔹 Obtener historial de últimas versiones (hasta 3)
    public function GetLastDocumentVersions($id, $limit = 3)
    {
        $documentos = TechnicalDataSheetDocument::obtenerUltimasVersiones($id, $limit);

        if ($documentos->isEmpty()) {
            return response()->json(['error' => 'No hay documentos disponibles'], 404);
        }

        $resultados = [];

        foreach ($documentos as $doc) {
            $url = $doc->obtenerUrlFirmada();

            if ($url) {
                $resultados[] = [
                    'id' => $doc->id,
                    'version_document' => $doc->version_document,
                    'url' => $url
                ];
            }
        }

        return response()->json($resultados);
    }
}
