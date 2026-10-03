<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detalle_toma_inventario', function (Blueprint $table) {
            $table->id('id_detalle');
            $table->foreignId('id_toma')->constrained('tomas_inventario', 'id_toma')->cascadeOnDelete();
            $table->foreignId('id_producto')->constrained('productos', 'id_producto');
            $table->decimal('stock_sistema', 12, 2); // snapshot al abrir la toma
            $table->decimal('stock_contado', 12, 2)->nullable(); // lo que digita el bodeguero
            $table->decimal('diferencia', 12, 2)->nullable(); // contado - sistema
            $table->foreignId('id_usuario_conteo')->nullable()->constrained('usuario', 'id_usuario');
            $table->timestamp('fecha_conteo')->nullable();
            $table->string('observaciones', 200)->nullable();

            $table->unique(['id_toma', 'id_producto']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_toma_inventario');
    }
};