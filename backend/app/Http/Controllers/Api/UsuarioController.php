<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Almacen;
use App\Models\RolUsuario;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UsuarioController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Usuario::with(['rol', 'almacenes']);

        if ($request->filled('rol_user')) {
            $query->where('rol_user', $request->rol_user);
        }
        if ($request->filled('nombre')) {
            $query->where('nombre', 'ilike', '%' . $request->nombre . '%');
        }

        return response()->json(
            $query->orderBy('nombre')->paginate($request->get('per_page', 20))
        );
    }

    public function show(int $id): JsonResponse
    {
        $usuario = Usuario::with(['rol', 'almacenes'])->find($id);

        if (!$usuario) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        return response()->json($usuario);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nom_usuario'    => 'required|string|max:60|unique:usuario,nom_usuario',
            'contrasena'     => 'required|string|min:6',
            'nombre'         => 'required|string|max:120',
            'identificacion' => 'nullable|string|max:20|unique:usuario,identificacion',
            'correo'         => 'nullable|email|max:120|unique:usuario,correo',
            'telefono'       => 'nullable|string|max:20',
            'celular'        => 'nullable|string|max:20',
            'direccion'      => 'nullable|string|max:200',
            'ciudad'         => 'nullable|string|max:80',
            'rol_user'       => 'required|integer|exists:rolusuario,id_rol',
            'id_almacenes'   => 'required|array|min:1',
            'id_almacenes.*' => 'integer|exists:almacenes,id_almacen',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $validator->errors()], 422);
        }

        $usuario = DB::transaction(function () use ($request) {
            $nuevo = Usuario::create([
                ...collect($request->validated ?? $request->all())->except('id_almacenes', 'contrasena')->all(),
                'nom_usuario'    => $request->nom_usuario,
                'nombre'         => $request->nombre,
                'identificacion' => $request->identificacion,
                'correo'         => $request->correo,
                'telefono'       => $request->telefono,
                'celular'        => $request->celular,
                'direccion'      => $request->direccion,
                'ciudad'         => $request->ciudad,
                'rol_user'       => $request->rol_user,
                'contrasena'     => Hash::make($request->contrasena),
                'estado'         => true,
            ]);

            $nuevo->almacenes()->sync($request->id_almacenes);

            return $nuevo;
        });

        $usuario->load(['rol', 'almacenes']);

        return response()->json([
            'message' => 'Usuario creado correctamente',
            'usuario' => $usuario,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $usuario = Usuario::find($id);

        if (!$usuario) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'nombre'         => 'sometimes|string|max:120',
            'identificacion' => 'nullable|string|max:20|unique:usuario,identificacion,' . $id . ',id_usuario',
            'correo'         => 'nullable|email|max:120|unique:usuario,correo,' . $id . ',id_usuario',
            'telefono'       => 'nullable|string|max:20',
            'celular'        => 'nullable|string|max:20',
            'direccion'      => 'nullable|string|max:200',
            'ciudad'         => 'nullable|string|max:80',
            'rol_user'       => 'sometimes|integer|exists:rolusuario,id_rol',
            'estado'         => 'sometimes|boolean',
            'contrasena'     => 'nullable|string|min:6',
            'id_almacenes'   => 'sometimes|array|min:1',
            'id_almacenes.*' => 'integer|exists:almacenes,id_almacen',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $validator->errors()], 422);
        }

        DB::transaction(function () use ($request, $usuario) {
            $datos = $request->except(['id_almacenes', 'contrasena']);

            if ($request->filled('contrasena')) {
                $datos['contrasena'] = Hash::make($request->contrasena);
            }

            $usuario->update($datos);

            if ($request->has('id_almacenes')) {
                $usuario->almacenes()->sync($request->id_almacenes);
            }
        });

        return response()->json($usuario->fresh(['rol', 'almacenes']));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $usuario = Usuario::find($id);

        if (!$usuario) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        // Nadie puede desactivarse/eliminarse a sí mismo desde este endpoint
        // (evita quedarse sin admins activos por accidente).
        if ($request->user()->id_usuario === $usuario->id_usuario) {
            return response()->json(['message' => 'No puedes eliminar tu propio usuario.'], 422);
        }

        $usuario->update(['estado' => false]);
        $usuario->delete(); // soft delete — el registro queda, solo se marca deleted_at

        return response()->json(['message' => 'Usuario eliminado correctamente']);
    }

    // ─── Catálogos auxiliares para el frontend ─────────────────────
    public function roles(): JsonResponse
    {
        return response()->json(RolUsuario::orderBy('nombre')->get());
    }

    public function almacenesDisponibles(): JsonResponse
    {
        return response()->json(Almacen::where('activo', true)->orderBy('nombre')->get());
    }
}