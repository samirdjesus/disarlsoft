<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devoluciones_cliente', function (Blueprint $table) {
            $table->id('id_devolucion');
            $table->foreignId('id_venta')->constrained('ventas_cabecera', 'id_venta');
            $table->foreignId('id_venta_detalle')->constrained('ventas_detalle', 'id_detalle');
            $table->foreignId('id_producto')->constrained('productos', 'id_producto'); // el devuelto
            $table->foreignId('id_almacen')->constrained('almacenes', 'id_almacen');
            $table->decimal('cantidad_devuelta', 12, 2);
            $table->decimal('valor_devuelto', 12, 2); // = cantidad × precio real pagado (snapshot)
            $table->enum('tipo_compensacion', ['cambio', 'dinero', 'combinado']);

            // Solo si hay cambio de producto:
            $table->foreignId('id_producto_cambio')->nullable()->constrained('productos', 'id_producto');
            $table->decimal('cantidad_cambio', 12, 2)->nullable();
            $table->decimal('valor_producto_cambio', 12, 2)->nullable();

            // Dinero entregado al cliente (positivo) o diferencia por combinado:
            $table->decimal('valor_dinero_entregado', 12, 2)->default(0);

            $table->foreignId('id_usuario')->constrained('usuario', 'id_usuario');
            $table->date('fecha_devolucion');
            $table->string('observaciones', 300)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devoluciones_cliente');
    }
};