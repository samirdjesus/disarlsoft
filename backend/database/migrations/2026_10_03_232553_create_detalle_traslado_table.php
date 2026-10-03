<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detalle_traslado', function (Blueprint $table) {
            $table->id('id_detalle');
            $table->foreignId('id_traslado')->constrained('traslados', 'id_traslado')->cascadeOnDelete();
            $table->foreignId('id_producto')->constrained('productos', 'id_producto');
            $table->string('nombre_producto', 150);
            $table->decimal('cantidad', 12, 2);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_traslado');
    }
};