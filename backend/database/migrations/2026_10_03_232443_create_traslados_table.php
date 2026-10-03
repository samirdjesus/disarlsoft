<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('traslados', function (Blueprint $table) {
            $table->id('id_traslado');
            $table->foreignId('id_almacen_origen')->constrained('almacenes', 'id_almacen');
            $table->foreignId('id_almacen_destino')->constrained('almacenes', 'id_almacen');
            $table->foreignId('id_usuario')->constrained('usuario', 'id_usuario'); // responsable, del token
            $table->date('fecha_traslado');
            $table->string('observaciones', 300)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('traslados');
    }
};