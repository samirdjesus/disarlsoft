<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Proveedor extends Model
{
    protected $table = 'proveedores';
    protected $primaryKey = 'id_proveedor';

    protected $fillable = [
        'nombre_proveedor', 'nit', 'contacto_nombre',
        'telefono', 'celular', 'correo', 'direccion', 'ciudad', 'activo',
    ];

    protected $casts = ['activo' => 'boolean'];
}