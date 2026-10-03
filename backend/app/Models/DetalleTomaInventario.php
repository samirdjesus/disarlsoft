<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetalleTomaInventario extends Model
{
    protected $table = 'detalle_toma_inventario';
    protected $primaryKey = 'id_detalle';
    public $timestamps = false;

    protected $fillable = [
        'id_toma', 'id_producto', 'stock_sistema', 'stock_contado',
        'diferencia', 'id_usuario_conteo', 'fecha_conteo', 'observaciones',
    ];

    protected $casts = [
        'stock_sistema' => 'decimal:2',
        'stock_contado' => 'decimal:2',
        'diferencia'    => 'decimal:2',
        'fecha_conteo'  => 'datetime',
    ];

    public function toma()
    {
        return $this->belongsTo(TomaInventario::class, 'id_toma', 'id_toma');
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'id_producto', 'id_producto');
    }

    public function usuarioConteo()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_conteo', 'id_usuario');
    }
}