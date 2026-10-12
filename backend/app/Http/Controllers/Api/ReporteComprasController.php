<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ReporteComprasController extends Controller
{
    private function baseQuery(Request $request)
    {
        $query = DB::table('detalle_factura as df')
            ->join('facturas as f', 'f.id_factura', '=', 'df.id_factura')
            ->where('f.estado', 'procesada');

        if ($request->filled('fecha_desde')) $query->whereDate('f.fecha_factura', '>=', $request->fecha_desde);
        if ($request->filled('fecha_hasta')) $query->whereDate('f.fecha_factura', '<=', $request->fecha_hasta);
        if ($request->filled('id_almacen'))  $query->where('f.id_almacen', $request->id_almacen);

        $this->limitarAlmacenes($query, $request, 'f.id_almacen');

        return $query;
    }

    public function general(Request $request): JsonResponse
    {
        $resumen = $this->baseQuery($request)
            ->selectRaw('COUNT(DISTINCT f.id_factura) as total_facturas, SUM(df.cantidad_facturada) as total_unidades, SUM(df.total_linea) as total_valor')
            ->first();

        return response()->json($resumen);
    }

    public function porProveedor(Request $request): JsonResponse
    {
        $datos = $this->baseQuery($request)
            ->join('proveedores as pr', 'pr.id_proveedor', '=', 'f.id_proveedor')
            ->selectRaw('pr.id_proveedor, pr.nombre_proveedor, COUNT(DISTINCT f.id_factura) as total_facturas, SUM(df.total_linea) as total_valor')
            ->groupBy('pr.id_proveedor', 'pr.nombre_proveedor')
            ->orderByDesc('total_valor')
            ->get();

        return response()->json($datos);
    }

    public function porTipoCompra(Request $request): JsonResponse
    {
        $datos = $this->baseQuery($request)
            ->selectRaw('f.tipo_compra, COUNT(DISTINCT f.id_factura) as total_facturas, SUM(df.total_linea) as total_valor')
            ->groupBy('f.tipo_compra')
            ->get();

        return response()->json($datos);
    }
}