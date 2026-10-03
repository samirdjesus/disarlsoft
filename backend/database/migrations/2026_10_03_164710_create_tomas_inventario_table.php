<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tomas_inventario', function (Blueprint $table) {
            $table->id('id_toma');
            $table->foreignId('id_almacen')->constrained('almacenes', 'id_almacen');
            $table->string('periodo', 20)->nullable(); // ej. "2026-10" para agrupar tomas mensuales
            $table->timestamp('fecha_apertura');
            $table->timestamp('fecha_cierre')->nullable();
            $table->enum('estado', ['abierta', 'cerrada'])->default('abierta');
            $table->foreignId('id_usuario_apertura')->constrained('usuario', 'id_usuario');
            $table->foreignId('id_usuario_cierre')->nullable()->constrained('usuario', 'id_usuario');
            $table->string('observaciones', 300)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tomas_inventario');
    }
};