<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventarioAlmacen extends Model
{
    protected $table = 'inventario_almacen';
    const CREATED_AT = null;
    const UPDATED_AT = 'updated_at';

    protected $fillable = ['id_almacen', 'id_producto', 'stock_actual', 'stock_minimo'];

    protected $casts = [
        'stock_actual'  => 'decimal:2',
        'stock_minimo'  => 'decimal:2',
    ];

    protected $appends = ['bajo_minimo'];

    public function getBajoMinimoAttribute(): bool
    {
        return $this->stock_actual <= $this->stock_minimo;
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'id_producto', 'id_producto');
    }

    public function almacen()
    {
        return $this->belongsTo(Almacen::class, 'id_almacen', 'id_almacen');
    }
}