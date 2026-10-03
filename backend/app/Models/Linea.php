<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Linea extends Model
{
    protected $table = 'linea';
    protected $primaryKey = 'id_linea';
    public $timestamps = false;

    protected $fillable = ['nombre_linea'];
}