<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('linea', function (Blueprint $table) {
            $table->id('id_linea');
            $table->string('nombre_linea', 100)->unique();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('linea');
    }
};