<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventarioAlmacen;
use App\Models\InventarioMovimiento;
use App\Models\Producto;
use App\Models\VentaCabecera;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class VentaController extends Controller
{
    private array $reglasDetalle = [
        'detalles'                   => 'required|array|min:1',
        'detalles.*.id_producto'     => 'required|integer|exists:productos,id_producto',
        'detalles.*.precio_venta'    => 'required|numeric|min:0',
        'detalles.*.cantidad'        => 'required|numeric|min:0.01',
        'detalles.*.impuesto'        => 'nullable|numeric|min:0',
        'detalles.*.iva_valor'       => 'nullable|numeric|min:0',
        'detalles.*.descuento_valor' => 'nullable|numeric|min:0',
        'detalles.*.utilidad_valor'  => 'nullable|numeric|min:0',
        'detalles.*.total_linea'     => 'required|numeric|min:0',
    ];

    // ─── Listar ventas pagadas (solo de los almacenes del usuario) ───
    public function index(Request $request): JsonResponse
    {
        $query = VentaCabecera::with(['detalles', 'cliente', 'vendedor'])
            ->where('estado', 'pagada');

        if ($request->filled('id_almacen'))  $query->where('id_almacen', $request->id_almacen);
        if ($request->filled('doc_venta'))   $query->where('doc_venta', $request->doc_venta);
        if ($request->filled('fecha_desde')) $query->whereDate('fecha_venta', '>=', $request->fecha_desde);
        if ($request->filled('fecha_hasta')) $query->whereDate('fecha_venta', '<=', $request->fecha_hasta);

        $this->limitarAlmacenes($query, $request);

        return response()->json(
            $query->orderByDesc('fecha_venta')->orderByDesc('doc_venta')->paginate($request->get('per_page', 20))
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $venta = VentaCabecera::with(['detalles.producto', 'cliente', 'vendedor', 'almacen'])->find($id);
        if (!$venta) {
            return response()->json(['message' => 'Venta no encontrada'], 404);
        }

        $this->autorizarAlmacen($request, $venta->id_almacen);

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

                // Un borrador solo lo toca su dueño (o un administrador).
                // Se responde con JSON en vez de abort(): dentro de este try, un
                // abort sería capturado por el catch y devuelto como error 500.
                if (!$request->user()->esAdmin() && (int) $cabecera->id_vendedor !== (int) $request->user()->id_usuario) {
                    DB::rollBack();
                    return response()->json(['message' => 'Este borrador pertenece a otro vendedor.'], 403);
                }

                $cabecera->update($datos);
            } else {
                $cabecera = VentaCabecera::create($datos);
            }

            // Reemplazo completo del detalle: simple y seguro para un borrador.
            $cabecera->detalles()->delete();
            foreach ($request->detalles as $d) {
                $producto = Producto::find($d['id_producto']);
                $cabecera->detalles()->create([
                    'id_producto'     => $d['id_producto'],
                    'nombre_producto' => $producto->nombre_producto, // snapshot: nunca se confía en el nombre que mande el cliente
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

    public function eliminarBorrador(Request $request, int $id): JsonResponse
    {
        // Un vendedor solo elimina sus propios borradores; el admin, cualquiera.
        $cabecera = VentaCabecera::where('id_venta', $id)
            ->where('estado', 'borrador')
            ->when(!$request->user()->esAdmin(), fn ($q) => $q->where('id_vendedor', $request->user()->id_usuario))
            ->first();

        if (!$cabecera) {
            return response()->json(['message' => 'Borrador no encontrado.'], 404);
        }

        $cabecera->detalles()->delete();
        $cabecera->delete(); // borrado físico: un borrador nunca tocó inventario, no hay nada que reversar

        return response()->json(['ok' => true]);
    }

    // ─── COBRAR: convierte un borrador en venta pagada, o crea una directa ───
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
            // Advisory lock: doc_venta secuencial sin huecos ni duplicados aunque
            // dos cajeros cobren al mismo tiempo.
            DB::statement('SELECT pg_advisory_xact_lock(12345)');

            // Se consideran TODOS los documentos ya usados (incluidas ventas anuladas):
            // un número de documento anulado nunca se reutiliza.
            $nextDoc = (VentaCabecera::whereNotNull('doc_venta')->max('doc_venta') ?? 0) + 1;

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

                if (!$request->user()->esAdmin() && (int) $cabecera->id_vendedor !== (int) $request->user()->id_usuario) {
                    DB::rollBack();
                    return response()->json(['message' => 'Este borrador pertenece a otro vendedor.'], 403);
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
                'id_venta'     => $cabecera->id_venta,
                'doc_venta'    => $cabecera->doc_venta,
                'estado'       => 'pagada',
                'advertencias' => $advertencias,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[VentaController@cobrar]', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Error al procesar la venta', 'error' => $e->getMessage()], 500);
        }
    }

    // ─── ANULAR una venta pagada: reversa el inventario ───
    public function anular(Request $request, int $id): JsonResponse
    {
        $cabecera = VentaCabecera::with('detalles')->find($id);

        if (!$cabecera || $cabecera->estado !== 'pagada') {
            return response()->json(['message' => 'Venta no encontrada o no está pagada.'], 422);
        }

        // Antes de abrir la transacción, para que el 403 no se confunda con un error interno
        $this->autorizarAlmacen($request, $cabecera->id_almacen);

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