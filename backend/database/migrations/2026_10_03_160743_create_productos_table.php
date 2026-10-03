<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id('id_producto');

            $table->foreignId('id_proveedor')->constrained('proveedores', 'id_proveedor');
            $table->foreignId('id_marca')->nullable()->constrained('marcas', 'id_marcas');
            $table->foreignId('id_linea')->nullable()->constrained('linea', 'id_linea');

            $table->string('cod_producto', 50)->unique(); // código de barras / código interno
            $table->string('codigo_proveedor', 50)->nullable(); // código propio del proveedor, de referencia
            $table->string('nombre_producto', 150);
            $table->string('tam_producto', 50)->nullable();  // presentación: "500ml", "1kg"...
            $table->string('ref_producto', 50)->nullable();

            // Parámetros de precio — todos asignados manualmente por el administrador
            $table->decimal('precio_lista', 12, 2);                 // precio de lista del proveedor
            $table->decimal('impuesto', 6, 4)->default(0);          // IVA, ej. 0.19
            $table->decimal('utilidad', 6, 4)->default(0);          // % de ganancia definido por el admin
            $table->decimal('descuentopf', 6, 4)->default(0);       // % descuento pie de factura
            $table->decimal('descuentopp', 6, 4)->default(0);       // % descuento pronto pago
            $table->decimal('valor_descuento', 12, 2)->default(0);  // descuento adicional en valor fijo
            $table->smallInteger('docena')->default(12);            // unidad de referencia de compra (12 salvo promoción)
            $table->decimal('val_min', 12, 2)->nullable();          // precio mínimo de venta permitido

            $table->boolean('estado')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};