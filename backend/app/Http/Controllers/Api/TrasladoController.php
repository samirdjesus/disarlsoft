<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventarioAlmacen;
use App\Models\InventarioMovimiento;
use App\Models\Producto;
use App\Models\Traslado;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TrasladoController extends Controller
{
    // Consulta de traslados realizados — el requisito que pediste explícitamente
    public function index(Request $request): JsonResponse
    {
        $query = Traslado::with(['almacenOrigen', 'almacenDestino', 'usuario', 'detalles']);

        if ($request->filled('id_almacen_origen'))  $query->where('id_almacen_origen', $request->id_almacen_origen);
        if ($request->filled('id_almacen_destino')) $query->where('id_almacen_destino', $request->id_almacen_destino);
        if ($request->filled('fecha_desde'))        $query->whereDate('fecha_traslado', '>=', $request->fecha_desde);
        if ($request->filled('fecha_hasta'))        $query->whereDate('fecha_traslado', '<=', $request->fecha_hasta);

        return response()->json($query->orderByDesc('id_traslado')->paginate($request->get('per_page', 20)));
    }

    public function show(int $id): JsonResponse
    {
        $traslado = Traslado::with(['detalles.producto', 'almacenOrigen', 'almacenDestino', 'usuario'])->find($id);
        if (!$traslado) {
            return response()->json(['message' => 'Traslado no encontrado'], 404);
        }
        return response()->json($traslado);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_almacen_origen'       => 'required|integer|exists:almacenes,id_almacen',
            'id_almacen_destino'      => 'required|integer|different:id_almacen_origen|exists:almacenes,id_almacen',
            'fecha_traslado'          => 'required|date',
            'observaciones'           => 'nullable|string|max:300',
            'detalles'                => 'required|array|min:1',
            'detalles.*.id_producto'  => 'required|integer|exists:productos,id_producto',
            'detalles.*.cantidad'     => 'required|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $validator->errors()], 422);
        }

        try {
            $traslado = DB::transaction(function () use ($request) {
                $traslado = Traslado::create([
                    'id_almacen_origen'  => $request->id_almacen_origen,
                    'id_almacen_destino' => $request->id_almacen_destino,
                    'id_usuario'         => $request->user()->id_usuario,
                    'fecha_traslado'     => $request->fecha_traslado,
                    'observaciones'      => $request->observaciones,
                ]);

                foreach ($request->detalles as $d) {
                    $producto = Producto::find($d['id_producto']);

                    $traslado->detalles()->create([
                        'id_producto'     => $d['id_producto'],
                        'nombre_producto' => $producto->nombre_producto,
                        'cantidad'        => $d['cantidad'],
                    ]);

                    // ─ Salida del origen ─
                    $stockOrigen = InventarioAlmacen::firstOrCreate(
                        ['id_almacen' => $request->id_almacen_origen, 'id_producto' => $d['id_producto']],
                        ['stock_actual' => 0, 'stock_minimo' => 0]
                    );
                    $stockOrigen = InventarioAlmacen::where('id', $stockOrigen->id)->lockForUpdate()->first();

                    $anteriorOrigen = $stockOrigen->stock_actual;
                    $nuevoOrigen = $anteriorOrigen - $d['cantidad'];

                    if ($nuevoOrigen < 0) {
                        throw new \RuntimeException("Stock insuficiente de \"{$producto->nombre_producto}\" en el almacén de origen.");
                    }

                    $stockOrigen->update(['stock_actual' => $nuevoOrigen]);

                    InventarioMovimiento::create([
                        'id_almacen'     => $request->id_almacen_origen,
                        'id_producto'    => $d['id_producto'],
                        'tipo'           => 'traslado_salida',
                        'cantidad'       => $d['cantidad'],
                        'stock_anterior' => $anteriorOrigen,
                        'stock_nuevo'    => $nuevoOrigen,
                        'referencia'     => 'TRAS-' . $traslado->id_traslado,
                        'nota'           => 'Traslado hacia almacén ' . $request->id_almacen_destino,
                        'id_usuario'     => $request->user()->id_usuario,
                    ]);

                    // ─ Entrada al destino ─
                    $stockDestino = InventarioAlmacen::firstOrCreate(
                        ['id_almacen' => $request->id_almacen_destino, 'id_producto' => $d['id_producto']],
                        ['stock_actual' => 0, 'stock_minimo' => 0]
                    );
                    $stockDestino = InventarioAlmacen::where('id', $stockDestino->id)->lockForUpdate()->first();

                    $anteriorDestino = $stockDestino->stock_actual;
                    $nuevoDestino = $anteriorDestino + $d['cantidad'];
                    $stockDestino->update(['stock_actual' => $nuevoDestino]);

                    InventarioMovimiento::create([
                        'id_almacen'     => $request->id_almacen_destino,
                        'id_producto'    => $d['id_producto'],
                        'tipo'           => 'traslado_entrada',
                        'cantidad'       => $d['cantidad'],
                        'stock_anterior' => $anteriorDestino,
                        'stock_nuevo'    => $nuevoDestino,
                        'referencia'     => 'TRAS-' . $traslado->id_traslado,
                        'nota'           => 'Traslado desde almacén ' . $request->id_almacen_origen,
                        'id_usuario'     => $request->user()->id_usuario,
                    ]);
                }

                return $traslado;
            });

            return response()->json($traslado->load(['detalles', 'almacenOrigen', 'almacenDestino']), 201);

        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}