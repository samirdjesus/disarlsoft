<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('almacenes', function (Blueprint $table) {
            $table->id('id_almacen');
            $table->string('nombre', 100);
            $table->string('descripcion', 200)->nullable();
            $table->boolean('activo')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('almacenes');
    }
};