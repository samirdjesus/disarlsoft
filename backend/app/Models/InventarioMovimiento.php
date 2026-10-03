<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventarioMovimiento extends Model
{
    protected $table = 'inventario_movimientos';
    protected $primaryKey = 'id_movimiento';
    const UPDATED_AT = null;
    const CREATED_AT = 'created_at';

    protected $fillable = [
        'id_almacen', 'id_producto', 'tipo', 'cantidad',
        'stock_anterior', 'stock_nuevo', 'referencia', 'nota', 'id_usuario',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'id_producto', 'id_producto');
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