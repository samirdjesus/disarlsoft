<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerificarAlmacen
{
    public function handle(Request $request, Closure $next)
    {
        $usuario = $request->user();

        if ($usuario && !$usuario->esAdmin()) {
            $candidatos = [
                $request->route('id_almacen'),
                $request->input('id_almacen'),
                $request->input('id_almacen_origen'), // en traslados, el emisor debe pertenecer al origen
            ];

            foreach ($candidatos as $valor) {
                if ($valor !== null && $valor !== '' && !$usuario->puedeOperarEn((int) $valor)) {
                    return response()->json(['message' => 'No tienes acceso a este almacén.'], 403);
                }
            }
        }

        return $next($request);
    }
}