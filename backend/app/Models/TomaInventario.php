<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TomaInventario extends Model
{
    protected $table = 'tomas_inventario';
    protected $primaryKey = 'id_toma';

    protected $fillable = [
        'id_almacen', 'periodo', 'fecha_apertura', 'fecha_cierre',
        'estado', 'id_usuario_apertura', 'id_usuario_cierre', 'observaciones',
    ];

    protected $casts = [
        'fecha_apertura' => 'datetime',
        'fecha_cierre'   => 'datetime',
    ];

    public function almacen()
    {
        return $this->belongsTo(Almacen::class, 'id_almacen', 'id_almacen');
    }

    public function detalles()
    {
        return $this->hasMany(DetalleTomaInventario::class, 'id_toma', 'id_toma');
    }

    public function usuarioApertura()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_apertura', 'id_usuario');
    }

    public function usuarioCierre()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_cierre', 'id_usuario');
    }
}