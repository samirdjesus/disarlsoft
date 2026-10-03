<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marcas', function (Blueprint $table) {
            $table->id('id_marcas');
            $table->string('nombre_marca', 100)->unique();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marcas');
    }
};