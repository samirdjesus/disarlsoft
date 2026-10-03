<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Almacen;
use App\Models\OrdenCompra;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Barryvdh\DomPDF\Facade\Pdf;

class OrdenCompraController extends Controller
{
    // Genera OC-{ALMACEN}-{YYYYMMDD}-{consecutivo}, reiniciado por
    // almacén y por día. lockForUpdate evita que dos personas generen
    // el mismo número si cotizan al mismo tiempo.
    private function generarNumeroOrden(int $idAlmacen): string
    {
        $almacen = Almacen::findOrFail($idAlmacen);
        $codigo = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $almacen->nombre) ?: 'ALM', 0, 3));
        $fecha = now()->format('Ymd');
        $prefijo = "OC-{$codigo}-{$fecha}-";

        return DB::transaction(function () use ($prefijo, $idAlmacen) {
            $ultimo = OrdenCompra::where('id_almacen', $idAlmacen)
                ->where('numero_orden', 'like', "{$prefijo}%")
                ->lockForUpdate()
                ->orderByDesc('numero_orden')
                ->first();

            $siguiente = $ultimo ? ((int) substr($ultimo->numero_orden, -4)) + 1 : 1;

            return $prefijo . str_pad((string) $siguiente, 4, '0', STR_PAD_LEFT);
        });
    }

    // GET /proveedores/{id}/productos?id_almacen=X — para armar el carrito de la OC
    public function productosPorProveedor(Request $request, int $idProveedor): JsonResponse
    {
        $idAlmacen = (int) $request->query('id_almacen', 0);

        $productos = Producto::with('marca')
            ->where('id_proveedor', $idProveedor)
            ->where('estado', true)
            ->get()
            ->map(function (Producto $p) use ($idAlmacen) {
                $stock = $idAlmacen ? (optional($p->stockEnAlmacen($idAlmacen))->stock_actual ?? 0) : 0;

                return [
                    'id_producto'     => $p->id_producto,
                    'cod_producto'    => $p->cod_producto,
                    'nombre_producto' => $p->nombre_producto,
                    'tam_producto'    => $p->tam_producto,
                    'marca'           => $p->marca?->nombre_marca,
                    'precio_lista'    => (float) $p->precio_lista,
                    'stock_actual'    => (float) $stock,
                ];
            })
            ->values();

        return response()->json($productos);
    }

    public function index(Request $request): JsonResponse
    {
        $query = OrdenCompra::with(['proveedor', 'almacen', 'usuario']);

        if ($request->filled('id_almacen'))   $query->where('id_almacen', $request->id_almacen);
        if ($request->filled('id_proveedor')) $query->where('id_proveedor', $request->id_proveedor);
        if ($request->filled('estado'))       $query->where('estado', $request->estado);

        return response()->json($query->orderByDesc('id_orden')->paginate($request->get('per_page', 20)));
    }

    public function show(int $id): JsonResponse
    {
        $orden = OrdenCompra::with(['detalles.producto', 'proveedor', 'almacen', 'usuario'])->find($id);
        if (!$orden) {
            return response()->json(['message' => 'Orden no encontrada'], 404);
        }
        return response()->json($orden);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_almacen'                  => 'required|integer|exists:almacenes,id_almacen',
            'id_proveedor'                => 'required|integer|exists:proveedores,id_proveedor',
            'observaciones'               => 'nullable|string|max:300',
            'detalles'                    => 'required|array|min:1',
            'detalles.*.id_producto'      => 'required|integer|exists:productos,id_producto',
            'detalles.*.precio_cotizado'  => 'required|numeric|min:0',
            'detalles.*.cantidad_pedida'  => 'required|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $validator->errors()], 422);
        }

        $orden = DB::transaction(function () use ($request) {
            $numeroOrden = $this->generarNumeroOrden($request->id_almacen);

            $subtotal = 0;
            $lineas = [];

            foreach ($request->detalles as $d) {
                $producto = Producto::find($d['id_producto']);
                $totalLinea = round($d['precio_cotizado'] * $d['cantidad_pedida'], 2);
                $subtotal += $totalLinea;

                $lineas[] = [
                    'id_producto'      => $d['id_producto'],
                    'nombre_producto'  => $producto->nombre_producto,
                    'precio_cotizado'  => $d['precio_cotizado'],
                    'cantidad_pedida'  => $d['cantidad_pedida'],
                    'subtotal_linea'   => $totalLinea,
                    'total_linea'      => $totalLinea,
                ];
            }

            $orden = OrdenCompra::create([
                'numero_orden'  => $numeroOrden,
                'id_almacen'    => $request->id_almacen,
                'id_proveedor'  => $request->id_proveedor,
                'id_usuario'    => $request->user()->id_usuario, // nunca del body
                'fecha_orden'   => now()->toDateString(),
                'subtotal'      => round($subtotal, 2),
                'total'         => round($subtotal, 2),
                'estado'        => 'abierta',
                'observaciones' => $request->observaciones,
            ]);

            $orden->detalles()->createMany($lineas);

            return $orden;
        });

        return response()->json($orden->load(['detalles', 'proveedor', 'almacen']), 201);
    }

    public function anular(int $id): JsonResponse
    {
        $orden = OrdenCompra::find($id);
        if (!$orden || $orden->estado !== 'abierta') {
            return response()->json(['message' => 'Orden no encontrada o no está abierta.'], 422);
        }

        $orden->update(['estado' => 'anulada']);
        return response()->json(['message' => 'Orden anulada correctamente']);
    }

    public function pdf(int $id)
    {
    $orden = OrdenCompra::with(['detalles', 'proveedor', 'almacen', 'usuario'])->findOrFail($id);
    $pdf = Pdf::loadView('pdf.orden_compra', ['orden' => $orden]);
    return $pdf->stream("{$orden->numero_orden}.pdf");
    }
}