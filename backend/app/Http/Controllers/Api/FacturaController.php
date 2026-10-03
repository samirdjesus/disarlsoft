<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Factura;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\Novedad;
use App\Models\InventarioAlmacen;
use App\Models\InventarioMovimiento;

class FacturaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Factura::with(['proveedor', 'almacen', 'orden']);

        if ($request->filled('id_almacen'))   $query->where('id_almacen', $request->id_almacen);
        if ($request->filled('id_proveedor')) $query->where('id_proveedor', $request->id_proveedor);
        if ($request->filled('estado'))       $query->where('estado', $request->estado);
        if ($request->filled('tipo_compra'))  $query->where('tipo_compra', $request->tipo_compra);

        return response()->json($query->orderByDesc('id_factura')->paginate($request->get('per_page', 20)));
    }

    public function show(int $id): JsonResponse
    {
        $factura = Factura::with(['detalles.producto', 'proveedor', 'almacen', 'orden.detalles'])->find($id);
        if (!$factura) {
            return response()->json(['message' => 'Factura no encontrada'], 404);
        }
        return response()->json($factura);
    }

    // POST /facturas — registra la factura, CON o SIN orden de compra.
    // El frontend envía id_orden solo si el usuario la seleccionó;
    // si no, tipo_compra se infiere automáticamente como 'directa'.
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'numero_factura'               => 'required|string|max:60',
            'id_orden'                     => 'nullable|integer|exists:ordenes_compra,id_orden',
            'id_proveedor'                 => 'required|integer|exists:proveedores,id_proveedor',
            'id_almacen'                   => 'required|integer|exists:almacenes,id_almacen',
            'fecha_factura'                => 'required|date',
            'observaciones'                => 'nullable|string|max:300',
            'detalles'                     => 'required|array|min:1',
            'detalles.*.id_producto'       => 'required|integer|exists:productos,id_producto',
            'detalles.*.precio_compra'     => 'required|numeric|min:0',
            'detalles.*.cantidad_facturada'=> 'required|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $validator->errors()], 422);
        }

        // Evitar cargar la misma factura física dos veces (aunque la BD
        // también lo rechazaría por el unique, esto da un mensaje más claro)
        $yaExiste = Factura::where('id_proveedor', $request->id_proveedor)
            ->where('numero_factura', $request->numero_factura)
            ->exists();

        if ($yaExiste) {
            return response()->json(['message' => 'Ya existe una factura con ese número para este proveedor.'], 422);
        }

        $factura = DB::transaction(function () use ($request) {
            $subtotal = 0;
            $lineas = [];

            foreach ($request->detalles as $d) {
                $producto = Producto::find($d['id_producto']);
                $totalLinea = round($d['precio_compra'] * $d['cantidad_facturada'], 2);
                $subtotal += $totalLinea;

                $lineas[] = [
                    'id_producto'         => $d['id_producto'],
                    'nombre_producto'     => $producto->nombre_producto,
                    'precio_compra'       => $d['precio_compra'],
                    'cantidad_facturada'  => $d['cantidad_facturada'],
                    'total_linea'         => $totalLinea,
                    'ingresado'           => false,
                ];
            }

            $factura = Factura::create([
                'numero_factura' => $request->numero_factura,
                'id_orden'       => $request->id_orden,
                'id_proveedor'   => $request->id_proveedor,
                'id_almacen'     => $request->id_almacen,
                'id_usuario'     => $request->user()->id_usuario,
                'fecha_factura'  => $request->fecha_factura,
                'tipo_compra'    => $request->filled('id_orden') ? 'con_orden' : 'directa',
                'subtotal'       => round($subtotal, 2),
                'total'          => round($subtotal, 2),
                'estado'         => 'registrada',
                'observaciones'  => $request->observaciones,
            ]);

            $factura->detalles()->createMany($lineas);

            return $factura;
        });

        return response()->json($factura->load(['detalles', 'proveedor', 'almacen', 'orden']), 201);
    }

    // PUT /facturas/{id} — editar datos generales (no detalles, eso es aparte)
    public function update(Request $request, int $id): JsonResponse
    {
        $factura = Factura::find($id);
        if (!$factura) {
            return response()->json(['message' => 'Factura no encontrada'], 404);
        }
        if ($factura->estado === 'procesada') {
            return response()->json(['message' => 'No se puede editar una factura ya procesada en inventario.'], 422);
        }

        $validator = Validator::make($request->all(), [
            'numero_factura' => 'sometimes|string|max:60',
            'fecha_factura'  => 'sometimes|date',
            'observaciones'  => 'nullable|string|max:300',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $validator->errors()], 422);
        }

        $factura->update($validator->validated());
        return response()->json($factura->fresh(['detalles', 'proveedor', 'almacen']));
    }

    // DELETE /facturas/{id} — eliminación TOTAL de la factura
    public function destroy(int $id): JsonResponse
    {
        $factura = Factura::find($id);
        if (!$factura) {
            return response()->json(['message' => 'Factura no encontrada'], 404);
        }
        if ($factura->estado === 'procesada') {
            return response()->json(['message' => 'No se puede eliminar una factura ya procesada en inventario. Debe anularse por Novedades.'], 422);
        }

        $factura->detalles()->delete();
        $factura->delete();
        return response()->json(['message' => 'Factura eliminada correctamente']);
    }

    // PUT /facturas/{id}/detalles/{idDetalle} — editar UN producto de la factura
    public function actualizarDetalle(Request $request, int $id, int $idDetalle): JsonResponse
    {
        $factura = Factura::find($id);
        if (!$factura || $factura->estado === 'procesada') {
            return response()->json(['message' => 'Factura no encontrada o ya procesada.'], 422);
        }

        $detalle = $factura->detalles()->find($idDetalle);
        if (!$detalle) {
            return response()->json(['message' => 'Producto no encontrado en esta factura.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'precio_compra'      => 'sometimes|numeric|min:0',
            'cantidad_facturada' => 'sometimes|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $validator->errors()], 422);
        }

        $precio = $request->precio_compra ?? $detalle->precio_compra;
        $cantidad = $request->cantidad_facturada ?? $detalle->cantidad_facturada;

        $detalle->update([
            'precio_compra'      => $precio,
            'cantidad_facturada' => $cantidad,
            'total_linea'        => round($precio * $cantidad, 2),
        ]);

        $this->recalcularTotales($factura);

        return response()->json($detalle);
    }

    // DELETE /facturas/{id}/detalles/{idDetalle} — eliminar UN producto de la factura
    public function eliminarDetalle(int $id, int $idDetalle): JsonResponse
    {
        $factura = Factura::find($id);
        if (!$factura || $factura->estado === 'procesada') {
            return response()->json(['message' => 'Factura no encontrada o ya procesada.'], 422);
        }

        $detalle = $factura->detalles()->find($idDetalle);
        if (!$detalle) {
            return response()->json(['message' => 'Producto no encontrado en esta factura.'], 404);
        }

        if ($factura->detalles()->count() <= 1) {
            return response()->json(['message' => 'No se puede eliminar el único producto de la factura; elimina la factura completa en su lugar.'], 422);
        }

        $detalle->delete();
        $this->recalcularTotales($factura);

        return response()->json(['message' => 'Producto eliminado de la factura']);
    }

    // POST /facturas/{id}/detalles — agregar un producto nuevo (ej. vía
    // lector de código de barras: escanea → busca por cod_producto en el
    // frontend con GET productos/codigo/{cod} → envía aquí el id_producto)
    public function agregarDetalle(Request $request, int $id): JsonResponse
    {
        $factura = Factura::find($id);
        if (!$factura || $factura->estado === 'procesada') {
            return response()->json(['message' => 'Factura no encontrada o ya procesada.'], 422);
        }

        $validator = Validator::make($request->all(), [
            'id_producto'         => 'required|integer|exists:productos,id_producto',
            'precio_compra'       => 'required|numeric|min:0',
            'cantidad_facturada'  => 'required|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $validator->errors()], 422);
        }

        $producto = Producto::find($request->id_producto);
        $totalLinea = round($request->precio_compra * $request->cantidad_facturada, 2);

        $detalle = $factura->detalles()->create([
            'id_producto'         => $request->id_producto,
            'nombre_producto'     => $producto->nombre_producto,
            'precio_compra'       => $request->precio_compra,
            'cantidad_facturada'  => $request->cantidad_facturada,
            'total_linea'         => $totalLinea,
            'ingresado'           => false,
        ]);

        $this->recalcularTotales($factura);

        return response()->json($detalle, 201);
    }

    private function recalcularTotales(Factura $factura): void
    {
        $subtotal = $factura->detalles()->sum('total_linea');
        $factura->update(['subtotal' => $subtotal, 'total' => $subtotal]);
    }

    // GET /facturas/{id}/comparativo — solo tiene sentido si tipo_compra = con_orden
public function comparativo(int $id): JsonResponse
{
    $factura = Factura::with('detalles.producto')->find($id);
    if (!$factura) {
        return response()->json(['message' => 'Factura no encontrada'], 404);
    }
    if ($factura->tipo_compra !== 'con_orden') {
        return response()->json(['message' => 'Esta factura es de compra directa, no tiene orden para comparar.'], 422);
    }

    $orden = $factura->orden()->with('detalles')->first();
    $pedidoPorProducto = $orden->detalles->keyBy('id_producto');
    $facturadoPorProducto = $factura->detalles->keyBy('id_producto');

    $coincidentes = [];
    $sobrantes = [];
    $faltantes = [];

    foreach ($facturadoPorProducto as $idProducto => $detFactura) {
        $pedida = $pedidoPorProducto->get($idProducto)?->cantidad_pedida ?? 0;
        $facturada = $detFactura->cantidad_facturada;
        $coincide = min($facturada, $pedida);

        if ($coincide > 0) {
            $coincidentes[] = ['id_producto' => $idProducto, 'nombre_producto' => $detFactura->nombre_producto, 'cantidad' => $coincide];
        }
        if ($facturada > $pedida) {
            $sobrantes[] = ['id_producto' => $idProducto, 'nombre_producto' => $detFactura->nombre_producto, 'cantidad' => $facturada - $pedida];
        }
    }

    foreach ($pedidoPorProducto as $idProducto => $detOrden) {
        $facturada = $facturadoPorProducto->get($idProducto)?->cantidad_facturada ?? 0;
        if ($facturada < $detOrden->cantidad_pedida) {
            $faltantes[] = ['id_producto' => $idProducto, 'nombre_producto' => $detOrden->nombre_producto, 'cantidad' => $detOrden->cantidad_pedida - $facturada];
        }
    }

    return response()->json(compact('coincidentes', 'sobrantes', 'faltantes'));
}

// POST /facturas/{id}/procesar — el corazón del módulo de Compra:
// ingresa al inventario lo que corresponde y genera las novedades.
public function procesar(Request $request, int $id): JsonResponse
{
    $factura = Factura::with('detalles')->find($id);
    if (!$factura) {
        return response()->json(['message' => 'Factura no encontrada'], 404);
    }
    if (!in_array($factura->estado, ['registrada', 'comparada'])) {
        return response()->json(['message' => 'Esta factura ya fue procesada o está anulada.'], 422);
    }

    DB::transaction(function () use ($factura, $request) {
        $usuarioId = $request->user()->id_usuario;

        if ($factura->tipo_compra === 'directa') {
            // Compra directa: se ingresa TODO lo facturado, sin comparación.
            foreach ($factura->detalles as $detalle) {
                $this->ingresarAInventario($factura, $detalle->id_producto, $detalle->cantidad_facturada, $usuarioId);
                $detalle->update(['ingresado' => true]);
            }

        } else {
            // Con orden: solo se ingresa lo que coincide; el resto genera novedad.
            $orden = $factura->orden()->with('detalles')->first();
            $pedidoPorProducto = $orden->detalles->keyBy('id_producto');
            $facturadoPorProducto = $factura->detalles->keyBy('id_producto');

            foreach ($factura->detalles as $detalle) {
                $pedida = $pedidoPorProducto->get($detalle->id_producto)?->cantidad_pedida ?? 0;
                $coincide = min($detalle->cantidad_facturada, $pedida);

                if ($coincide > 0) {
                    $this->ingresarAInventario($factura, $detalle->id_producto, $coincide, $usuarioId);
                }

                if ($detalle->cantidad_facturada > $pedida) {
                    Novedad::create([
                        'id_factura' => $factura->id_factura,
                        'id_orden' => $orden->id_orden,
                        'id_producto' => $detalle->id_producto,
                        'tipo' => 'sobrante',
                        'cantidad' => $detalle->cantidad_facturada - $pedida,
                        'id_usuario_genera' => $usuarioId,
                    ]);
                }

                $detalle->update(['ingresado' => true]);
            }

            foreach ($orden->detalles as $detOrden) {
                $facturada = $facturadoPorProducto->get($detOrden->id_producto)?->cantidad_facturada ?? 0;
                if ($facturada < $detOrden->cantidad_pedida) {
                    Novedad::create([
                        'id_factura' => $factura->id_factura,
                        'id_orden' => $orden->id_orden,
                        'id_producto' => $detOrden->id_producto,
                        'tipo' => 'faltante',
                        'cantidad' => $detOrden->cantidad_pedida - $facturada,
                        'id_usuario_genera' => $usuarioId,
                    ]);
                }
            }

            $orden->update(['estado' => 'cerrada']);
        }

        $factura->update(['estado' => 'procesada']);
    });

    return response()->json($factura->fresh(['detalles', 'orden']));
}

private function ingresarAInventario(Factura $factura, int $idProducto, float $cantidad, int $idUsuario): void
{
    $stock = InventarioAlmacen::firstOrCreate(
        ['id_almacen' => $factura->id_almacen, 'id_producto' => $idProducto],
        ['stock_actual' => 0, 'stock_minimo' => 0]
    );
    $stock = InventarioAlmacen::where('id', $stock->id)->lockForUpdate()->first();

    $anterior = $stock->stock_actual;
    $nuevo = $anterior + $cantidad;
    $stock->update(['stock_actual' => $nuevo]);

    InventarioMovimiento::create([
        'id_almacen' => $factura->id_almacen,
        'id_producto' => $idProducto,
        'tipo' => 'entrada',
        'cantidad' => $cantidad,
        'stock_anterior' => $anterior,
        'stock_nuevo' => $nuevo,
        'referencia' => 'FAC-' . $factura->id_factura,
        'nota' => 'Ingreso por factura ' . $factura->numero_factura,
        'id_usuario' => $idUsuario,
    ]);
}
}