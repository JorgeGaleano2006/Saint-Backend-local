<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SanitizeInput
{
    /**
     * Lista de campos que no deben ser sanitizados (por ejemplo, campos que contienen HTML intencional).
     *
     * @var array
     */
    protected $except = [];

    /**
     * Sanitiza un valor o array de valores.
     *
     * @param mixed $value
     * @return mixed
     */
    protected function sanitize($value)
    {
        if (is_array($value)) {
            $clean = [];
            foreach ($value as $k => $v) {
                // No sanitizar claves que están en la lista de excepciones
                if (in_array($k, $this->except)) {
                    $clean[$k] = $v;
                } else {
                    $clean[$k] = $this->sanitize($v);
                }
            }
            return $clean;
        }

        if (is_string($value)) {
            // Eliminar espacios al inicio y final
            $value = trim($value);

            // Eliminar caracteres de control no deseados
            $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value);

            // Eliminar etiquetas HTML para evitar XSS
            $value = strip_tags($value);

            // Convertir caracteres especiales a entidades HTML para evitar XSS
            $value = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            // Sanitizar correos electrónicos
            if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $value = filter_var($value, FILTER_SANITIZE_EMAIL);
            }

            // Sanitizar URLs (opcional, si esperas URLs en los inputs)
            if (filter_var($value, FILTER_VALIDATE_URL)) {
                $value = filter_var($value, FILTER_SANITIZE_URL);
            }
        }

        return $value;
    }

    /**
     * Manejar la solicitud entrante.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Sanitizar parámetros de la query string (GET)
        $query = $request->query();
        if (!empty($query)) {
            $request->query->replace($this->sanitize($query));
        }

        // Sanitizar todos los inputs (POST, PUT, etc.)
        $input = $request->all();
        if (!empty($input)) {
            $request->replace($this->sanitize($input));
        }

        return $next($request);
    }
}
