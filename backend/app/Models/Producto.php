<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    protected $table = 'productos';
    protected $primaryKey = 'id_producto';

    protected $fillable = [
        'id_proveedor', 'id_marca', 'id_linea',
        'cod_producto', 'codigo_proveedor', 'nombre_producto',
        'tam_producto', 'ref_producto',
        'precio_lista', 'impuesto', 'utilidad',
        'descuentopf', 'descuentopp', 'valor_descuento',
        'docena', 'val_min', 'estado',
    ];

    protected $casts = [
        'precio_lista'    => 'decimal:2',
        'impuesto'        => 'decimal:4',
        'utilidad'        => 'decimal:4',
        'descuentopf'     => 'decimal:4',
        'descuentopp'     => 'decimal:4',
        'valor_descuento' => 'decimal:2',
        'val_min'         => 'decimal:2',
        'estado'          => 'boolean',
    ];

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class, 'id_proveedor', 'id_proveedor');
    }

    public function marca()
    {
        return $this->belongsTo(Marca::class, 'id_marca', 'id_marcas');
    }

    public function linea()
    {
        return $this->belongsTo(Linea::class, 'id_linea', 'id_linea');
    }
    public function stocks()
    {
        return $this->hasMany(InventarioAlmacen::class, 'id_producto', 'id_producto');
    }

    public function stockEnAlmacen(int $idAlmacen): ?InventarioAlmacen
    {
        return $this->stocks()->where('id_almacen', $idAlmacen)->first();
    }

    // La relación stocks()/stockEnAlmacen() se agrega en la Fase de
    // Inventario, cuando exista la tabla inventario_almacen.
}