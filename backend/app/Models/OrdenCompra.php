<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrdenCompra extends Model
{
    protected $table = 'ordenes_compra';
    protected $primaryKey = 'id_orden';

    protected $fillable = [
        'numero_orden', 'id_almacen', 'id_proveedor', 'id_usuario',
        'fecha_orden', 'subtotal', 'total', 'estado', 'observaciones',
    ];

    protected $casts = ['fecha_orden' => 'date'];

    public function detalles()
    {
        return $this->hasMany(DetalleOrdenCompra::class, 'id_orden', 'id_orden');
    }

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class, 'id_proveedor', 'id_proveedor');
    }

    public function almacen()
    {
        return $this->belongsTo(Almacen::class, 'id_almacen', 'id_almacen');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }
}