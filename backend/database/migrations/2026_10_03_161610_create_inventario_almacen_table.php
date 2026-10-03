<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventario_almacen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_almacen')->constrained('almacenes', 'id_almacen')->cascadeOnDelete();
            $table->foreignId('id_producto')->constrained('productos', 'id_producto')->cascadeOnDelete();
            $table->decimal('stock_actual', 12, 2)->default(0);
            $table->decimal('stock_minimo', 12, 2)->default(0);
            $table->timestamp('updated_at')->nullable();

            $table->unique(['id_almacen', 'id_producto']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_almacen');
    }
};