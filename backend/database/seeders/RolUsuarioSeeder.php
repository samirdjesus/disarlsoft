<?php

namespace Database\Seeders;

use App\Models\RolUsuario;
use Illuminate\Database\Seeder;

class RolUsuarioSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['codigo' => 'admin',      'nombre' => 'Administrador'],
            ['codigo' => 'cajero',     'nombre' => 'Cajero'],
            ['codigo' => 'bodeguero',  'nombre' => 'Bodeguero'],
            ['codigo' => 'inventario', 'nombre' => 'Encargado de Inventario'],
            ['codigo' => 'vendedor',   'nombre' => 'Vendedor'],
        ];

        foreach ($roles as $rol) {
            RolUsuario::updateOrCreate(['codigo' => $rol['codigo']], $rol);
        }
    }
}