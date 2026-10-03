<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Traslado extends Model
{
    protected $table = 'traslados';
    protected $primaryKey = 'id_traslado';

    protected $fillable = [
        'id_almacen_origen', 'id_almacen_destino', 'id_usuario',
        'fecha_traslado', 'observaciones',
    ];

    protected $casts = ['fecha_traslado' => 'date'];

    public function detalles()
    {
        return $this->hasMany(DetalleTraslado::class, 'id_traslado', 'id_traslado');
    }

    public function almacenOrigen()
    {
        return $this->belongsTo(Almacen::class, 'id_almacen_origen', 'id_almacen');
    }

    public function almacenDestino()
    {
        return $this->belongsTo(Almacen::class, 'id_almacen_destino', 'id_almacen');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }
}