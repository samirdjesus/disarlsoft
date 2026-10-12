<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Modulo;
use App\Models\RolUsuario;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class RolController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            RolUsuario::withCount(['permisos', 'usuarios'])->orderBy('nombre')->get()
        );
    }

    public function show(int $id): JsonResponse
    {
        $rol = RolUsuario::with('permisos')->find($id);
        if (!$rol) {
            return response()->json(['message' => 'Rol no encontrado'], 404);
        }

        return response()->json([
            'id_rol'      => $rol->id_rol,
            'codigo'      => $rol->codigo,
            'nombre'      => $rol->nombre,
            'descripcion' => $rol->descripcion,
            'id_permisos' => $rol->permisos->pluck('id_permiso'),
        ]);
    }

    // Catálogo completo agrupado por grupo de módulo: base de la matriz
    // "módulo × acción" que va a pintar el frontend con checkboxes.
    public function catalogoPermisos(): JsonResponse
    {
        $modulos = Modulo::where('activo', true)
            ->with('permisos')
            ->orderBy('grupo')->orderBy('orden')->orderBy('nombre')
            ->get();

        $catalogo = $modulos->groupBy('grupo')->map(fn ($items) => $items->map(fn ($m) => [
            'id_modulo' => $m->id_modulo,
            'codigo'    => $m->codigo,
            'nombre'    => $m->nombre,
            'permisos'  => $m->permisos->map(fn ($p) => [
                'id_permiso' => $p->id_permiso,
                'accion'     => $p->accion,
            ])->values(),
        ])->values());

        return response()->json($catalogo);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre'      => 'required|string|max:60|unique:rolusuario,nombre',
            'descripcion' => 'nullable|string|max:200',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $validator->errors()], 422);
        }

        // 'codigo' es el identificador estable: se genera una sola vez a
        // partir del nombre y nunca cambia, aunque el nombre se edite después.
        $base = Str::limit(Str::slug($request->nombre, '_'), 35, '') ?: 'rol';
        $codigo = $base;
        $i = 2;
        while (RolUsuario::where('codigo', $codigo)->exists()) {
            $codigo = $base . '_' . $i++;
        }

        $rol = RolUsuario::create([
            'codigo'      => $codigo,
            'nombre'      => $request->nombre,
            'descripcion' => $request->descripcion,
        ]);

        return response()->json($rol, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $rol = RolUsuario::find($id);
        if (!$rol) {
            return response()->json(['message' => 'Rol no encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'nombre'      => 'sometimes|string|max:60|unique:rolusuario,nombre,' . $id . ',id_rol',
            'descripcion' => 'nullable|string|max:200',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $validator->errors()], 422);
        }

        $rol->update($validator->validated());
        return response()->json($rol);
    }

    public function destroy(int $id): JsonResponse
    {
        $rol = RolUsuario::find($id);
        if (!$rol) {
            return response()->json(['message' => 'Rol no encontrado'], 404);
        }
        if ($rol->codigo === 'admin') {
            return response()->json(['message' => 'El rol administrador no se puede eliminar.'], 422);
        }
        // withTrashed: un usuario "eliminado" (soft delete) sigue apuntando al rol
        if ($rol->usuarios()->withTrashed()->exists()) {
            return response()->json(['message' => 'No se puede eliminar: hay usuarios asignados a este rol.'], 422);
        }

        $rol->delete(); // rol_permiso se limpia solo (cascadeOnDelete)
        return response()->json(['message' => 'Rol eliminado correctamente']);
    }

    // PUT /roles/{id}/permisos — reemplaza el conjunto completo de permisos del rol
    public function asignarPermisos(Request $request, int $id): JsonResponse
    {
        // Solo un administrador puede repartir permisos. Sin esta guarda,
        // alguien con permiso "usuarios/editar" podría darse a sí mismo
        // (vía su rol) acceso a cualquier otro módulo.
     

       
        if (!$request->user()->esAdmin()) {
            return response()->json(['message' => 'Solo un administrador puede asignar permisos.'], 403);
        }

        $rol = RolUsuario::find($id);
        if (!$rol) {
            return response()->json(['message' => 'Rol no encontrado'], 404);
        }
        if ($rol->codigo === 'admin') {
            return response()->json(['message' => 'El rol administrador tiene acceso total y no se configura.'], 422);
        }

        $validator = Validator::make($request->all(), [
            'id_permisos'   => 'present|array',
            'id_permisos.*' => 'integer|exists:permisos,id_permiso',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $validator->errors()], 422);
        }

        $rol->permisos()->sync($request->id_permisos);

        return response()->json([
            'message'     => 'Permisos actualizados',
            'id_permisos' => $rol->permisos()->pluck('permisos.id_permiso'),
        ]);
    }
}