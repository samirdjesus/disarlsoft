<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Embalaje;
use App\Models\InventarioAlmacen;
use App\Models\InventarioMovimiento;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class EmbalajeController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Embalaje::with(['productoPadre', 'productoHijo'])->where('activo', true)->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_producto_padre' => 'required|integer|exists:productos,id_producto|different:id_producto_hijo',
            'id_producto_hijo'  => 'required|integer|exists:productos,id_producto',
            'factor_conversion' => 'required|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $validator->errors()], 422);
        }

        $embalaje = Embalaje::create([...$validator->validated(), 'activo' => true]);
        return response()->json($embalaje->load(['productoPadre', 'productoHijo']), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $embalaje = Embalaje::find($id);
        if (!$embalaje) {
            return response()->json(['message' => 'Embalaje no encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'factor_conversion' => 'sometimes|numeric|min:0.01',
            'activo'            => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $validator->errors()], 422);
        }

        $embalaje->update($validator->validated());
        return response()->json($embalaje->fresh(['productoPadre', 'productoHijo']));
    }

    // POST /embalajes/{id}/convertir — la operación real: fracciona N
    // unidades del padre en sus unidades hijas, según el factor fijo.
    public function convertir(Request $request, int $id): JsonResponse
    {
        $embalaje = Embalaje::find($id);
        if (!$embalaje || !$embalaje->activo) {
            return response()->json(['message' => 'Embalaje no encontrado o inactivo'], 404);
        }

        $validator = Validator::make($request->all(), [
            'id_almacen'      => 'required|integer|exists:almacenes,id_almacen',
            'cantidad_padre'  => 'required|numeric|min:0.01',
            'observaciones'   => 'nullable|string|max:300',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $validator->errors()], 422);
        }

        try {
            DB::transaction(function () use ($embalaje, $request) {
                $cantidadHijo = round($request->cantidad_padre * $embalaje->factor_conversion, 2);

                // ─ Descuenta el padre ─
                $stockPadre = InventarioAlmacen::where('id_almacen', $request->id_almacen)
                    ->where('id_producto', $embalaje->id_producto_padre)
                    ->lockForUpdate()
                    ->first();

                $disponible = $stockPadre?->stock_actual ?? 0;
                if ($disponible < $request->cantidad_padre) {
                    throw new \RuntimeException('Stock insuficiente del producto a fraccionar.');
                }

                $anteriorPadre = $stockPadre->stock_actual;
                $nuevoPadre = $anteriorPadre - $request->cantidad_padre;
                $stockPadre->update(['stock_actual' => $nuevoPadre]);

                InventarioMovimiento::create([
                    'id_almacen'     => $request->id_almacen,
                    'id_producto'    => $embalaje->id_producto_padre,
                    'tipo'           => 'ajuste_negativo',
                    'cantidad'       => $request->cantidad_padre,
                    'stock_anterior' => $anteriorPadre,
                    'stock_nuevo'    => $nuevoPadre,
                    'referencia'     => 'EMB-' . $embalaje->id_embalaje,
                    'nota'           => 'Fraccionamiento de embalaje: ' . ($request->observaciones ?? 'conversión a unidades'),
                    'id_usuario'     => $request->user()->id_usuario,
                ]);

                // ─ Suma el hijo ─
                $stockHijo = InventarioAlmacen::firstOrCreate(
                    ['id_almacen' => $request->id_almacen, 'id_producto' => $embalaje->id_producto_hijo],
                    ['stock_actual' => 0, 'stock_minimo' => 0]
                );
                $stockHijo = InventarioAlmacen::where('id', $stockHijo->id)->lockForUpdate()->first();

                $anteriorHijo = $stockHijo->stock_actual;
                $nuevoHijo = $anteriorHijo + $cantidadHijo;
                $stockHijo->update(['stock_actual' => $nuevoHijo]);

                InventarioMovimiento::create([
                    'id_almacen'     => $request->id_almacen,
                    'id_producto'    => $embalaje->id_producto_hijo,
                    'tipo'           => 'ajuste_positivo',
                    'cantidad'       => $cantidadHijo,
                    'stock_anterior' => $anteriorHijo,
                    'stock_nuevo'    => $nuevoHijo,
                    'referencia'     => 'EMB-' . $embalaje->id_embalaje,
                    'nota'           => 'Generado por fraccionamiento de embalaje',
                    'id_usuario'     => $request->user()->id_usuario,
                ]);
            });

            return response()->json(['message' => 'Conversión realizada correctamente']);

        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}