<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Illuminate\Support\Facades\DB;

class Usuario extends Authenticatable implements JWTSubject
{
    use Notifiable, SoftDeletes;

    protected $table = 'usuario';
    protected $primaryKey = 'id_usuario';

    protected $fillable = [
        'nom_usuario', 'contrasena', 'nombre', 'identificacion',
        'direccion', 'telefono', 'celular', 'correo', 'ciudad',
        'rol_user', 'estado',
    ];

    protected $hidden = ['contrasena', 'remember_token'];

    protected $casts = ['estado' => 'boolean'];

    public function getAuthPassword()
    {
        return $this->contrasena;
    }

    public function rol()
    {
        return $this->belongsTo(RolUsuario::class, 'rol_user', 'id_rol');
    }

    public function almacenes()
    {
        return $this->belongsToMany(Almacen::class, 'usuario_almacen', 'id_usuario', 'id_almacen');
    }

    public function almacenPrincipal(): ?int
    {
        if (!$this->relationLoaded('almacenes')) {
            $this->load('almacenes');
        }

        return $this->almacenes->first()?->id_almacen;
    }

    public function esAdmin(): bool
    {
        return $this->rol?->codigo === 'admin';
    }

    public function tienePermiso(string $codigoModulo, string $accion = 'ver'): bool
    {
        if ($this->esAdmin()) {
            return true;
        }

        if (!$this->relationLoaded('rol')) {
            $this->load('rol');
        }

        return (bool) $this->rol?->tienePermiso($codigoModulo, $accion);
    }

    public function permisosPorModulo(): array
{
    // Solo las acciones que realmente existen para cada módulo
    $catalogo = DB::table('permisos as p')
        ->join('modulos as m', 'm.id_modulo', '=', 'p.id_modulo')
        ->where('m.activo', true)
        ->get(['m.codigo', 'p.accion', 'p.id_permiso']);

    // El admin tiene todo; para los demás, un solo query con sus permisos
    $concedidos = $this->esAdmin()
        ? null
        : DB::table('rol_permiso')->where('id_rol', $this->rol_user)->pluck('id_permiso')->flip();

    $resultado = [];
    foreach ($catalogo as $fila) {
        $resultado[$fila->codigo][$fila->accion] =
            $concedidos === null || $concedidos->has((int) $fila->id_permiso);
    }

    return $resultado;
}
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return ['rol' => $this->rol_user];
    }
    public function almacenesPermitidos(): ?array
{
    if ($this->esAdmin()) {
        return null;
    }

    return array_map('intval', $this->almacenes()->pluck('almacenes.id_almacen')->all());
}

public function puedeOperarEn(int $idAlmacen): bool
{
    $ids = $this->almacenesPermitidos();
    return $ids === null || in_array($idAlmacen, $ids, true);
}
}