<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ReporteTrasladosController extends Controller
{
    public function general(Request $request): JsonResponse
    {
        $query = DB::table('detalle_traslado as dt')
            ->join('traslados as t', 't.id_traslado', '=', 'dt.id_traslado')
            ->join('almacenes as ao', 'ao.id_almacen', '=', 't.id_almacen_origen')
            ->join('almacenes as ad', 'ad.id_almacen', '=', 't.id_almacen_destino');

        if ($request->filled('fecha_desde')) $query->whereDate('t.fecha_traslado', '>=', $request->fecha_desde);
        if ($request->filled('fecha_hasta')) $query->whereDate('t.fecha_traslado', '<=', $request->fecha_hasta);

        // Un usuario ve los traslados que salen de o llegan a sus almacenes
        $ids = $request->user()->almacenesPermitidos();
        if ($ids !== null) {
            $query->where(fn ($q) => $q->whereIn('t.id_almacen_origen', $ids)->orWhereIn('t.id_almacen_destino', $ids));
        }

        $datos = $query
            ->selectRaw('ao.nombre as origen, ad.nombre as destino, COUNT(DISTINCT t.id_traslado) as total_traslados, SUM(dt.cantidad) as total_unidades')
            ->groupBy('ao.nombre', 'ad.nombre')
            ->orderByDesc('total_unidades')
            ->get();

        return response()->json($datos);
    }
}