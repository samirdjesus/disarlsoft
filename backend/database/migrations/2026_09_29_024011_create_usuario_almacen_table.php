<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuario_almacen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_usuario')->constrained('usuario', 'id_usuario')->cascadeOnDelete();
            $table->foreignId('id_almacen')->constrained('almacenes', 'id_almacen')->cascadeOnDelete();
            $table->unique(['id_usuario', 'id_almacen']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuario_almacen');
    }
};