<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventas_detalle', function (Blueprint $table) {
            $table->id('id_detalle');
            $table->foreignId('id_venta')->constrained('ventas_cabecera', 'id_venta')->cascadeOnDelete();
            $table->foreignId('id_producto')->constrained('productos', 'id_producto');

            // Snapshot completo al momento de la venta — independiente de
            // lo que diga 'productos' después. Esto es lo que confirmaste.
            $table->string('nombre_producto', 150);
            $table->decimal('precio_venta', 12, 2);
            $table->decimal('cantidad', 12, 2);
            $table->decimal('impuesto', 6, 4)->default(0);
            $table->decimal('iva_valor', 12, 2)->default(0);
            $table->decimal('descuento_valor', 12, 2)->default(0);
            $table->decimal('costo_unitario', 12, 2)->default(0); // de productos.precio_lista al momento de vender
            $table->decimal('utilidad_valor', 12, 2)->default(0);
            $table->decimal('total_linea', 12, 2);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas_detalle');
    }
};