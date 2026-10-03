<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permisos', function (Blueprint $table) {
            $table->id('id_permiso');
            $table->foreignId('id_modulo')->constrained('modulos', 'id_modulo')->cascadeOnDelete();
            $table->enum('accion', ['ver', 'crear', 'editar', 'eliminar', 'exportar', 'aprobar']);
            $table->unique(['id_modulo', 'accion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permisos');
    }
};