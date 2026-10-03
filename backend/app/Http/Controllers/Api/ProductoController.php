<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class ProductoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Producto::with(['marca', 'linea', 'proveedor']);

        if ($request->filled('nombre'))       $query->where('nombre_producto', 'ilike', '%' . $request->nombre . '%');
        if ($request->filled('id_marca'))     $query->where('id_marca', $request->id_marca);
        if ($request->filled('id_linea'))     $query->where('id_linea', $request->id_linea);
        if ($request->filled('id_proveedor')) $query->where('id_proveedor', $request->id_proveedor);
        if ($request->has('estado'))          $query->where('estado', $request->boolean('estado'));

        return response()->json(
            $query->orderBy('nombre_producto')->paginate($request->get('per_page', 20))
        );
    }

    // Para el lector de código de barras en Compras/Ventas
    public function buscarCodigo(string $cod): JsonResponse
    {
        $producto = Producto::with(['marca', 'linea'])
            ->where('cod_producto', $cod)
            ->where('estado', true)
            ->first();

        if (!$producto) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        return response()->json($producto);
    }

    public function show(int $id): JsonResponse
    {
        $producto = Producto::with(['marca', 'linea', 'proveedor'])->find($id);
        if (!$producto) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }
        return response()->json($producto);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_proveedor'     => 'required|integer|exists:proveedores,id_proveedor',
            'id_marca'         => 'nullable|integer|exists:marcas,id_marcas',
            'id_linea'         => 'nullable|integer|exists:linea,id_linea',
            'cod_producto'     => 'required|string|max:50|unique:productos,cod_producto',
            'codigo_proveedor' => 'nullable|string|max:50',
            'nombre_producto'  => 'required|string|max:150',
            'tam_producto'     => 'nullable|string|max:50',
            'ref_producto'     => 'nullable|string|max:50',
            'precio_lista'     => 'required|numeric|min:0',
            'impuesto'         => 'nullable|numeric|min:0|max:1',
            'utilidad'         => 'nullable|numeric|min:0|max:1',
            'descuentopf'      => 'nullable|numeric|min:0|max:1',
            'descuentopp'      => 'nullable|numeric|min:0|max:1',
            'valor_descuento'  => 'nullable|numeric|min:0',
            'docena'           => 'nullable|integer|min:1',
            'val_min'          => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $validator->errors()], 422);
        }

        $producto = Producto::create([...$validator->validated(), 'estado' => true]);
        return response()->json($producto->load(['marca', 'linea', 'proveedor']), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $producto = Producto::find($id);
        if (!$producto) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'id_proveedor'     => 'sometimes|integer|exists:proveedores,id_proveedor',
            'id_marca'         => 'nullable|integer|exists:marcas,id_marcas',
            'id_linea'         => 'nullable|integer|exists:linea,id_linea',
            'cod_producto'     => 'sometimes|string|max:50|unique:productos,cod_producto,' . $id . ',id_producto',
            'codigo_proveedor' => 'nullable|string|max:50',
            'nombre_producto'  => 'sometimes|string|max:150',
            'tam_producto'     => 'nullable|string|max:50',
            'ref_producto'     => 'nullable|string|max:50',
            'precio_lista'     => 'sometimes|numeric|min:0',
            'impuesto'         => 'nullable|numeric|min:0|max:1',
            'utilidad'         => 'nullable|numeric|min:0|max:1',
            'descuentopf'      => 'nullable|numeric|min:0|max:1',
            'descuentopp'      => 'nullable|numeric|min:0|max:1',
            'valor_descuento'  => 'nullable|numeric|min:0',
            'docena'           => 'nullable|integer|min:1',
            'val_min'          => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $validator->errors()], 422);
        }

        $producto->update($validator->validated());
        return response()->json($producto->fresh(['marca', 'linea', 'proveedor']));
    }

    // "Eliminar" = desactivar, nunca borrado físico — un producto puede
    // estar referenciado en ventas/compras históricas ya realizadas.
    public function destroy(int $id): JsonResponse
    {
        $producto = Producto::find($id);
        if (!$producto) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        $producto->update(['estado' => false]);
        return response()->json(['message' => 'Producto desactivado correctamente']);
    }

    public function reactivar(int $id): JsonResponse
    {
        $producto = Producto::find($id);
        if (!$producto) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        $producto->update(['estado' => true]);
        return response()->json(['message' => 'Producto reactivado correctamente']);
    }
}