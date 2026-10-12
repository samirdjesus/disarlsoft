<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventarioAlmacen;
use App\Models\InventarioMovimiento;
use App\Models\Novedad;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class NovedadController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Novedad::with(['factura', 'orden', 'producto']);

        if ($request->filled('tipo'))   $query->where('tipo', $request->tipo);
        if ($request->filled('estado')) $query->where('estado', $request->estado);

        // El almacén de una novedad es el de su factura
        $query->whereHas('factura', fn ($q) => $this->limitarAlmacenes($q, $request));

        return response()->json($query->orderByDesc('id_novedad')->paginate($request->get('per_page', 20)));
    }

    // POST /novedades/{id}/resolver: ingresar al inventario o descartar
    public function resolver(Request $request, int $id): JsonResponse
    {
        $novedad = Novedad::with('factura')->find($id);
        if (!$novedad || $novedad->estado !== 'pendiente') {
            return response()->json(['message' => 'Novedad no encontrada o ya resuelta.'], 422);
        }

        $this->autorizarAlmacen($request, $novedad->factura->id_almacen);

        $validator = Validator::make($request->all(), [
            'accion' => 'required|in:ingresar,descartar',
            'nota'   => 'required|string|max:300',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $validator->errors()], 422);
        }

        DB::transaction(function () use ($novedad, $request) {
            if ($request->accion === 'ingresar') {
                $factura = $novedad->factura;

                $stock = InventarioAlmacen::firstOrCreate(
                    ['id_almacen' => $factura->id_almacen, 'id_producto' => $novedad->id_producto],
                    ['stock_actual' => 0, 'stock_minimo' => 0]
                );
                $stock = InventarioAlmacen::where('id', $stock->id)->lockForUpdate()->first();

                $anterior = $stock->stock_actual;
                $nuevo = $novedad->tipo === 'sobrante' ? $anterior + $novedad->cantidad : $anterior - $novedad->cantidad;
                $stock->update(['stock_actual' => $nuevo]);

                InventarioMovimiento::create([
                    'id_almacen'     => $factura->id_almacen,
                    'id_producto'    => $novedad->id_producto,
                    'tipo'           => $novedad->tipo === 'sobrante' ? 'ajuste_positivo' : 'ajuste_negativo',
                    'cantidad'       => $novedad->cantidad,
                    'stock_anterior' => $anterior,
                    'stock_nuevo'    => $nuevo,
                    'referencia'     => 'NOVEDAD-' . $novedad->id_novedad,
                    'nota'           => 'Resolución de novedad: ' . $request->nota,
                    'id_usuario'     => $request->user()->id_usuario,
                ]);
            }

            $novedad->update([
                'estado'              => $request->accion === 'ingresar' ? 'ingresada' : 'descartada',
                'id_usuario_resuelve' => $request->user()->id_usuario,
                'nota'                => $request->nota,
            ]);
        });

        return response()->json($novedad->fresh(['factura', 'producto']));
    }
}