<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ReporteDevolucionesController extends Controller
{
    public function proveedor(Request $request): JsonResponse
    {
        $query = DB::table('devoluciones_proveedor as dp')
            ->join('motivos_devolucion as m', 'm.id_motivo', '=', 'dp.id_motivo')
            ->join('proveedores as pr', 'pr.id_proveedor', '=', 'dp.id_proveedor');

        if ($request->filled('fecha_desde')) $query->whereDate('dp.fecha_devolucion', '>=', $request->fecha_desde);
        if ($request->filled('fecha_hasta')) $query->whereDate('dp.fecha_devolucion', '<=', $request->fecha_hasta);
        if ($request->filled('id_almacen'))  $query->where('dp.id_almacen', $request->id_almacen);

        $this->limitarAlmacenes($query, $request, 'dp.id_almacen');

        $datos = $query
            ->selectRaw('pr.nombre_proveedor, m.nombre as motivo, COUNT(*) as total_devoluciones, SUM(dp.cantidad) as total_unidades')
            ->groupBy('pr.nombre_proveedor', 'm.nombre')
            ->orderByDesc('total_unidades')
            ->get();

        return response()->json($datos);
    }

    public function cliente(Request $request): JsonResponse
    {
        $query = DB::table('devoluciones_cliente as dc');

        if ($request->filled('fecha_desde')) $query->whereDate('dc.fecha_devolucion', '>=', $request->fecha_desde);
        if ($request->filled('fecha_hasta')) $query->whereDate('dc.fecha_devolucion', '<=', $request->fecha_hasta);
        if ($request->filled('id_almacen'))  $query->where('dc.id_almacen', $request->id_almacen);

        $this->limitarAlmacenes($query, $request, 'dc.id_almacen');

        $datos = $query
            ->selectRaw('tipo_compensacion, COUNT(*) as total_devoluciones, SUM(cantidad_devuelta) as total_unidades, SUM(valor_devuelto) as total_valor')
            ->groupBy('tipo_compensacion')
            ->get();

        return response()->json($datos);
    }
}