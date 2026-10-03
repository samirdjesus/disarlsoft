<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetalleOrdenCompra extends Model
{
    protected $table = 'detalle_orden_compra';
    protected $primaryKey = 'id_detalle';
    public $timestamps = false;

    protected $fillable = [
        'id_orden', 'id_producto', 'nombre_producto',
        'precio_cotizado', 'cantidad_pedida', 'subtotal_linea', 'total_linea',
    ];

    public function orden()
    {
        return $this->belongsTo(OrdenCompra::class, 'id_orden', 'id_orden');
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'id_producto', 'id_producto');
    }
}