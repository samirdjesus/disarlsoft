<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('embalajes', function (Blueprint $table) {
            $table->id('id_embalaje');
            $table->foreignId('id_producto_padre')->constrained('productos', 'id_producto'); // ej. galón
            $table->foreignId('id_producto_hijo')->constrained('productos', 'id_producto');  // ej. unidad de 1 litro
            $table->decimal('factor_conversion', 10, 2); // cuántas unidades del hijo salen de 1 del padre
            $table->boolean('activo')->default(true);
            $table->timestamps();

            // Un mismo padre no puede tener dos relaciones activas hacia
            // el mismo hijo (evitaría ambigüedad sobre qué factor aplicar)
            $table->unique(['id_producto_padre', 'id_producto_hijo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('embalajes');
    }
};