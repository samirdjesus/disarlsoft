<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facturas', function (Blueprint $table) {
            $table->id('id_factura');
            $table->string('numero_factura', 60); // el número que trae la factura física del proveedor
            $table->foreignId('id_orden')->nullable()->constrained('ordenes_compra', 'id_orden');
            $table->foreignId('id_proveedor')->constrained('proveedores', 'id_proveedor');
            $table->foreignId('id_almacen')->constrained('almacenes', 'id_almacen');
            $table->foreignId('id_usuario')->constrained('usuario', 'id_usuario');
            $table->date('fecha_factura');
            $table->enum('tipo_compra', ['con_orden', 'directa']);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->enum('estado', ['registrada', 'comparada', 'procesada', 'anulada'])->default('registrada');
            $table->string('observaciones', 300)->nullable();
            $table->timestamps();

            // El mismo proveedor no puede repetir número de factura —
            // evita cargar la misma factura física dos veces por error.
            $table->unique(['id_proveedor', 'numero_factura']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facturas');
    }
};