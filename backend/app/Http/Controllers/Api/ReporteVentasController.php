<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ReporteVentasController extends Controller
{
    private function baseQuery(Request $request)
    {
        $query = DB::table('ventas_detalle as vd')
            ->join('ventas_cabecera as vc', 'vc.id_venta', '=', 'vd.id_venta')
            ->where('vc.estado', 'pagada');

        if ($request->filled('fecha_desde')) $query->whereDate('vc.fecha_venta', '>=', $request->fecha_desde);
        if ($request->filled('fecha_hasta')) $query->whereDate('vc.fecha_venta', '<=', $request->fecha_hasta);
        if ($request->filled('id_almacen'))  $query->where('vc.id_almacen', $request->id_almacen);

        // Un usuario solo ve informes de sus almacenes
        $this->limitarAlmacenes($query, $request, 'vc.id_almacen');

        return $query;
    }

    public function general(Request $request): JsonResponse
    {
        $resumen = $this->baseQuery($request)
            ->selectRaw('COUNT(DISTINCT vc.id_venta) as total_documentos, SUM(vd.cantidad) as total_unidades, SUM(vd.total_linea) as total_valor')
            ->first();

        return response()->json($resumen);
    }

    public function porProveedor(Request $request): JsonResponse
    {
        $datos = $this->baseQuery($request)
            ->join('productos as p', 'p.id_producto', '=', 'vd.id_producto')
            ->join('proveedores as pr', 'pr.id_proveedor', '=', 'p.id_proveedor')
            ->selectRaw('pr.id_proveedor, pr.nombre_proveedor, SUM(vd.cantidad) as total_unidades, SUM(vd.total_linea) as total_valor')
            ->groupBy('pr.id_proveedor', 'pr.nombre_proveedor')
            ->orderByDesc('total_valor')
            ->get();

        return response()->json($datos);
    }

    public function porMarca(Request $request): JsonResponse
    {
        $datos = $this->baseQuery($request)
            ->join('productos as p', 'p.id_producto', '=', 'vd.id_producto')
            ->leftJoin('marcas as m', 'm.id_marcas', '=', 'p.id_marca')
            ->selectRaw('COALESCE(m.id_marcas, 0) as id_marca, COALESCE(m.nombre_marca, \'Sin marca\') as nombre_marca, SUM(vd.cantidad) as total_unidades, SUM(vd.total_linea) as total_valor')
            ->groupBy('m.id_marcas', 'm.nombre_marca')
            ->orderByDesc('total_valor')
            ->get();

        return response()->json($datos);
    }

    public function porLinea(Request $request): JsonResponse
    {
        $datos = $this->baseQuery($request)
            ->join('productos as p', 'p.id_producto', '=', 'vd.id_producto')
            ->leftJoin('linea as l', 'l.id_linea', '=', 'p.id_linea')
            ->selectRaw('COALESCE(l.id_linea, 0) as id_linea, COALESCE(l.nombre_linea, \'Sin línea\') as nombre_linea, SUM(vd.cantidad) as total_unidades, SUM(vd.total_linea) as total_valor')
            ->groupBy('l.id_linea', 'l.nombre_linea')
            ->orderByDesc('total_valor')
            ->get();

        return response()->json($datos);
    }

    public function porCodigo(Request $request): JsonResponse
    {
        $datos = $this->baseQuery($request)
            ->join('productos as p', 'p.id_producto', '=', 'vd.id_producto')
            ->selectRaw('p.cod_producto, p.nombre_producto, SUM(vd.cantidad) as total_unidades, SUM(vd.total_linea) as total_valor')
            ->groupBy('p.cod_producto', 'p.nombre_producto')
            ->orderByDesc('total_valor')
            ->get();

        return response()->json($datos);
    }

    public function porVendedor(Request $request): JsonResponse
    {
        $datos = $this->baseQuery($request)
            ->join('usuario as u', 'u.id_usuario', '=', 'vc.id_vendedor')
            ->selectRaw('u.id_usuario, u.nombre, SUM(vd.cantidad) as total_unidades, SUM(vd.total_linea) as total_valor')
            ->groupBy('u.id_usuario', 'u.nombre')
            ->orderByDesc('total_valor')
            ->get();

        return response()->json($datos);
    }

    // Selecciona un código → ventas de ese código desglosadas por vendedor.
    // Sin cod_producto devuelve todas las combinaciones vendedor × código.
    public function porVendedorCodigo(Request $request): JsonResponse
    {
        $query = $this->baseQuery($request)
            ->join('usuario as u', 'u.id_usuario', '=', 'vc.id_vendedor')
            ->join('productos as p', 'p.id_producto', '=', 'vd.id_producto');

        if ($request->filled('cod_producto')) {
            $query->where('p.cod_producto', $request->cod_producto);
        }

        $datos = $query
            ->selectRaw('u.id_usuario, u.nombre as vendedor, p.cod_producto, p.nombre_producto,
                         SUM(vd.cantidad) as total_unidades,
                         SUM(vd.cantidad_devuelta) as unidades_devueltas,
                         SUM(vd.total_linea) as total_valor')
            ->groupBy('u.id_usuario', 'u.nombre', 'p.cod_producto', 'p.nombre_producto')
            ->orderBy('p.cod_producto')
            ->orderByDesc('total_valor')
            ->get();

        return response()->json($datos);
    }
}