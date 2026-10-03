<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Factura extends Model
{
    protected $table = 'facturas';
    protected $primaryKey = 'id_factura';

    protected $fillable = [
        'numero_factura', 'id_orden', 'id_proveedor', 'id_almacen', 'id_usuario',
        'fecha_factura', 'tipo_compra', 'subtotal', 'total', 'estado', 'observaciones',
    ];

    protected $casts = ['fecha_factura' => 'date'];

    public function detalles()
    {
        return $this->hasMany(DetalleFactura::class, 'id_factura', 'id_factura');
    }

    public function orden()
    {
        return $this->belongsTo(OrdenCompra::class, 'id_orden', 'id_orden');
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