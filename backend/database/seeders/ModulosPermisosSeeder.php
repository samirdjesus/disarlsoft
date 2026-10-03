<?php

namespace Database\Seeders;

use App\Models\Modulo;
use App\Models\Permiso;
use App\Models\RolUsuario;
use Illuminate\Database\Seeder;

class ModulosPermisosSeeder extends Seeder
{
    // codigo => [grupo, nombre, acciones]
    private const MODULOS = [
        // Administración
        'usuarios'               => ['Administración', 'Usuarios', ['ver', 'crear', 'editar', 'eliminar']],
        'productos'               => ['Administración', 'Productos', ['ver', 'crear', 'editar', 'eliminar']],
        'marcas'                  => ['Administración', 'Marcas', ['ver', 'crear', 'editar', 'eliminar']],
        'lineas'                  => ['Administración', 'Líneas', ['ver', 'crear', 'editar', 'eliminar']],
        'proveedores'             => ['Administración', 'Proveedores', ['ver', 'crear', 'editar', 'eliminar']],
        'clientes'                => ['Administración', 'Clientes', ['ver', 'crear', 'editar', 'eliminar']],
        'almacenes'               => ['Administración', 'Almacenes', ['ver', 'crear', 'editar']],

        // Ventas
        'ventas'                  => ['Ventas', 'Ventas', ['ver', 'crear', 'editar', 'eliminar']],

        // Compras
        'compras.facturas'        => ['Compras', 'Facturas', ['ver', 'crear', 'editar', 'eliminar']],
        'compras.ordenes'         => ['Compras', 'Órdenes de compra', ['ver', 'crear', 'editar', 'eliminar']],
        'compras.comparacion'     => ['Compras', 'Comparación factura/orden', ['ver', 'crear']],
        'compras.novedades'       => ['Compras', 'Novedades', ['ver', 'crear', 'eliminar']],

        // Inventario
        'inventario.consulta'     => ['Inventario', 'Consulta de inventario', ['ver', 'exportar']],
        'inventario.movimientos'  => ['Inventario', 'Movimientos', ['ver']],
        'inventario.ajustes'      => ['Inventario', 'Ajustes', ['ver', 'crear', 'aprobar']],
        'inventario.tomas'        => ['Inventario', 'Tomas de inventario', ['ver', 'crear', 'editar']],

        // Traslados
        'traslados'               => ['Traslados', 'Traslados entre almacenes', ['ver', 'crear']],

        // Devoluciones
        'devoluciones.proveedor'  => ['Devoluciones', 'Devolución a proveedor', ['ver', 'crear']],
        'devoluciones.cliente'    => ['Devoluciones', 'Devolución de cliente', ['ver', 'crear']],

        // Embalajes
        'embalajes'               => ['Embalajes', 'Cambio de embalaje', ['ver', 'crear', 'editar']],

        // Informes
        'informes.ventas'         => ['Informes', 'Informes de ventas', ['ver', 'exportar']],
        'informes.compras'        => ['Informes', 'Informes de compras', ['ver', 'exportar']],
        'informes.traslados'      => ['Informes', 'Informes de traslados', ['ver', 'exportar']],
        'informes.devoluciones'   => ['Informes', 'Informes de devoluciones', ['ver', 'exportar']],
        'informes.inventario'     => ['Informes', 'Informes de inventario', ['ver', 'exportar']],
    ];

    public function run(): void
    {
        foreach (self::MODULOS as $codigo => [$grupo, $nombre, $acciones]) {
            $modulo = Modulo::updateOrCreate(
                ['codigo' => $codigo],
                ['nombre' => $nombre, 'grupo' => $grupo, 'activo' => true]
            );

            foreach ($acciones as $accion) {
                Permiso::firstOrCreate([
                    'id_modulo' => $modulo->id_modulo,
                    'accion'    => $accion,
                ]);
            }
        }

        // El rol admin recibe automáticamente TODOS los permisos existentes.
        $rolAdmin = RolUsuario::where('codigo', 'admin')->first();

        if ($rolAdmin) {
            $todosLosPermisos = Permiso::pluck('id_permiso');
            $rolAdmin->permisos()->syncWithoutDetaching($todosLosPermisos);
        }

        // Los demás roles (cajero, bodeguero, inventario, vendedor) se
        // configuran desde el módulo de Usuarios > Roles en el frontend,
        // asignando permisos puntuales — no se hardcodean aquí, porque
        // esa es justo la configurabilidad que pediste desde el inicio.
    }
}