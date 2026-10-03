<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detalle_factura', function (Blueprint $table) {
            $table->id('id_detalle');
            $table->foreignId('id_factura')->constrained('facturas', 'id_factura')->cascadeOnDelete();
            $table->foreignId('id_producto')->constrained('productos', 'id_producto');
            $table->string('nombre_producto', 150);
            $table->decimal('precio_compra', 12, 2);
            $table->decimal('cantidad_facturada', 12, 2);
            $table->decimal('total_linea', 12, 2);
            $table->boolean('ingresado')->default(false); // true una vez pasó a inventario_movimientos
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_factura');
    }
};