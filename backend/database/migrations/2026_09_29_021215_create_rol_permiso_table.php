<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rol_permiso', function (Blueprint $table) {
            $table->id('id_rol_permiso');
            $table->foreignId('id_rol')->constrained('rolusuario', 'id_rol')->cascadeOnDelete();
            $table->foreignId('id_permiso')->constrained('permisos', 'id_permiso')->cascadeOnDelete();
            $table->unique(['id_rol', 'id_permiso']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rol_permiso');
    }
};