<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DevolucionCliente;
use App\Models\InventarioAlmacen;
use App\Models\InventarioMovimiento;
use App\Models\VentaDetalle;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DevolucionClienteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = DevolucionCliente::with(['venta', 'producto', 'productoCambio', 'usuario']);

        if ($request->filled('id_venta'))          $query->where('id_venta', $request->id_venta);
        if ($request->filled('tipo_compensacion')) $query->where('tipo_compensacion', $request->tipo_compensacion);
        if ($request->filled('fecha_desde'))       $query->whereDate('fecha_devolucion', '>=', $request->fecha_desde);
        if ($request->filled('fecha_hasta'))       $query->whereDate('fecha_devolucion', '<=', $request->fecha_hasta);

        $this->limitarAlmacenes($query, $request);

        return response()->json($query->orderByDesc('id_devolucion')->paginate($request->get('per_page', 20)));
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_venta'               => 'required|integer|exists:ventas_cabecera,id_venta',
            'id_venta_detalle'       => 'required|integer|exists:ventas_detalle,id_detalle',
            'id_almacen'             => 'required|integer|exists:almacenes,id_almacen',
            'cantidad_devuelta'      => 'required|numeric|min:0.01',
            'tipo_compensacion'      => 'required|in:cambio,dinero,combinado',
            'id_producto_cambio'     => 'required_if:tipo_compensacion,cambio,combinado|nullable|integer|exists:productos,id_producto',
            'cantidad_cambio'        => 'required_if:tipo_compensacion,cambio,combinado|nullable|numeric|min:0.01',
            'valor_producto_cambio'  => 'required_if:tipo_compensacion,cambio,combinado|nullable|numeric|min:0',
            'valor_dinero_entregado' => 'nullable|numeric|min:0',
            'fecha_devolucion'       => 'required|date',
            'observaciones'          => 'nullable|string|max:300',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $validator->errors()], 422);
        }

        // La venta y el detalle deben corresponder entre sí
        $detalleVenta = VentaDetalle::where('id_detalle', $request->id_venta_detalle)
            ->where('id_venta', $request->id_venta)
            ->first();

        if (!$detalleVenta) {
            return response()->json(['message' => 'Este producto no pertenece a la venta indicada.'], 422);
        }

        $disponibleParaDevolver = $detalleVenta->cantidad - $detalleVenta->cantidad_devuelta;
        if ($request->cantidad_devuelta > $disponibleParaDevolver) {
            return response()->json([
                'message' => "Solo quedan {$disponibleParaDevolver} unidades disponibles para devolver de este producto (ya se devolvieron {$detalleVenta->cantidad_devuelta} antes).",
            ], 422);
        }

        try {
            $devolucion = DB::transaction(function () use ($request, $detalleVenta) {
                // El valor se calcula con el precio REAL pagado (snapshot de la venta)
                $valorDevuelto = round($detalleVenta->precio_venta * $request->cantidad_devuelta, 2);

                // ─ 1. Ingresar el producto devuelto al inventario ─
                $this->moverInventario(
                    $request->id_almacen, $detalleVenta->id_producto, $request->cantidad_devuelta,
                    'entrada', 'DEVCLI-PEND', 'Ingreso por devolución de cliente', $request->user()->id_usuario
                );

                // ─ 2. Si hay cambio de producto, validar que haya stock del nuevo ─
                if (in_array($request->tipo_compensacion, ['cambio', 'combinado'])) {
                    $stockCambio = InventarioAlmacen::where('id_almacen', $request->id_almacen)
                        ->where('id_producto', $request->id_producto_cambio)
                        ->lockForUpdate()
                        ->first();

                    $disponible = $stockCambio?->stock_actual ?? 0;
                    if ($disponible < $request->cantidad_cambio) {
                        throw new \RuntimeException('Stock insuficiente del producto de cambio seleccionado.');
                    }
                }

                $devolucion = DevolucionCliente::create([
                    'id_venta'               => $request->id_venta,
                    'id_venta_detalle'       => $request->id_venta_detalle,
                    'id_producto'            => $detalleVenta->id_producto,
                    'id_almacen'             => $request->id_almacen,
                    'cantidad_devuelta'      => $request->cantidad_devuelta,
                    'valor_devuelto'         => $valorDevuelto,
                    'tipo_compensacion'      => $request->tipo_compensacion,
                    'id_producto_cambio'     => $request->id_producto_cambio,
                    'cantidad_cambio'        => $request->cantidad_cambio,
                    'valor_producto_cambio'  => $request->valor_producto_cambio,
                    'valor_dinero_entregado' => $request->valor_dinero_entregado ?? 0,
                    'id_usuario'             => $request->user()->id_usuario,
                    'fecha_devolucion'       => $request->fecha_devolucion,
                    'observaciones'          => $request->observaciones,
                ]);

                // Ahora que existe id_devolucion, la referencia del movimiento queda correcta
                InventarioMovimiento::where('referencia', 'DEVCLI-PEND')
                    ->where('id_almacen', $request->id_almacen)
                    ->where('id_producto', $detalleVenta->id_producto)
                    ->latest('id_movimiento')
                    ->first()
                    ?->update(['referencia' => 'DEVCLI-' . $devolucion->id_devolucion]);

                if (in_array($request->tipo_compensacion, ['cambio', 'combinado'])) {
                    $this->moverInventario(
                        $request->id_almacen, $request->id_producto_cambio, $request->cantidad_cambio,
                        'salida', 'DEVCLI-' . $devolucion->id_devolucion, 'Entrega por cambio en devolución de cliente', $request->user()->id_usuario
                    );
                }

                $detalleVenta->update([
                    'cantidad_devuelta' => $detalleVenta->cantidad_devuelta + $request->cantidad_devuelta,
                ]);

                return $devolucion;
            });

            return response()->json($devolucion->load(['venta', 'producto', 'productoCambio']), 201);

        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    private function moverInventario(int $idAlmacen, int $idProducto, float $cantidad, string $tipo, string $referencia, string $nota, int $idUsuario): void
    {
        $stock = InventarioAlmacen::firstOrCreate(
            ['id_almacen' => $idAlmacen, 'id_producto' => $idProducto],
            ['stock_actual' => 0, 'stock_minimo' => 0]
        );
        $stock = InventarioAlmacen::where('id', $stock->id)->lockForUpdate()->first();

        $anterior = $stock->stock_actual;
        $nuevo = $tipo === 'entrada' ? $anterior + $cantidad : $anterior - $cantidad;

        if ($tipo === 'salida' && $nuevo < 0) {
            throw new \RuntimeException('Stock insuficiente para completar el movimiento.');
        }

        $stock->update(['stock_actual' => $nuevo]);

        InventarioMovimiento::create([
            'id_almacen'     => $idAlmacen,
            'id_producto'    => $idProducto,
            'tipo'           => $tipo,
            'cantidad'       => $cantidad,
            'stock_anterior' => $anterior,
            'stock_nuevo'    => $nuevo,
            'referencia'     => $referencia,
            'nota'           => $nota,
            'id_usuario'     => $idUsuario,
        ]);
    }
}