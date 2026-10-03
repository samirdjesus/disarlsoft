<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Proveedor;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class ProveedorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Proveedor::query();

        if ($request->filled('nombre')) {
            $query->where('nombre_proveedor', 'ilike', '%' . $request->nombre . '%');
        }
        if ($request->has('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }

        return response()->json(
            $query->orderBy('nombre_proveedor')->paginate($request->get('per_page', 20))
        );
    }

    public function show(int $id): JsonResponse
    {
        $proveedor = Proveedor::find($id);
        if (!$proveedor) {
            return response()->json(['message' => 'Proveedor no encontrado'], 404);
        }
        return response()->json($proveedor);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre_proveedor' => 'required|string|max:150',
            'nit'              => 'nullable|string|max:30|unique:proveedores,nit',
            'contacto_nombre'  => 'nullable|string|max:100',
            'telefono'         => 'nullable|string|max:20',
            'celular'          => 'nullable|string|max:20',
            'correo'           => 'nullable|email|max:120',
            'direccion'        => 'nullable|string|max:200',
            'ciudad'           => 'nullable|string|max:80',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $validator->errors()], 422);
        }

        $proveedor = Proveedor::create([...$validator->validated(), 'activo' => true]);
        return response()->json($proveedor, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $proveedor = Proveedor::find($id);
        if (!$proveedor) {
            return response()->json(['message' => 'Proveedor no encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'nombre_proveedor' => 'sometimes|string|max:150',
            'nit'              => 'nullable|string|max:30|unique:proveedores,nit,' . $id . ',id_proveedor',
            'contacto_nombre'  => 'nullable|string|max:100',
            'telefono'         => 'nullable|string|max:20',
            'celular'          => 'nullable|string|max:20',
            'correo'           => 'nullable|email|max:120',
            'direccion'        => 'nullable|string|max:200',
            'ciudad'           => 'nullable|string|max:80',
            'activo'           => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $validator->errors()], 422);
        }

        $proveedor->update($validator->validated());
        return response()->json($proveedor);
    }

    public function destroy(int $id): JsonResponse
    {
        $proveedor = Proveedor::find($id);
        if (!$proveedor) {
            return response()->json(['message' => 'Proveedor no encontrado'], 404);
        }

        // Igual que con Marcas: si ya tiene productos u órdenes de compra
        // asociadas, la FK correspondiente va a rechazar el borrado sola
        // una vez que existan esas tablas (RESTRICT por defecto).
        $proveedor->delete();
        return response()->json(['message' => 'Proveedor eliminado correctamente']);
    }
}