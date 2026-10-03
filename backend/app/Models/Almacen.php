<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Almacen extends Model
{
    protected $table = 'almacenes';
    protected $primaryKey = 'id_almacen';
    public $timestamps = false;

    protected $fillable = ['nombre', 'descripcion', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
    ];
    public function usuarios()
{
    return $this->belongsToMany(Usuario::class, 'usuario_almacen', 'id_almacen', 'id_usuario');
}

public function stocks()
{
    return $this->hasMany(InventarioAlmacen::class, 'id_almacen', 'id_almacen');
}

public function movimientos()
{
    return $this->hasMany(InventarioMovimiento::class, 'id_almacen', 'id_almacen');
}
}