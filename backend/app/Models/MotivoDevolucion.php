<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MotivoDevolucion extends Model
{
    protected $table = 'motivos_devolucion';
    protected $primaryKey = 'id_motivo';
    public $timestamps = false;

    protected $fillable = ['nombre', 'activo'];
    protected $casts = ['activo' => 'boolean'];
}