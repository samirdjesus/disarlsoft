<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Modulo extends Model
{
    protected $table = 'modulos';
    protected $primaryKey = 'id_modulo';
    public $timestamps = false;

    protected $fillable = ['codigo', 'nombre', 'grupo', 'orden', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function permisos()
    {
        return $this->hasMany(Permiso::class, 'id_modulo', 'id_modulo');
    }
}