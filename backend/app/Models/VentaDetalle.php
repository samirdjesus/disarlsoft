<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VentaDetalle extends Model
{
    protected $table = 'ventas_detalle';
    protected $primaryKey = 'id_detalle';
    public $timestamps = false;

    protected $fillable = [
        'id_venta', 'id_producto', 'nombre_producto', 'precio_venta',
        'cantidad', 'impuesto', 'iva_valor', 'descuento_valor',
        'costo_unitario', 'utilidad_valor', 'total_linea',
    ];

    public function cabecera()
    {
        return $this->belongsTo(VentaCabecera::class, 'id_venta', 'id_venta');
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'id_producto', 'id_producto');
    }
}