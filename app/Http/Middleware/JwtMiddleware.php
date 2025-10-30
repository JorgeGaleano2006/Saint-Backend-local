<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;

class JwtMiddleware
{
    /**
     * Middleware para validar Access o Refresh Token según alias.
     *
     * Si el alias es 'jwt.access' → solo permite tokens normales.
     * Si el alias es 'jwt.refresh' → solo permite tokens de tipo refresh.
     */
    public function handle(Request $request, Closure $next, $tokenType = 'access')
    {
        $authHeader = $request->header('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['error' => 'Token no proporcionado'], 401);
        }

        $token = substr($authHeader, 7);

        try {
            $publicKey = file_get_contents(env('JWT_PUBLIC_KEY_PATH'));
            $decoded = JWT::decode($token, new Key($publicKey, 'RS256'));

            // Validar tipo de token (access vs refresh)
            if ($tokenType === 'access' && (isset($decoded->type) && $decoded->type === 'refresh')) {
                return response()->json(['error' => 'Token de refresco no válido para esta ruta'], 403);
            }

            if ($tokenType === 'refresh' && (!isset($decoded->type) || $decoded->type !== 'refresh')) {
                return response()->json(['error' => 'Se requiere un token de refresco válido'], 403);
            }

            // Añadir usuario al request
            $request->merge(['jwt_user' => [
                'id'    => $decoded->sub,
                'email' => $decoded->email
            ]]);

        } catch (Exception $e) {
            return response()->json([
                'error' => 'Token inválido o expirado',
                'details' => $e->getMessage()
            ], 401);
        }

        return $next($request);
    }
}
