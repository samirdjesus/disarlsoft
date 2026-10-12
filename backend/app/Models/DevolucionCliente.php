<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DevolucionCliente extends Model
{
    protected $table = 'devoluciones_cliente';
    protected $primaryKey = 'id_devolucion';

    protected $fillable = [
        'id_venta', 'id_venta_detalle', 'id_producto', 'id_almacen',
        'cantidad_devuelta', 'valor_devuelto', 'tipo_compensacion',
        'id_producto_cambio', 'cantidad_cambio', 'valor_producto_cambio',
        'valor_dinero_entregado', 'id_usuario', 'fecha_devolucion', 'observaciones',
    ];

    protected $casts = ['fecha_devolucion' => 'date'];

    public function venta()          { return $this->belongsTo(VentaCabecera::class, 'id_venta', 'id_venta'); }
    public function ventaDetalle()   { return $this->belongsTo(VentaDetalle::class, 'id_venta_detalle', 'id_detalle'); }
    public function producto()       { return $this->belongsTo(Producto::class, 'id_producto', 'id_producto'); }
    public function productoCambio() { return $this->belongsTo(Producto::class, 'id_producto_cambio', 'id_producto'); }
    public function usuario()        { return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario'); }
}