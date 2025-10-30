<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cliente;

class ClienteController extends Controller
{
    /**
     * Obtener clientes desde Siesa filtrados por palabra clave
     */
    public function obtenerClientes($word)
    {
        $word = trim($word);

        if (strlen($word) < 2) {
            return response()->json([
                'success' => false,
                'error'   => 'El término de búsqueda debe tener al menos 2 caracteres'
            ], 400);
        }

        $clientes = Cliente::buscarPorPalabra($word);

        return response()->json([
            'success' => true,
            'data'    => $clientes
        ]);
    }
}


