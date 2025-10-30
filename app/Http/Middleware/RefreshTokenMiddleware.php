<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;

class RefreshJwtMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['error' => 'Refresh token no proporcionado'], 401);
        }

        try {
            $publicKey = file_get_contents(env('JWT_PUBLIC_KEY_PATH'));
            $decoded = JWT::decode($token, new Key($publicKey, 'RS256'));

            $request->merge(['jwt_user' => [
                'id' => $decoded->sub,
                'email' => $decoded->email
            ]]);

        } catch (Exception $e) {
            return response()->json(['error' => 'Refresh token inválido o expirado'], 401);
        }

        return $next($request);
    }
}
