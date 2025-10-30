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
}
