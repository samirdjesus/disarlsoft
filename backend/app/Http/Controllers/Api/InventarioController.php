<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventarioAlmacen;
use App\Models\InventarioMovimiento;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class InventarioController extends Controller
{
    // GET /inventario/stock/{id_almacen}
    public function stockAlmacen(Request $request, int $idAlmacen): JsonResponse
    {
        $query = InventarioAlmacen::with(['producto.marca', 'producto.linea'])
            ->where('id_almacen', $idAlmacen);

        if ($request->filled('nombre')) {
            $query->whereHas('producto', fn ($q) => $q->where('nombre_producto', 'ilike', '%' . $request->nombre . '%'));
        }

        return response()->json(
            $query->orderBy('id_producto')->paginate($request->get('per_page', 20))
        );
    }

    // GET /inventario/alertas  (productos bajo el mínimo, todos los almacenes o uno)
    public function alertas(Request $request): JsonResponse
    {
        $query = InventarioAlmacen::with(['producto', 'almacen'])
            ->whereColumn('stock_actual', '<=', 'stock_minimo');

        if ($request->filled('id_almacen')) {
            $query->where('id_almacen', $request->id_almacen);
        }

        return response()->json($query->get());
    }

    // GET /inventario/movimientos
    public function movimientos(Request $request): JsonResponse
    {
        $query = InventarioMovimiento::with(['producto', 'almacen', 'usuario']);

        if ($request->filled('id_almacen'))  $query->where('id_almacen', $request->id_almacen);
        if ($request->filled('id_producto')) $query->where('id_producto', $request->id_producto);
        if ($request->filled('tipo'))        $query->where('tipo', $request->tipo);
        if ($request->filled('fecha_desde')) $query->whereDate('created_at', '>=', $request->fecha_desde);
        if ($request->filled('fecha_hasta')) $query->whereDate('created_at', '<=', $request->fecha_hasta);

        return response()->json(
            $query->orderByDesc('created_at')->paginate($request->get('per_page', 30))
        );
    }

    // POST /inventario/stock-minimo
    public function actualizarStockMinimo(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_almacen'   => 'required|integer|exists:almacenes,id_almacen',
            'id_producto'  => 'required|integer|exists:productos,id_producto',
            'stock_minimo' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $validator->errors()], 422);
        }

        $registro = InventarioAlmacen::firstOrCreate(
            ['id_almacen' => $request->id_almacen, 'id_producto' => $request->id_producto],
            ['stock_actual' => 0]
        );
        $registro->update(['stock_minimo' => $request->stock_minimo]);

        return response()->json($registro);
    }

    // POST /inventario/entrada  (ingreso manual de stock, fuera de Compras)
  public function entrada(Request $request): JsonResponse
{
    try {
        return $this->moverStock($request, 'entrada');
    } catch (\RuntimeException $e) {
        return response()->json(['message' => $e->getMessage()], 422);
    }
}

public function salida(Request $request): JsonResponse
{
    try {
        return $this->moverStock($request, 'salida');
    } catch (\RuntimeException $e) {
        return response()->json(['message' => $e->getMessage()], 422);
    }
}
    private function moverStock(Request $request, string $tipo): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_almacen'  => 'required|integer|exists:almacenes,id_almacen',
            'id_producto' => 'required|integer|exists:productos,id_producto',
            'cantidad'    => 'required|numeric|min:0.01',
            'nota'        => 'nullable|string|max:200',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $validator->errors()], 422);
        }

        $resultado = DB::transaction(function () use ($request, $tipo) {
            $stock = InventarioAlmacen::firstOrCreate(
                ['id_almacen' => $request->id_almacen, 'id_producto' => $request->id_producto],
                ['stock_actual' => 0, 'stock_minimo' => 0]
            );

            // lockForUpdate sobre el registro YA existente (firstOrCreate no
            // bloquea), para que dos movimientos simultáneos del mismo
            // producto/almacén no se pisen entre sí.
            $stock = InventarioAlmacen::where('id', $stock->id)->lockForUpdate()->first();

            $anterior = $stock->stock_actual;
            $nuevo = $tipo === 'entrada'
                ? $anterior + $request->cantidad
                : $anterior - $request->cantidad;

            if ($tipo === 'salida' && $nuevo < 0) {
                throw new \RuntimeException('Stock insuficiente para realizar la salida.');
            }

            $stock->update(['stock_actual' => $nuevo]);

            $movimiento = InventarioMovimiento::create([
                'id_almacen'     => $request->id_almacen,
                'id_producto'    => $request->id_producto,
                'tipo'           => $tipo,
                'cantidad'       => $request->cantidad,
                'stock_anterior' => $anterior,
                'stock_nuevo'    => $nuevo,
                'nota'           => $request->nota,
                'id_usuario'     => $request->user()->id_usuario,
            ]);

            return ['stock' => $stock, 'movimiento' => $movimiento];
        });

        return response()->json($resultado, 201);
    }

    // POST /inventario/ajuste  (corrección puntual: fija el stock a un valor exacto)
     public function ajuste(Request $request): JsonResponse
    {
    $validator = Validator::make($request->all(), [
        'id_almacen'  => 'required|integer|exists:almacenes,id_almacen',
        'id_producto' => 'required|integer|exists:productos,id_producto',
        'stock_nuevo' => 'required|numeric|min:0',
        'nota'        => 'required|string|max:200',
    ]);

    if ($validator->fails()) {
        return response()->json(['message' => 'Datos inválidos', 'errors' => $validator->errors()], 422);
    }

    try {
        $resultado = DB::transaction(function () use ($request) {
            $stock = InventarioAlmacen::firstOrCreate(
                ['id_almacen' => $request->id_almacen, 'id_producto' => $request->id_producto],
                ['stock_actual' => 0, 'stock_minimo' => 0]
            );
            $stock = InventarioAlmacen::where('id', $stock->id)->lockForUpdate()->first();

            $anterior = $stock->stock_actual;
            $nuevo = $request->stock_nuevo;
            $diferencia = $nuevo - $anterior;

            if ($diferencia == 0) {
                throw new \RuntimeException('El stock nuevo es igual al actual, no hay nada que ajustar.');
            }

            $stock->update(['stock_actual' => $nuevo]);

            $movimiento = InventarioMovimiento::create([
                'id_almacen'     => $request->id_almacen,
                'id_producto'    => $request->id_producto,
                'tipo'           => $diferencia > 0 ? 'ajuste_positivo' : 'ajuste_negativo',
                'cantidad'       => abs($diferencia),
                'stock_anterior' => $anterior,
                'stock_nuevo'    => $nuevo,
                'nota'           => $request->nota,
                'id_usuario'     => $request->user()->id_usuario,
            ]);

            return ['stock' => $stock, 'movimiento' => $movimiento];
        });

        return response()->json($resultado, 201);

    } catch (\RuntimeException $e) {
        return response()->json(['message' => $e->getMessage()], 422);
    }
    }
}