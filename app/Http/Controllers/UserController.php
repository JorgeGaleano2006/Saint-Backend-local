<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class UserController extends Controller
{
    public function obtenerPorRoles(Request $request)
    {
        $validated = $request->validate([
            'roles' => 'required|array',
            'roles.*' => 'integer'
        ]);

        try {
            $usuarios = User::obtenerUsuariosPorRoles($validated['roles']);

            return response()->json([
                'success' => true,
                'usuarios' => $usuarios
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

   public function disableUser(Request $request)
{
    $validated = $request->validate([
        'user_id' => 'required|integer',
    ]);

    $userId = $validated['user_id'];

    // llamado al modelo
    $disabled = User::disableUser($userId);

    if ($disabled) {
        return response()->json([
            'success' => true,
            'message' => 'Usuario deshabilitado correctamente.'
        ]);
    } else {
        return response()->json([
            'success' => false,
            'message' => 'No se pudo deshabilitar el usuario o no existe.'
        ], 404);
    }
}

}
