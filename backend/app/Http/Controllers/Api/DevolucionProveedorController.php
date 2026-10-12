<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DevolucionProveedor;
use App\Models\InventarioAlmacen;
use App\Models\InventarioMovimiento;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DevolucionProveedorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = DevolucionProveedor::with(['proveedor', 'almacen', 'producto', 'motivo', 'usuario']);

        if ($request->filled('id_proveedor')) $query->where('id_proveedor', $request->id_proveedor);
        if ($request->filled('id_motivo'))    $query->where('id_motivo', $request->id_motivo);
        if ($request->filled('fecha_desde'))  $query->whereDate('fecha_devolucion', '>=', $request->fecha_desde);
        if ($request->filled('fecha_hasta'))  $query->whereDate('fecha_devolucion', '<=', $request->fecha_hasta);

        $this->limitarAlmacenes($query, $request);

        return response()->json($query->orderByDesc('id_devolucion')->paginate($request->get('per_page', 20)));
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_proveedor'     => 'required|integer|exists:proveedores,id_proveedor',
            'id_almacen'       => 'required|integer|exists:almacenes,id_almacen',
            'id_producto'      => 'required|integer|exists:productos,id_producto',
            'id_motivo'        => 'required|integer|exists:motivos_devolucion,id_motivo',
            'cantidad'         => 'required|numeric|min:0.01',
            'fecha_devolucion' => 'required|date',
            'observaciones'    => 'nullable|string|max:300',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $validator->errors()], 422);
        }

        try {
            $devolucion = DB::transaction(function () use ($request) {
                $stock = InventarioAlmacen::firstOrCreate(
                    ['id_almacen' => $request->id_almacen, 'id_producto' => $request->id_producto],
                    ['stock_actual' => 0, 'stock_minimo' => 0]
                );
                $stock = InventarioAlmacen::where('id', $stock->id)->lockForUpdate()->first();

                $anterior = $stock->stock_actual;
                $nuevo = $anterior - $request->cantidad;

                if ($nuevo < 0) {
                    throw new \RuntimeException('Stock insuficiente para devolver esta cantidad al proveedor.');
                }

                $stock->update(['stock_actual' => $nuevo]);

                $devolucion = DevolucionProveedor::create([
                    ...$request->only(['id_proveedor', 'id_almacen', 'id_producto', 'id_motivo', 'cantidad', 'fecha_devolucion', 'observaciones']),
                    'id_usuario' => $request->user()->id_usuario,
                ]);

                InventarioMovimiento::create([
                    'id_almacen'     => $request->id_almacen,
                    'id_producto'    => $request->id_producto,
                    'tipo'           => 'salida',
                    'cantidad'       => $request->cantidad,
                    'stock_anterior' => $anterior,
                    'stock_nuevo'    => $nuevo,
                    'referencia'     => 'DEVPROV-' . $devolucion->id_devolucion,
                    'nota'           => 'Devolución a proveedor',
                    'id_usuario'     => $request->user()->id_usuario,
                ]);

                return $devolucion;
            });

            return response()->json($devolucion->load(['proveedor', 'producto', 'motivo']), 201);

        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}