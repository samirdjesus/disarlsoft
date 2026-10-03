<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Marca;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class MarcaController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Marca::orderBy('nombre_marca')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre_marca' => 'required|string|max:100|unique:marcas,nombre_marca',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $validator->errors()], 422);
        }

        $marca = Marca::create($validator->validated());
        return response()->json($marca, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $marca = Marca::find($id);
        if (!$marca) {
            return response()->json(['message' => 'Marca no encontrada'], 404);
        }

        $validator = Validator::make($request->all(), [
            'nombre_marca' => 'required|string|max:100|unique:marcas,nombre_marca,' . $id . ',id_marcas',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $validator->errors()], 422);
        }

        $marca->update($validator->validated());
        return response()->json($marca);
    }

    public function destroy(int $id): JsonResponse
    {
        $marca = Marca::find($id);
        if (!$marca) {
            return response()->json(['message' => 'Marca no encontrada'], 404);
        }

        // Antes de borrar: si ya hay productos usando esta marca, la FK
        // de productos.id_marca lo va a rechazar solo (RESTRICT por defecto),
        // así que ni siquiera necesitamos chequearlo manualmente aquí —
        // Postgres nos protege de dejar productos huérfanos.
        $marca->delete();
        return response()->json(['message' => 'Marca eliminada correctamente']);
    }
}