<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'categoria_producto_id' => $this->categoria_producto_id,
            'proveedor_id' => $this->proveedor_id,
            'nombre' => $this->nombre,
            'marca' => $this->marca,
            'tono' => $this->tono,
            'descripcion' => $this->descripcion,
            'precio_costo' => $this->precio_costo,
            'precio_venta' => $this->precio_venta,
            'precio_sugerido' => $this->precio_sugerido,
            'stock' => $this->stock,
            'stock_minimo' => $this->stock_minimo,
            'unidad_control' => $this->unidad_control,
            'contenido_envase' => $this->contenido_envase,
            'presentacion' => $this->presentacion,
            'presentacion_label' => $this->presentacionLabel(),
            'unidad_label' => $this->unidadLabel(),
            'unidad_abrev' => $this->unidadAbrev(),
            'costo_envase' => $this->costo_envase,
            'costo_unitario' => $this->costo_unitario,
            'fecha_compra' => $this->fecha_compra?->toDateString(),
            'fecha_vencimiento' => $this->fecha_vencimiento?->toDateString(),
            'activo' => (bool) $this->activo,
            'pendiente_confirmacion' => (bool) $this->pendiente_confirmacion,
            'esta_agotado' => $this->estaAgotado(),
            'tiene_stock_bajo' => $this->tieneStockBajo(),
            'categoria' => new CategoriaProductoResource($this->whenLoaded('categoria')),
            'proveedor' => $this->whenLoaded('proveedor', fn () => $this->proveedor ? [
                'id' => $this->proveedor->id,
                'nombre' => $this->proveedor->nombre,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
