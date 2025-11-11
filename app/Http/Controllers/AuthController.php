<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Models\User;
use Exception;

class AuthController extends Controller
{
    /**
     * Login con hash SHA512 y JWT RS256
     */
    public function login(Request $request)
{
    $request->validate([
        'email'    => 'required|email',
        'password' => 'required'
    ]);

    // Buscar usuario junto con sus roles
    $user = User::with('roles')->where('email', $request->email)->first();

    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'Usuario no encontrado'
        ], 401);
    }

    // Validar si el usuario está habilitado (tolerante a tipos de datos)
    if (!$user->enabled || $user->enabled === false || $user->enabled === 0 || $user->enabled === 'f') {
        return response()->json([
            'success' => false,
            'message' => 'El usuario está deshabilitado. Comuníquese con el administrador.'
        ], 403);
    }

    // Validar contraseña (SHA512)
    $hashedInput = hash('sha512', $request->password);
    if (!hash_equals($hashedInput, $user->password)) {
        return response()->json([
            'success' => false,
            'message' => 'Contraseña incorrecta'
        ], 401);
    }

    // Cargar clave privada
    $privateKey = file_get_contents(env('JWT_PRIVATE_KEY_PATH'));

    // Generar Access Token
    $accessPayload = [
        'sub'   => $user->id,
        'email' => $user->email,
        'iat'   => time(),
        'exp'   => time() + env('JWT_EXPIRE_TIME', 3600),
        'type'  => 'access'
    ];
    $accessToken = JWT::encode($accessPayload, $privateKey, 'RS256');

    // Generar Refresh Token
    $refreshPayload = [
        'sub'   => $user->id,
        'email' => $user->email,
        'iat'   => time(),
        'exp'   => time() + env('JWT_REFRESH_EXPIRE_TIME', 604800),
        'type'  => 'refresh'
    ];
    $refreshToken = JWT::encode($refreshPayload, $privateKey, 'RS256');

    return response()->json([
        'success' => true,
        'message' => 'Inicio de sesión exitoso',
        'user' => [
            'id'    => $user->id,
            'name'  => trim($user->first_name . ' ' . $user->last_name),
            'email' => $user->email,
            'roles' => $user->roles->map(fn($r) => [
                'id'   => $r->id,
                'name' => $r->name
            ])->values()
        ],
        'token'  => $accessToken,
        'refresh_token' => $refreshToken
    ]);
}


    /**
     * Refrescar token
     */
    public function refresh(Request $request)
    {
        $authHeader = $request->header('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['error' => 'Token no proporcionado'], 401);
        }

        $token = substr($authHeader, 7);
        $publicKey = file_get_contents(env('JWT_PUBLIC_KEY_PATH'));

        try {
            $decoded = JWT::decode($token, new Key($publicKey, 'RS256'));

            if (($decoded->type ?? null) !== 'refresh') {
                return response()->json(['error' => 'El token no es de tipo refresh'], 403);
            }

            $privateKey = file_get_contents(env('JWT_PRIVATE_KEY_PATH'));

            $newPayload = [
                'sub'   => $decoded->sub,
                'email' => $decoded->email,
                'iat'   => time(),
                'exp'   => time() + env('JWT_EXPIRE_TIME', 3600),
                'type'  => 'access'
            ];

            $newAccessToken = JWT::encode($newPayload, $privateKey, 'RS256');

            return response()->json([
                'success' => true,
                'message' => 'Token renovado correctamente',
                'access_token' => $newAccessToken
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error'   => 'Token inválido o expirado',
                'details' => $e->getMessage()
            ], 401);
        }
    }

    /**
     * Retorna el usuario autenticado (rutas protegidas)
     */
    public function me(Request $request)
    {
        $jwtUser = $request->jwt_user;

        $user = User::with('roles')
            ->where('id', $jwtUser['id'])
            ->first(['id', 'first_name', 'last_name', 'email']);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'user' => [
                'id'    => $user->id,
                'name'  => trim($user->first_name . ' ' . $user->last_name),
                'email' => $user->email,
                'roles' => $user->roles->map(fn($r) => [
                    'id'   => $r->id,
                    'name' => $r->name
                ])->values()
            ]
        ]);
    }
}
