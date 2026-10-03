<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rolusuario', function (Blueprint $table) {
            $table->id('id_rol');
            // Código estable para lógica del sistema (ej. bypass de admin).
            // 'nombre' es lo que se muestra en pantalla y sí se puede editar
            // desde el frontend; 'codigo' nunca debería cambiar una vez creado.
            $table->string('codigo', 40)->unique();
            $table->string('nombre', 60);
            $table->string('descripcion', 200)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rolusuario');
    }
};