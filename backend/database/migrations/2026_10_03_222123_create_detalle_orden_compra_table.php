<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detalle_orden_compra', function (Blueprint $table) {
            $table->id('id_detalle');
            $table->foreignId('id_orden')->constrained('ordenes_compra', 'id_orden')->cascadeOnDelete();
            $table->foreignId('id_producto')->constrained('productos', 'id_producto');
            $table->string('nombre_producto', 150); // snapshot — igual que en Ventas
            $table->decimal('precio_cotizado', 12, 2);
            $table->decimal('cantidad_pedida', 12, 2);
            $table->decimal('subtotal_linea', 12, 2);
            $table->decimal('total_linea', 12, 2);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_orden_compra');
    }
};