<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventario_movimientos', function (Blueprint $table) {
            $table->id('id_movimiento');
            $table->foreignId('id_almacen')->constrained('almacenes', 'id_almacen');
            $table->foreignId('id_producto')->constrained('productos', 'id_producto');
            $table->enum('tipo', ['entrada', 'salida', 'traslado_entrada', 'traslado_salida', 'ajuste_positivo', 'ajuste_negativo']);
            $table->decimal('cantidad', 12, 2);
            $table->decimal('stock_anterior', 12, 2);
            $table->decimal('stock_nuevo', 12, 2);
            $table->string('referencia', 60)->nullable(); // ej. "V-123", "OC-DIS-20260101-0001"
            $table->string('nota', 200)->nullable();
            $table->foreignId('id_usuario')->constrained('usuario', 'id_usuario');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_movimientos');
    }
};