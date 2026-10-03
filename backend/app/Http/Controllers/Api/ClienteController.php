<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class ClienteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Cliente::query();

        if ($request->filled('nombre')) {
            $query->where('nombre_cliente', 'ilike', '%' . $request->nombre . '%');
        }
        if ($request->filled('identificacion')) {
            $query->where('identificacion', $request->identificacion);
        }

        return response()->json(
            $query->orderBy('nombre_cliente')->paginate($request->get('per_page', 20))
        );
    }

    public function show(int $id): JsonResponse
    {
        $cliente = Cliente::find($id);
        if (!$cliente) {
            return response()->json(['message' => 'Cliente no encontrado'], 404);
        }
        return response()->json($cliente);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre_cliente'  => 'required|string|max:150',
            'identificacion'  => 'nullable|string|max:30|unique:clientes,identificacion',
            'telefono'        => 'nullable|string|max:20',
            'celular'         => 'nullable|string|max:20',
            'correo'          => 'nullable|email|max:120',
            'direccion'       => 'nullable|string|max:200',
            'ciudad'          => 'nullable|string|max:80',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $validator->errors()], 422);
        }

        $cliente = Cliente::create([...$validator->validated(), 'activo' => true]);
        return response()->json($cliente, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $cliente = Cliente::find($id);
        if (!$cliente) {
            return response()->json(['message' => 'Cliente no encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'nombre_cliente' => 'sometimes|string|max:150',
            'identificacion' => 'nullable|string|max:30|unique:clientes,identificacion,' . $id . ',id_cliente',
            'telefono'       => 'nullable|string|max:20',
            'celular'        => 'nullable|string|max:20',
            'correo'         => 'nullable|email|max:120',
            'direccion'      => 'nullable|string|max:200',
            'ciudad'         => 'nullable|string|max:80',
            'activo'         => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $validator->errors()], 422);
        }

        $cliente->update($validator->validated());
        return response()->json($cliente);
    }

    public function destroy(int $id): JsonResponse
    {
        $cliente = Cliente::find($id);
        if (!$cliente) {
            return response()->json(['message' => 'Cliente no encontrado'], 404);
        }

        $cliente->delete();
        return response()->json(['message' => 'Cliente eliminado correctamente']);
    }
}