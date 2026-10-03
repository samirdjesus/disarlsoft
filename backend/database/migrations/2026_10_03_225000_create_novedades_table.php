<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('novedades', function (Blueprint $table) {
            $table->id('id_novedad');
            $table->foreignId('id_factura')->constrained('facturas', 'id_factura');
            $table->foreignId('id_orden')->nullable()->constrained('ordenes_compra', 'id_orden');
            $table->foreignId('id_producto')->constrained('productos', 'id_producto');
            $table->enum('tipo', ['sobrante', 'faltante']);
            $table->decimal('cantidad', 12, 2);
            $table->enum('estado', ['pendiente', 'ingresada', 'descartada'])->default('pendiente');
            $table->foreignId('id_usuario_genera')->constrained('usuario', 'id_usuario');
            $table->foreignId('id_usuario_resuelve')->nullable()->constrained('usuario', 'id_usuario');
            $table->string('nota', 300)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('novedades');
    }
};