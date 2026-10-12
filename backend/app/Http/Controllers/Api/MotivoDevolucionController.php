<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MotivoDevolucion;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class MotivoDevolucionController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(MotivoDevolucion::where('activo', true)->orderBy('nombre')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:100|unique:motivos_devolucion,nombre',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $validator->errors()], 422);
        }

        $motivo = MotivoDevolucion::create([...$validator->validated(), 'activo' => true]);
        return response()->json($motivo, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $motivo = MotivoDevolucion::find($id);
        if (!$motivo) {
            return response()->json(['message' => 'Motivo no encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'nombre' => 'sometimes|string|max:100|unique:motivos_devolucion,nombre,' . $id . ',id_motivo',
            'activo' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $validator->errors()], 422);
        }

        $motivo->update($validator->validated());
        return response()->json($motivo);
    }
}