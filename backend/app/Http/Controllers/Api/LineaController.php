<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class LineaController extends Controller
{
    //
}
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Linea;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class LineaController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Linea::orderBy('nombre_linea')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre_linea' => 'required|string|max:100|unique:linea,nombre_linea',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $validator->errors()], 422);
        }

        $linea = Linea::create($validator->validated());
        return response()->json($linea, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $linea = Linea::find($id);
        if (!$linea) {
            return response()->json(['message' => 'Línea no encontrada'], 404);
        }

        $validator = Validator::make($request->all(), [
            'nombre_linea' => 'required|string|max:100|unique:linea,nombre_linea,' . $id . ',id_linea',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $validator->errors()], 422);
        }

        $linea->update($validator->validated());
        return response()->json($linea);
    }

    public function destroy(int $id): JsonResponse
    {
        $linea = Linea::find($id);
        if (!$linea) {
            return response()->json(['message' => 'Línea no encontrada'], 404);
        }

        $linea->delete();
        return response()->json(['message' => 'Línea eliminada correctamente']);
    }
}