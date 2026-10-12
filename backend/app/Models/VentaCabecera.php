<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VentaCabecera extends Model
{
    use SoftDeletes;

    protected $table = 'ventas_cabecera';
    protected $primaryKey = 'id_venta';
    const CREATED_AT = null;
    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'doc_venta', 'id_almacen', 'id_vendedor', 'id_cliente',
        'fecha_venta', 'hora_venta', 'subtotal',
        'descuento_porcentaje', 'descuento_valor', 'total', 'pago', 'estado',
    ];

    protected $casts = [
        'fecha_venta' => 'date',
    ];

    public function detalles()
    {
        return $this->hasMany(VentaDetalle::class, 'id_venta', 'id_venta');
    }

    public function almacen()
    {
        return $this->belongsTo(Almacen::class, 'id_almacen', 'id_almacen');
    }

    public function vendedor()
    {
        return $this->belongsTo(Usuario::class, 'id_vendedor', 'id_usuario');
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'id_cliente', 'id_cliente');
    }
    public function devoluciones()
    {
        return $this->hasMany(DevolucionCliente::class, 'id_venta', 'id_venta');
    }
}