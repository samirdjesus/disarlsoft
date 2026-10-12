<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DetalleTomaInventario;
use App\Models\InventarioAlmacen;
use App\Models\InventarioMovimiento;
use App\Models\TomaInventario;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TomaInventarioController extends Controller
{
    // Carga una toma y comprueba que el usuario tenga acceso a su almacén.
    private function tomaAutorizada(Request $request, int $id): TomaInventario
    {
        $toma = TomaInventario::find($id);
        abort_if(!$toma, 404, 'Toma no encontrada');
        $this->autorizarAlmacen($request, $toma->id_almacen);

        return $toma;
    }

    public function index(Request $request): JsonResponse
    {
        $query = TomaInventario::with('almacen');

        if ($request->filled('id_almacen')) $query->where('id_almacen', $request->id_almacen);
        if ($request->filled('estado'))     $query->where('estado', $request->estado);

        $this->limitarAlmacenes($query, $request);

        return response()->json($query->orderByDesc('fecha_apertura')->paginate(20));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $this->tomaAutorizada($request, $id);

        $toma = TomaInventario::with(['almacen', 'usuarioApertura', 'usuarioCierre'])->find($id);
        return response()->json($toma);
    }

    // POST /inventario/tomas: abre una toma y congela el stock actual
    // de TODOS los productos con registro en ese almacén.
    public function abrir(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_almacen'    => 'required|integer|exists:almacenes,id_almacen',
            'periodo'       => 'nullable|string|max:20',
            'observaciones' => 'nullable|string|max:300',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $validator->errors()], 422);
        }

        // No se permiten dos tomas abiertas al mismo tiempo en el mismo almacén
        $yaAbierta = TomaInventario::where('id_almacen', $request->id_almacen)
            ->where('estado', 'abierta')
            ->exists();

        if ($yaAbierta) {
            return response()->json(['message' => 'Ya existe una toma de inventario abierta para este almacén.'], 422);
        }

        $toma = DB::transaction(function () use ($request) {
            $toma = TomaInventario::create([
                'id_almacen'          => $request->id_almacen,
                'periodo'             => $request->periodo,
                'fecha_apertura'      => now(),
                'estado'              => 'abierta',
                'id_usuario_apertura' => $request->user()->id_usuario,
                'observaciones'       => $request->observaciones,
            ]);

            $stocks = InventarioAlmacen::where('id_almacen', $request->id_almacen)->get();

            foreach ($stocks as $stock) {
                DetalleTomaInventario::create([
                    'id_toma'       => $toma->id_toma,
                    'id_producto'   => $stock->id_producto,
                    'stock_sistema' => $stock->stock_actual,
                ]);
            }

            return $toma;
        });

        return response()->json($toma->load('detalles'), 201);
    }

    public function detalles(Request $request, int $id): JsonResponse
    {
        $this->tomaAutorizada($request, $id);

        $detalles = DetalleTomaInventario::with('producto')
            ->where('id_toma', $id)
            ->orderBy('id_detalle')
            ->get();

        return response()->json($detalles);
    }

    // Pensado para el lector de código de barras: busca el detalle de la toma
    // por el código del producto, para que el frontend sepa qué fila actualizar.
    public function buscarDetalle(Request $request, int $id, string $cod): JsonResponse
    {
        $this->tomaAutorizada($request, $id);

        $detalle = DetalleTomaInventario::with('producto')
            ->where('id_toma', $id)
            ->whereHas('producto', fn ($q) => $q->where('cod_producto', $cod))
            ->first();

        if (!$detalle) {
            return response()->json(['message' => 'Este producto no está en la toma (o el código no existe).'], 404);
        }

        return response()->json($detalle);
    }

    // POST /inventario/tomas/{id}/conteo: registra el conteo físico de un producto
    public function registrarConteo(Request $request, int $id): JsonResponse
    {
        $toma = TomaInventario::find($id);
        if (!$toma || $toma->estado !== 'abierta') {
            return response()->json(['message' => 'Toma no encontrada o ya cerrada.'], 422);
        }

        $this->autorizarAlmacen($request, $toma->id_almacen);

        $validator = Validator::make($request->all(), [
            'id_producto'   => 'required|integer|exists:productos,id_producto',
            'stock_contado' => 'required|numeric|min:0',
            'observaciones' => 'nullable|string|max:200',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $validator->errors()], 422);
        }

        $detalle = DetalleTomaInventario::where('id_toma', $id)
            ->where('id_producto', $request->id_producto)
            ->first();

        if (!$detalle) {
            return response()->json(['message' => 'Este producto no pertenece a la toma.'], 404);
        }

        $detalle->update([
            'stock_contado'     => $request->stock_contado,
            'diferencia'        => $request->stock_contado - $detalle->stock_sistema,
            'id_usuario_conteo' => $request->user()->id_usuario,
            'fecha_conteo'      => now(),
            'observaciones'     => $request->observaciones,
        ]);

        return response()->json($detalle->fresh('producto'));
    }

    // GET /inventario/tomas/{id}/comparativo: resumen antes de cerrar
    public function comparativo(Request $request, int $id): JsonResponse
    {
        $this->tomaAutorizada($request, $id);

        $detalles = DetalleTomaInventario::with('producto')->where('id_toma', $id)->get();

        return response()->json([
            'total_productos' => $detalles->count(),
            'contados'        => $detalles->whereNotNull('stock_contado')->count(),
            'pendientes'      => $detalles->whereNull('stock_contado')->count(),
            'con_diferencia'  => $detalles->where('diferencia', '!=', 0)->whereNotNull('diferencia')->count(),
            'sobrantes'       => $detalles->where('diferencia', '>', 0)->count(),
            'faltantes'       => $detalles->where('diferencia', '<', 0)->count(),
            'detalles'        => $detalles,
        ]);
    }

    // POST /inventario/tomas/{id}/cerrar: aplica los ajustes a inventario_almacen
    // y genera los movimientos de auditoría correspondientes.
    public function cerrar(Request $request, int $id): JsonResponse
    {
        $toma = TomaInventario::find($id);
        if (!$toma || $toma->estado !== 'abierta') {
            return response()->json(['message' => 'Toma no encontrada o ya cerrada.'], 422);
        }

        $this->autorizarAlmacen($request, $toma->id_almacen);

        $pendientes = DetalleTomaInventario::where('id_toma', $id)->whereNull('stock_contado')->count();
        if ($pendientes > 0) {
            return response()->json([
                'message' => "Hay {$pendientes} producto(s) sin contar. Debes registrar el conteo de todos antes de cerrar.",
            ], 422);
        }

        DB::transaction(function () use ($toma, $request) {
            $detallesConDiferencia = DetalleTomaInventario::where('id_toma', $toma->id_toma)
                ->where('diferencia', '!=', 0)
                ->get();

            foreach ($detallesConDiferencia as $detalle) {
                $stock = InventarioAlmacen::where('id_almacen', $toma->id_almacen)
                    ->where('id_producto', $detalle->id_producto)
                    ->lockForUpdate()
                    ->first();

                $anterior = $stock->stock_actual;
                $nuevo = $detalle->stock_contado;

                $stock->update(['stock_actual' => $nuevo]);

                InventarioMovimiento::create([
                    'id_almacen'     => $toma->id_almacen,
                    'id_producto'    => $detalle->id_producto,
                    'tipo'           => $detalle->diferencia > 0 ? 'ajuste_positivo' : 'ajuste_negativo',
                    'cantidad'       => abs($detalle->diferencia),
                    'stock_anterior' => $anterior,
                    'stock_nuevo'    => $nuevo,
                    'referencia'     => 'TOMA-' . $toma->id_toma,
                    'nota'           => 'Ajuste por toma de inventario física',
                    'id_usuario'     => $request->user()->id_usuario,
                ]);
            }

            $toma->update([
                'estado'            => 'cerrada',
                'fecha_cierre'      => now(),
                'id_usuario_cierre' => $request->user()->id_usuario,
            ]);
        });

        return response()->json($toma->fresh(['detalles.producto']));
    }
}