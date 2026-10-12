<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas_detalle', function (Blueprint $table) {
            $table->decimal('cantidad_devuelta', 12, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('ventas_detalle', function (Blueprint $table) {
            $table->dropColumn('cantidad_devuelta');
        });
    }
};