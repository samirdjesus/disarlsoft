<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UsuarioController;
use App\Http\Controllers\Api\MarcaController;
use App\Http\Controllers\Api\LineaController;
use App\Http\Controllers\Api\ProveedorController;
use App\Http\Controllers\Api\ClienteController;
use App\Http\Controllers\Api\ProductoController;
use App\Http\Controllers\Api\AlmacenController;
use App\Http\Controllers\Api\InventarioController;
use App\Http\Controllers\Api\TomaInventarioController;
use App\Http\Controllers\Api\VentaController;
use App\Http\Controllers\Api\OrdenCompraController;
use App\Http\Controllers\Api\FacturaController;
use App\Http\Controllers\Api\NovedadController;
use App\Http\Controllers\Api\TrasladoController;
use App\Http\Controllers\Api\MotivoDevolucionController;
use App\Http\Controllers\Api\DevolucionProveedorController;
use App\Http\Controllers\Api\DevolucionClienteController;
use App\Http\Controllers\Api\EmbalajeController;
use App\Http\Controllers\Api\ReporteVentasController;
use App\Http\Controllers\Api\ReporteComprasController;
use App\Http\Controllers\Api\ReporteDevolucionesController;
use App\Http\Controllers\Api\ReporteTrasladosController;
use App\Http\Controllers\Api\RolController;

Route::post('auth/login', [AuthController::class, 'login']);


