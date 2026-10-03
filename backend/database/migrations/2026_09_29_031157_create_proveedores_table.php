<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proveedores', function (Blueprint $table) {
            $table->id('id_proveedor');
            $table->string('nombre_proveedor', 150);
            $table->string('nit', 30)->nullable()->unique();
            $table->string('contacto_nombre', 100)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('celular', 20)->nullable();
            $table->string('correo', 120)->nullable();
            $table->string('direccion', 200)->nullable();
            $table->string('ciudad', 80)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proveedores');
    }
};