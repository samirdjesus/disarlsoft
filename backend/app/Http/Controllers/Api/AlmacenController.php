<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Almacen;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class AlmacenController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Almacen::orderBy('nombre')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre'      => 'required|string|max:100|unique:almacenes,nombre',
            'descripcion' => 'nullable|string|max:200',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $validator->errors()], 422);
        }

        $almacen = Almacen::create([...$validator->validated(), 'activo' => true]);
        return response()->json($almacen, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $almacen = Almacen::find($id);
        if (!$almacen) {
            return response()->json(['message' => 'Almacén no encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'nombre'      => 'sometimes|string|max:100|unique:almacenes,nombre,' . $id . ',id_almacen',
            'descripcion' => 'nullable|string|max:200',
            'activo'      => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $validator->errors()], 422);
        }

        $almacen->update($validator->validated());
        return response()->json($almacen);
    }
}