Route::middleware(['auth:api', 'almacen'])->group(function () {
    // ── Auth ─────────────────────────────────────────────
    Route::get('auth/me',      [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::post('auth/refresh', [AuthController::class, 'refresh']);

    // ── Usuarios ──────────────────────────────────────────
    Route::get('usuarios/roles',                 [UsuarioController::class, 'roles']);
    Route::get('usuarios/almacenes-disponibles', [UsuarioController::class, 'almacenesDisponibles']);

    Route::middleware('permiso:usuarios,ver')->get('usuarios', [UsuarioController::class, 'index']);
    Route::middleware('permiso:usuarios,ver')->get('usuarios/{id}', [UsuarioController::class, 'show']);
    Route::middleware('permiso:usuarios,crear')->post('usuarios', [UsuarioController::class, 'store']);
    Route::middleware('permiso:usuarios,editar')->put('usuarios/{id}', [UsuarioController::class, 'update']);
    Route::middleware('permiso:usuarios,eliminar')->delete('usuarios/{id}', [UsuarioController::class, 'destroy']);

    // ── Marcas ────────────────────────────────────────────
Route::middleware('permiso:marcas,ver')->get('marcas', [MarcaController::class, 'index']);
Route::middleware('permiso:marcas,crear')->post('marcas', [MarcaController::class, 'store']);
Route::middleware('permiso:marcas,editar')->put('marcas/{id}', [MarcaController::class, 'update']);
Route::middleware('permiso:marcas,eliminar')->delete('marcas/{id}', [MarcaController::class, 'destroy']);

// ── Líneas ────────────────────────────────────────────
Route::middleware('permiso:lineas,ver')->get('lineas', [LineaController::class, 'index']);
Route::middleware('permiso:lineas,crear')->post('lineas', [LineaController::class, 'store']);
Route::middleware('permiso:lineas,editar')->put('lineas/{id}', [LineaController::class, 'update']);
Route::middleware('permiso:lineas,eliminar')->delete('lineas/{id}', [LineaController::class, 'destroy']);

Route::middleware('permiso:proveedores,ver')->get('proveedores', [ProveedorController::class, 'index']);
Route::middleware('permiso:proveedores,ver')->get('proveedores/{id}', [ProveedorController::class, 'show']);
Route::middleware('permiso:proveedores,crear')->post('proveedores', [ProveedorController::class, 'store']);
Route::middleware('permiso:proveedores,editar')->put('proveedores/{id}', [ProveedorController::class, 'update']);
Route::middleware('permiso:proveedores,eliminar')->delete('proveedores/{id}', [ProveedorController::class, 'destroy']);

Route::middleware('permiso:clientes,ver')->get('clientes', [ClienteController::class, 'index']);
Route::middleware('permiso:clientes,ver')->get('clientes/{id}', [ClienteController::class, 'show']);
Route::middleware('permiso:clientes,crear')->post('clientes', [ClienteController::class, 'store']);
Route::middleware('permiso:clientes,editar')->put('clientes/{id}', [ClienteController::class, 'update']);
Route::middleware('permiso:clientes,eliminar')->delete('clientes/{id}', [ClienteController::class, 'destroy']);



Route::middleware('permiso:productos,ver')->get('productos/codigo/{cod}', [ProductoController::class, 'buscarCodigo']);
Route::middleware('permiso:productos,ver')->get('productos', [ProductoController::class, 'index']);
Route::middleware('permiso:productos,ver')->get('productos/{id}', [ProductoController::class, 'show']);
Route::middleware('permiso:productos,crear')->post('productos', [ProductoController::class, 'store']);
Route::middleware('permiso:productos,editar')->put('productos/{id}', [ProductoController::class, 'update']);
Route::middleware('permiso:productos,eliminar')->delete('productos/{id}', [ProductoController::class, 'destroy']);
Route::middleware('permiso:productos,editar')->patch('productos/{id}/reactivar', [ProductoController::class, 'reactivar']);



Route::middleware('permiso:almacenes,ver')->get('almacenes', [AlmacenController::class, 'index']);
Route::middleware('permiso:almacenes,crear')->post('almacenes', [AlmacenController::class, 'store']);
Route::middleware('permiso:almacenes,editar')->put('almacenes/{id}', [AlmacenController::class, 'update']);



Route::middleware('permiso:inventario.consulta,ver')->get('inventario/stock/{id_almacen}', [InventarioController::class, 'stockAlmacen']);
Route::middleware('permiso:inventario.consulta,ver')->get('inventario/alertas', [InventarioController::class, 'alertas']);
Route::middleware('permiso:inventario.movimientos,ver')->get('inventario/movimientos', [InventarioController::class, 'movimientos']);
Route::middleware('permiso:inventario.ajustes,crear')->post('inventario/stock-minimo', [InventarioController::class, 'actualizarStockMinimo']);
Route::middleware('permiso:inventario.ajustes,crear')->post('inventario/entrada', [InventarioController::class, 'entrada']);
Route::middleware('permiso:inventario.ajustes,crear')->post('inventario/salida', [InventarioController::class, 'salida']);
Route::middleware('permiso:inventario.ajustes,aprobar')->post('inventario/ajuste', [InventarioController::class, 'ajuste']);



Route::prefix('inventario/tomas')->middleware('permiso:inventario.tomas,ver')->group(function () {
    Route::get('/', [TomaInventarioController::class, 'index']);
    Route::get('/{id}', [TomaInventarioController::class, 'show']);
    Route::get('/{id}/detalles', [TomaInventarioController::class, 'detalles']);
    Route::get('/{id}/detalles/buscar/{cod}', [TomaInventarioController::class, 'buscarDetalle']);
    Route::get('/{id}/comparativo', [TomaInventarioController::class, 'comparativo']);
});

Route::prefix('inventario/tomas')->middleware('permiso:inventario.tomas,crear')->group(function () {
    Route::post('/', [TomaInventarioController::class, 'abrir']);
    Route::post('/{id}/conteo', [TomaInventarioController::class, 'registrarConteo']);
});

Route::prefix('inventario/tomas')->middleware('permiso:inventario.tomas,editar')->group(function () {
    Route::post('/{id}/cerrar', [TomaInventarioController::class, 'cerrar']);
});



Route::middleware('permiso:ventas,crear')->get('ventas/borradores', [VentaController::class, 'borradores']);
Route::middleware('permiso:ventas,ver')->get('ventas', [VentaController::class, 'index']);
Route::middleware('permiso:ventas,ver')->get('ventas/{id}', [VentaController::class, 'show']);
Route::middleware('permiso:ventas,crear')->post('ventas/borrador', [VentaController::class, 'guardarBorrador']);
Route::middleware('permiso:ventas,crear')->delete('ventas/borrador/{id}', [VentaController::class, 'eliminarBorrador']);
Route::middleware('permiso:ventas,crear')->post('ventas/cobrar', [VentaController::class, 'cobrar']);
Route::middleware('permiso:ventas,eliminar')->post('ventas/{id}/anular', [VentaController::class, 'anular']);



Route::middleware('permiso:compras.ordenes,ver')->get('proveedores/{id}/productos', [OrdenCompraController::class, 'productosPorProveedor']);
Route::middleware('permiso:compras.ordenes,ver')->get('ordenes-compra', [OrdenCompraController::class, 'index']);
Route::middleware('permiso:compras.ordenes,ver')->get('ordenes-compra/{id}', [OrdenCompraController::class, 'show']);
Route::middleware('permiso:compras.ordenes,ver')->get('ordenes-compra/{id}/pdf', [OrdenCompraController::class, 'pdf']);
Route::middleware('permiso:compras.ordenes,crear')->post('ordenes-compra', [OrdenCompraController::class, 'store']);
Route::middleware('permiso:compras.ordenes,eliminar')->post('ordenes-compra/{id}/anular', [OrdenCompraController::class, 'anular']);



Route::middleware('permiso:compras.facturas,ver')->get('facturas', [FacturaController::class, 'index']);
Route::middleware('permiso:compras.facturas,ver')->get('facturas/{id}', [FacturaController::class, 'show']);
Route::middleware('permiso:compras.facturas,crear')->post('facturas', [FacturaController::class, 'store']);
Route::middleware('permiso:compras.facturas,editar')->put('facturas/{id}', [FacturaController::class, 'update']);
Route::middleware('permiso:compras.facturas,eliminar')->delete('facturas/{id}', [FacturaController::class, 'destroy']);
Route::middleware('permiso:compras.facturas,crear')->post('facturas/{id}/detalles', [FacturaController::class, 'agregarDetalle']);
Route::middleware('permiso:compras.facturas,editar')->put('facturas/{id}/detalles/{idDetalle}', [FacturaController::class, 'actualizarDetalle']);
Route::middleware('permiso:compras.facturas,eliminar')->delete('facturas/{id}/detalles/{idDetalle}', [FacturaController::class, 'eliminarDetalle']);



Route::middleware('permiso:compras.comparacion,ver')->get('facturas/{id}/comparativo', [FacturaController::class, 'comparativo']);
Route::middleware('permiso:compras.comparacion,crear')->post('facturas/{id}/procesar', [FacturaController::class, 'procesar']);

Route::middleware('permiso:compras.novedades,ver')->get('novedades', [NovedadController::class, 'index']);
Route::middleware('permiso:compras.novedades,crear')->post('novedades/{id}/resolver', [NovedadController::class, 'resolver']);



Route::middleware('permiso:traslados,ver')->get('traslados', [TrasladoController::class, 'index']);
Route::middleware('permiso:traslados,ver')->get('traslados/{id}', [TrasladoController::class, 'show']);
Route::middleware('permiso:traslados,crear')->post('traslados', [TrasladoController::class, 'store']);



Route::middleware('permiso:devoluciones.proveedor,ver')->get('motivos-devolucion', [MotivoDevolucionController::class, 'index']);
Route::middleware('permiso:devoluciones.proveedor,crear')->post('motivos-devolucion', [MotivoDevolucionController::class, 'store']);
Route::middleware('permiso:devoluciones.proveedor,editar')->put('motivos-devolucion/{id}', [MotivoDevolucionController::class, 'update']);



Route::middleware('permiso:devoluciones.proveedor,ver')->get('devoluciones-proveedor', [DevolucionProveedorController::class, 'index']);
Route::middleware('permiso:devoluciones.proveedor,crear')->post('devoluciones-proveedor', [DevolucionProveedorController::class, 'store']);



Route::middleware('permiso:devoluciones.cliente,ver')->get('devoluciones-cliente', [DevolucionClienteController::class, 'index']);
Route::middleware('permiso:devoluciones.cliente,crear')->post('devoluciones-cliente', [DevolucionClienteController::class, 'store']);



Route::middleware('permiso:embalajes,ver')->get('embalajes', [EmbalajeController::class, 'index']);
Route::middleware('permiso:embalajes,crear')->post('embalajes', [EmbalajeController::class, 'store']);
Route::middleware('permiso:embalajes,editar')->put('embalajes/{id}', [EmbalajeController::class, 'update']);
Route::middleware('permiso:embalajes,crear')->post('embalajes/{id}/convertir', [EmbalajeController::class, 'convertir']);


// Informes de Ventas
Route::middleware('permiso:informes.ventas,ver')->prefix('reportes/ventas')->group(function () {
    Route::get('general', [ReporteVentasController::class, 'general']);
    Route::get('proveedor', [ReporteVentasController::class, 'porProveedor']);
    Route::get('marca', [ReporteVentasController::class, 'porMarca']);
    Route::get('linea', [ReporteVentasController::class, 'porLinea']);
    Route::get('codigo', [ReporteVentasController::class, 'porCodigo']);
    Route::get('vendedor', [ReporteVentasController::class, 'porVendedor']);
    Route::get('vendedor-codigo', [ReporteVentasController::class, 'porVendedorCodigo']);
});

// Informes de Compras
Route::middleware('permiso:informes.compras,ver')->prefix('reportes/compras')->group(function () {
    Route::get('general', [ReporteComprasController::class, 'general']);
    Route::get('proveedor', [ReporteComprasController::class, 'porProveedor']);
    Route::get('tipo', [ReporteComprasController::class, 'porTipoCompra']);
});

// Informes de Devoluciones
Route::middleware('permiso:informes.devoluciones,ver')->prefix('reportes/devoluciones')->group(function () {
    Route::get('proveedor', [ReporteDevolucionesController::class, 'proveedor']);
    Route::get('cliente', [ReporteDevolucionesController::class, 'cliente']);
});

// Informes de Traslados
Route::middleware('permiso:informes.traslados,ver')->get('reportes/traslados', [ReporteTrasladosController::class, 'general']);



Route::middleware('permiso:usuarios,ver')->get('roles', [RolController::class, 'index']);
Route::middleware('permiso:usuarios,ver')->get('roles/catalogo-permisos', [RolController::class, 'catalogoPermisos']);
Route::middleware('permiso:usuarios,ver')->get('roles/{id}', [RolController::class, 'show']);
Route::middleware('permiso:usuarios,crear')->post('roles', [RolController::class, 'store']);
Route::middleware('permiso:usuarios,editar')->put('roles/{id}', [RolController::class, 'update']);
Route::middleware('permiso:usuarios,eliminar')->delete('roles/{id}', [RolController::class, 'destroy']);
Route::middleware('permiso:usuarios,editar')->put('roles/{id}/permisos', [RolController::class, 'asignarPermisos']);

});