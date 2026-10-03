<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventas_cabecera', function (Blueprint $table) {
            $table->id('id_venta');
            $table->unsignedBigInteger('doc_venta')->nullable(); // null mientras es borrador
            $table->foreignId('id_almacen')->constrained('almacenes', 'id_almacen');
            $table->foreignId('id_vendedor')->constrained('usuario', 'id_usuario');
            $table->foreignId('id_cliente')->nullable()->constrained('clientes', 'id_cliente');
            $table->date('fecha_venta');
            $table->time('hora_venta')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('descuento_porcentaje', 6, 4)->default(0);
            $table->decimal('descuento_valor', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('pago', 12, 2)->nullable();
            $table->enum('estado', ['borrador', 'pagada', 'anulada'])->default('borrador');
            $table->timestamp('updated_at')->useCurrent();
            $table->softDeletes();

            $table->unique('doc_venta'); // único solo entre ventas pagadas, se maneja en el controlador
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas_cabecera');
    }
};
