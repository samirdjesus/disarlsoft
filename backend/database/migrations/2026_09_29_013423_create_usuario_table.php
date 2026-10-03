<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuario', function (Blueprint $table) {
            $table->id('id_usuario');
            $table->string('nom_usuario', 60)->unique();
            $table->string('contrasena', 255);
            $table->string('nombre', 120);
            $table->string('identificacion', 20)->nullable()->unique();
            $table->string('correo', 120)->nullable()->unique();
            $table->string('telefono', 20)->nullable();
            $table->string('celular', 20)->nullable();
            $table->string('direccion', 200)->nullable();
            $table->string('ciudad', 80)->nullable();
            $table->foreignId('rol_user')->constrained('rolusuario', 'id_rol');
            $table->boolean('estado')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuario');
    }
};