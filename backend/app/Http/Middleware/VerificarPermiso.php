<?php

namespace App\Http\Middleware;

use App\Models\Usuario;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VerificarPermiso
{
    public function handle(Request $request, Closure $next, string $modulo, string $accion = 'ver')
    {
        /** @var Usuario|null $usuario */
        $usuario = Auth::guard('api')->user();

        if (!$usuario) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        if (!$usuario->tienePermiso($modulo, $accion)) {
            return response()->json([
                'message' => "No autorizado. Se requiere permiso de '{$accion}' en el módulo '{$modulo}'.",
            ], 403);
        }

        return $next($request);
    }
}