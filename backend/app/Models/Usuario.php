<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

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
        $codigos = Modulo::where('activo', true)->pluck('codigo');
        $acciones = ['ver', 'crear', 'editar', 'eliminar', 'exportar', 'aprobar'];

        $resultado = [];
        foreach ($codigos as $codigo) {
            $resultado[$codigo] = [];
            foreach ($acciones as $accion) {
                $resultado[$codigo][$accion] = $this->tienePermiso($codigo, $accion);
            }
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
}