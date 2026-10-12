<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    protected function autorizarAlmacen(Request $request, int $idAlmacen): void
    {
        abort_unless($request->user()->puedeOperarEn($idAlmacen), 403, 'No tienes acceso a este almacén.');
    }

    protected function limitarAlmacenes($query, Request $request, string $columna = 'id_almacen')
    {
        $ids = $request->user()->almacenesPermitidos();
        if ($ids !== null) {
            $query->whereIn($columna, $ids);
        }
        return $query;
    }
}