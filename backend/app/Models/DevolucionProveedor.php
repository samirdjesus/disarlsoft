<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DevolucionProveedor extends Model
{
    protected $table = 'devoluciones_proveedor';
    protected $primaryKey = 'id_devolucion';

    protected $fillable = [
        'id_proveedor', 'id_almacen', 'id_producto', 'id_motivo',
        'cantidad', 'id_usuario', 'fecha_devolucion', 'observaciones',
    ];

    protected $casts = ['fecha_devolucion' => 'date'];

    public function proveedor() { return $this->belongsTo(Proveedor::class, 'id_proveedor', 'id_proveedor'); }
    public function almacen()   { return $this->belongsTo(Almacen::class, 'id_almacen', 'id_almacen'); }
    public function producto()  { return $this->belongsTo(Producto::class, 'id_producto', 'id_producto'); }
    public function motivo()    { return $this->belongsTo(MotivoDevolucion::class, 'id_motivo', 'id_motivo'); }
    public function usuario()   { return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario'); }
}
