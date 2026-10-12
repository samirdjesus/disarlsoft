<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devoluciones_proveedor', function (Blueprint $table) {
            $table->id('id_devolucion');
            $table->foreignId('id_proveedor')->constrained('proveedores', 'id_proveedor');
            $table->foreignId('id_almacen')->constrained('almacenes', 'id_almacen');
            $table->foreignId('id_producto')->constrained('productos', 'id_producto');
            $table->foreignId('id_motivo')->constrained('motivos_devolucion', 'id_motivo');
            $table->decimal('cantidad', 12, 2);
            $table->foreignId('id_usuario')->constrained('usuario', 'id_usuario');
            $table->date('fecha_devolucion');
            $table->string('observaciones', 300)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devoluciones_proveedor');
    }
};