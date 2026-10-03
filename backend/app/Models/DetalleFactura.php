<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetalleFactura extends Model
{
    protected $table = 'detalle_factura';
    protected $primaryKey = 'id_detalle';
    public $timestamps = false;

    protected $fillable = [
        'id_factura', 'id_producto', 'nombre_producto',
        'precio_compra', 'cantidad_facturada', 'total_linea', 'ingresado',
    ];

    protected $casts = ['ingresado' => 'boolean'];

    public function factura()
    {
        return $this->belongsTo(Factura::class, 'id_factura', 'id_factura');
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'id_producto', 'id_producto');
    }
}