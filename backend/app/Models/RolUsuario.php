<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RolUsuario extends Model
{
    protected $table = 'rolusuario';
    protected $primaryKey = 'id_rol';
    public $timestamps = false;

    protected $fillable = ['codigo', 'nombre', 'descripcion'];

    public function usuarios()
    {
        return $this->hasMany(Usuario::class, 'rol_user', 'id_rol');
    }

    public function permisos()
    {
        return $this->belongsToMany(Permiso::class, 'rol_permiso', 'id_rol', 'id_permiso');
    }

    public function tienePermiso(string $codigoModulo, string $accion = 'ver'): bool
    {
        return $this->permisos()
            ->whereHas('modulo', fn ($q) => $q->where('codigo', $codigoModulo))
            ->where('accion', $accion)
            ->exists();
    }
}