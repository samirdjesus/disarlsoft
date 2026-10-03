<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permiso extends Model
{
    protected $table = 'permisos';
    protected $primaryKey = 'id_permiso';
    public $timestamps = false;

    protected $fillable = ['id_modulo', 'accion'];

    public function modulo()
    {
        return $this->belongsTo(Modulo::class, 'id_modulo', 'id_modulo');
    }

    public function roles()
    {
        return $this->belongsToMany(RolUsuario::class, 'rol_permiso', 'id_permiso', 'id_rol');
    }
}