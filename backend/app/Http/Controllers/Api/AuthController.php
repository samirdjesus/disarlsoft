<?php

namespace App\Http\Controllers\Api;

use App\Models\Almacen;
use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nom_usuario' => 'required|string',
            'contrasena'  => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $validator->errors()], 422);
        }

        $usuario = Usuario::with('rol')->where('nom_usuario', $request->nom_usuario)->first();

        if (!$usuario || !Hash::check($request->contrasena, $usuario->contrasena)) {
            return response()->json(['message' => 'Credenciales incorrectas'], 401);
        }

        if (!$usuario->estado) {
            return response()->json(['message' => 'Usuario inactivo o bloqueado'], 403);
        }

        $token = JWTAuth::fromUser($usuario);

        return response()->json([
            'token'      => $token,
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'usuario'    => $this->formatoUsuario($usuario),
        ]);
    }

    public function refresh()
    {
        try {
            $nuevoToken = auth('api')->refresh();
        } catch (JWTException $e) {
            return response()->json(['message' => 'Sesión expirada, vuelva a iniciar sesión'], 401);
        }

        return response()->json([
            'token'      => $nuevoToken,
            'expires_in' => auth('api')->factory()->getTTL() * 60,
        ]);
    }

    public function me(Request $request)
    {
        $usuario = Auth::guard('api')->user();

        if (!$usuario) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        $usuario->load('rol');
        return response()->json($this->formatoUsuario($usuario));
    }

    public function logout()
    {
        auth('api')->logout();
        return response()->json(['message' => 'Sesión cerrada']);
    }
private function formatoUsuario(Usuario $u): array
{
    // El admin opera en todos los almacenes activos; los demás, solo en los asignados
    $almacenes = $u->esAdmin()
        ? Almacen::where('activo', true)->orderBy('nombre')->get()
        : $u->almacenes()->where('almacenes.activo', true)->orderBy('almacenes.nombre')->get();

    return [
        'id_usuario'        => $u->id_usuario,
        'nom_usuario'       => $u->nom_usuario,
        'nombre'            => $u->nombre,
        'correo'            => $u->correo,
        'rol'               => $u->rol?->codigo,
        'rol_nombre'        => $u->rol?->nombre,
        'es_admin'          => $u->esAdmin(),
        'almacenes'         => $almacenes->map(fn ($a) => [
            'id_almacen' => $a->id_almacen,
            'nombre'     => $a->nombre,
        ])->values(),
        'almacen_principal' => $almacenes->first()?->id_almacen,
        'permisos'          => $u->permisosPorModulo(),
    ];
}
}