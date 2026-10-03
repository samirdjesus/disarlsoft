<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Novedad extends Model
{
    protected $table = 'novedades';
    protected $primaryKey = 'id_novedad';

    protected $fillable = [
        'id_factura', 'id_orden', 'id_producto', 'tipo', 'cantidad',
        'estado', 'id_usuario_genera', 'id_usuario_resuelve', 'nota',
    ];

    public function factura()
    {
        return $this->belongsTo(Factura::class, 'id_factura', 'id_factura');
    }

    public function orden()
    {
        return $this->belongsTo(OrdenCompra::class, 'id_orden', 'id_orden');
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'id_producto', 'id_producto');
    }

    public function usuarioGenera()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_genera', 'id_usuario');
    }

    public function usuarioResuelve()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_resuelve', 'id_usuario');
    }
}