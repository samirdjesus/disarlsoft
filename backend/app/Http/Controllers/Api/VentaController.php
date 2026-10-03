<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventarioAlmacen;
use App\Models\InventarioMovimiento;
use App\Models\Producto;
use App\Models\VentaCabecera;
use App\Models\VentaDetalle;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class VentaController extends Controller
{
    private array $reglasDetalle = [
        'detalles'                  => 'required|array|min:1',
        'detalles.*.id_producto'    => 'required|integer|exists:productos,id_producto',
        'detalles.*.precio_venta'   => 'required|numeric|min:0',
        'detalles.*.cantidad'       => 'required|numeric|min:0.01',
        'detalles.*.impuesto'       => 'nullable|numeric|min:0',
        'detalles.*.iva_valor'      => 'nullable|numeric|min:0',
        'detalles.*.descuento_valor'=> 'nullable|numeric|min:0',
        'detalles.*.utilidad_valor' => 'nullable|numeric|min:0',
        'detalles.*.total_linea'    => 'required|numeric|min:0',
    ];

    // ─── Listar ventas pagadas ──────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $query = VentaCabecera::with(['detalles', 'cliente', 'vendedor'])
            ->where('estado', 'pagada');

        if ($request->filled('id_almacen')) $query->where('id_almacen', $request->id_almacen);
        if ($request->filled('doc_venta'))  $query->where('doc_venta', $request->doc_venta);
        if ($request->filled('fecha_desde'))$query->whereDate('fecha_venta', '>=', $request->fecha_desde);
        if ($request->filled('fecha_hasta'))$query->whereDate('fecha_venta', '<=', $request->fecha_hasta);

        return response()->json(
            $query->orderByDesc('fecha_venta')->orderByDesc('doc_venta')->paginate($request->get('per_page', 20))
        );
    }

    public function show(int $id): JsonResponse
    {
        $venta = VentaCabecera::with(['detalles.producto', 'cliente', 'vendedor', 'almacen'])->find($id);
        if (!$venta) {
            return response()->json(['message' => 'Venta no encontrada'], 404);
        }
        return response()->json($venta);
    }

    // ─── Borradores activos del vendedor autenticado ───────────
    public function borradores(Request $request): JsonResponse
    {
        $borradores = VentaCabecera::with(['detalles.producto'])
            ->where('estado', 'borrador')
            ->where('id_vendedor', $request->user()->id_usuario)
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get();

        return response()->json($borradores);
    }

    // ─── Guardar/actualizar borrador (NO toca inventario) ──────
    public function guardarBorrador(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), array_merge([
            'id_venta'              => 'nullable|integer',
            'id_almacen'            => 'required|integer|exists:almacenes,id_almacen',
            'id_cliente'            => 'nullable|integer|exists:clientes,id_cliente',
            'fecha_venta'           => 'required|date',
            'hora_venta'            => 'nullable|date_format:H:i:s',
            'subtotal'              => 'nullable|numeric|min:0',
            'descuento_porcentaje'  => 'nullable|numeric|min:0',
            'descuento_valor'       => 'nullable|numeric|min:0',
            'total'                 => 'nullable|numeric|min:0',
        ], $this->reglasDetalle));

        if ($validator->fails()) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $datos = [
                'id_almacen'            => $request->id_almacen,
                'id_cliente'            => $request->id_cliente,
                'id_vendedor'           => $request->user()->id_usuario,
                'fecha_venta'           => $request->fecha_venta,
                'hora_venta'            => $request->hora_venta,
                'subtotal'              => $request->subtotal ?? 0,
                'descuento_porcentaje'  => $request->descuento_porcentaje ?? 0,
                'descuento_valor'       => $request->descuento_valor ?? 0,
                'total'                 => $request->total ?? 0,
                'estado'                => 'borrador',
                'doc_venta'             => null,
            ];

            if ($request->filled('id_venta')) {
                $cabecera = VentaCabecera::find($request->id_venta);

                if (!$cabecera || $cabecera->estado !== 'borrador') {
                    DB::rollBack();
                    return response()->json(['message' => 'Este borrador no existe o ya fue cobrado.'], 422);
                }

                $cabecera->update($datos);
            } else {
                $cabecera = VentaCabecera::create($datos);
            }

            // Reemplazo completo del detalle — simple y seguro para un borrador.
            $cabecera->detalles()->delete();
            foreach ($request->detalles as $d) {
                $producto = Producto::find($d['id_producto']);
                $cabecera->detalles()->create([
                    'id_producto'     => $d['id_producto'],
                    'nombre_producto' => $producto->nombre_producto, // snapshot, nunca confiamos en el nombre que mande el cliente
                    'precio_venta'    => $d['precio_venta'],
                    'cantidad'        => $d['cantidad'],
                    'impuesto'        => $d['impuesto'] ?? 0,
                    'iva_valor'       => $d['iva_valor'] ?? 0,
                    'descuento_valor' => $d['descuento_valor'] ?? 0,
                    'costo_unitario'  => $producto->precio_lista,
                    'utilidad_valor'  => $d['utilidad_valor'] ?? 0,
                    'total_linea'     => $d['total_linea'],
                ]);
            }

            DB::commit();
            return response()->json(['id_venta' => $cabecera->id_venta, 'estado' => 'borrador']);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[VentaController@guardarBorrador]', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Error al guardar borrador'], 500);
        }
    }

    public function eliminarBorrador(int $id): JsonResponse
    {
        $cabecera = VentaCabecera::where('id_venta', $id)->where('estado', 'borrador')->first();

        if (!$cabecera) {
            return response()->json(['message' => 'Borrador no encontrado.'], 404);
        }

        $cabecera->detalles()->delete();
        $cabecera->delete(); // borrado físico — un borrador nunca tocó inventario, no hay nada que reversar

        return response()->json(['ok' => true]);
    }

    // ─── COBRAR — convierte borrador en venta pagada, o crea una directa ───
    public function cobrar(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), array_merge([
            'id_venta'              => 'nullable|integer',
            'id_almacen'            => 'required|integer|exists:almacenes,id_almacen',
            'id_cliente'            => 'nullable|integer|exists:clientes,id_cliente',
            'fecha_venta'           => 'required|date',
            'hora_venta'            => 'nullable|date_format:H:i:s',
            'subtotal'              => 'required|numeric|min:0',
            'descuento_porcentaje'  => 'nullable|numeric|min:0',
            'descuento_valor'       => 'nullable|numeric|min:0',
            'total'                 => 'required|numeric|min:0',
            'pago'                  => 'nullable|numeric|min:0',
        ], $this->reglasDetalle));

        if ($validator->fails()) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            // Advisory lock: garantiza doc_venta secuencial sin huecos ni
            // duplicados aunque dos cajeros cobren al mismo tiempo.
            DB::statement('SELECT pg_advisory_xact_lock(12345)');

            $nextDoc = (VentaCabecera::where('estado', 'pagada')->max('doc_venta') ?? 0) + 1;

            $datos = [
                'doc_venta'             => $nextDoc,
                'id_almacen'            => $request->id_almacen,
                'id_cliente'            => $request->id_cliente,
                'id_vendedor'           => $request->user()->id_usuario,
                'fecha_venta'           => $request->fecha_venta,
                'hora_venta'            => $request->hora_venta,
                'subtotal'              => $request->subtotal,
                'descuento_porcentaje'  => $request->descuento_porcentaje ?? 0,
                'descuento_valor'       => $request->descuento_valor ?? 0,
                'total'                 => $request->total,
                'pago'                  => $request->pago,
                'estado'                => 'pagada',
                'updated_at'            => now(),
            ];

            if ($request->filled('id_venta')) {
                $cabecera = VentaCabecera::find($request->id_venta);

                if (!$cabecera || $cabecera->estado !== 'borrador') {
                    DB::rollBack();
                    return response()->json(['message' => 'Esta venta ya fue cobrada o no existe.'], 422);
                }

                $cabecera->update($datos);
            } else {
                $cabecera = VentaCabecera::create($datos);
            }

            $cabecera->detalles()->delete();
            foreach ($request->detalles as $d) {
                $producto = Producto::find($d['id_producto']);
                $cabecera->detalles()->create([
                    'id_producto'     => $d['id_producto'],
                    'nombre_producto' => $producto->nombre_producto,
                    'precio_venta'    => $d['precio_venta'],
                    'cantidad'        => $d['cantidad'],
                    'impuesto'        => $d['impuesto'] ?? 0,
                    'iva_valor'       => $d['iva_valor'] ?? 0,
                    'descuento_valor' => $d['descuento_valor'] ?? 0,
                    'costo_unitario'  => $producto->precio_lista,
                    'utilidad_valor'  => $d['utilidad_valor'] ?? 0,
                    'total_linea'     => $d['total_linea'],
                ]);
            }

            // ─ Descontar inventario + auditar ─
            $advertencias = [];
            foreach ($request->detalles as $d) {
                $stock = InventarioAlmacen::firstOrCreate(
                    ['id_almacen' => $request->id_almacen, 'id_producto' => $d['id_producto']],
                    ['stock_actual' => 0, 'stock_minimo' => 0]
                );
                $stock = InventarioAlmacen::where('id', $stock->id)->lockForUpdate()->first();

                $anterior = $stock->stock_actual;
                $nuevo = $anterior - $d['cantidad'];
                $stock->update(['stock_actual' => $nuevo]);

                if ($nuevo < 0) {
                    $advertencias[] = "Producto {$d['id_producto']} quedó con stock negativo ({$nuevo}).";
                }

                InventarioMovimiento::create([
                    'id_almacen'     => $request->id_almacen,
                    'id_producto'    => $d['id_producto'],
                    'tipo'           => 'salida',
                    'cantidad'       => $d['cantidad'],
                    'stock_anterior' => $anterior,
                    'stock_nuevo'    => $nuevo,
                    'referencia'     => 'V-' . $nextDoc,
                    'nota'           => 'Venta POS #' . $nextDoc,
                    'id_usuario'     => $request->user()->id_usuario,
                ]);
            }

            DB::commit();

            return response()->json([
                'id_venta'   => $cabecera->id_venta,
                'doc_venta'  => $cabecera->doc_venta,
                'estado'     => 'pagada',
                'advertencias' => $advertencias,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[VentaController@cobrar]', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Error al procesar la venta', 'error' => $e->getMessage()], 500);
        }
    }

    // ─── ANULAR una venta pagada — reversa inventario (corrige el bug del legacy) ───
    public function anular(Request $request, int $id): JsonResponse
    {
        $cabecera = VentaCabecera::with('detalles')->find($id);

        if (!$cabecera || $cabecera->estado !== 'pagada') {
            return response()->json(['message' => 'Venta no encontrada o no está pagada.'], 422);
        }

        DB::beginTransaction();
        try {
            foreach ($cabecera->detalles as $detalle) {
                $stock = InventarioAlmacen::where('id_almacen', $cabecera->id_almacen)
                    ->where('id_producto', $detalle->id_producto)
                    ->lockForUpdate()
                    ->first();

                if ($stock) {
                    $anterior = $stock->stock_actual;
                    $nuevo = $anterior + $detalle->cantidad;
                    $stock->update(['stock_actual' => $nuevo]);

                    InventarioMovimiento::create([
                        'id_almacen'     => $cabecera->id_almacen,
                        'id_producto'    => $detalle->id_producto,
                        'tipo'           => 'entrada',
                        'cantidad'       => $detalle->cantidad,
                        'stock_anterior' => $anterior,
                        'stock_nuevo'    => $nuevo,
                        'referencia'     => 'ANULACION-V-' . $cabecera->doc_venta,
                        'nota'           => 'Reversión por anulación de venta',
                        'id_usuario'     => $request->user()->id_usuario,
                    ]);
                }
            }

            $cabecera->update(['estado' => 'anulada']);

            DB::commit();
            return response()->json(['message' => 'Venta anulada y stock reversado correctamente.']);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[VentaController@anular]', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Error al anular la venta'], 500);
        }
    }
}