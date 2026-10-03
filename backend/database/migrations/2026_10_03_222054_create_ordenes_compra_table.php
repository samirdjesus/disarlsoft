<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ordenes_compra', function (Blueprint $table) {
            $table->id('id_orden');
            $table->string('numero_orden', 40)->unique(); // ej. OC-ALM-20261003-0001
            $table->foreignId('id_almacen')->constrained('almacenes', 'id_almacen');
            $table->foreignId('id_proveedor')->constrained('proveedores', 'id_proveedor');
            $table->foreignId('id_usuario')->constrained('usuario', 'id_usuario'); // quien la generó, del token, nunca del body
            $table->date('fecha_orden');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->enum('estado', ['abierta', 'cerrada', 'anulada'])->default('abierta');
            $table->string('observaciones', 300)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordenes_compra');
    }
};