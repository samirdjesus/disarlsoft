<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modulos', function (Blueprint $table) {
            $table->id('id_modulo');
            $table->string('codigo', 60)->unique();
            $table->string('nombre', 100);
            $table->string('grupo', 60)->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modulos');
    }
};