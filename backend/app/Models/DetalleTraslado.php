<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetalleTraslado extends Model
{
    protected $table = 'detalle_traslado';
    protected $primaryKey = 'id_detalle';
    public $timestamps = false;

    protected $fillable = ['id_traslado', 'id_producto', 'nombre_producto', 'cantidad'];

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'id_producto', 'id_producto');
    }
}