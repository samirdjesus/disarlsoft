<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Embalaje extends Model
{
    protected $table = 'embalajes';
    protected $primaryKey = 'id_embalaje';

    protected $fillable = ['id_producto_padre', 'id_producto_hijo', 'factor_conversion', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function productoPadre()
    {
        return $this->belongsTo(Producto::class, 'id_producto_padre', 'id_producto');
    }

    public function productoHijo()
    {
        return $this->belongsTo(Producto::class, 'id_producto_hijo', 'id_producto');
    }
}