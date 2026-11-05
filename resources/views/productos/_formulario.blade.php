@php($p = $producto)
<div class="space-y-6">
    {{-- Identificación --}}
    <fieldset class="space-y-4">
        <legend class="font-serif text-lg font-semibold text-ink-900">Identificación</legend>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="label">Nombre *</label>
                <input name="nombre" value="{{ old('nombre', $p->nombre) }}" class="field @error('nombre') border-red-400 @enderror" required maxlength="200" placeholder="Ej: Tinte 6-0 Rubio Oscuro">
                @error('nombre')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label">Categoría *</label>
                <select name="categoria_producto_id" class="field @error('categoria_producto_id') border-red-400 @enderror" required>
                    <option value="">Seleccione…</option>
                    @foreach($categorias as $cat)
                        <option value="{{ $cat->id }}" @selected((int) old('categoria_producto_id', $p->categoria_producto_id) === $cat->id)>{{ $cat->nombre }}</option>
                    @endforeach
                </select>
                @error('categoria_producto_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label">Marca</label>
                <input name="marca" value="{{ old('marca', $p->marca) }}" class="field" maxlength="120">
                @error('marca')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label">Línea</label>
                <input name="linea" value="{{ old('linea', $p->linea) }}" class="field" placeholder="Ej: Energizing, Silk Oil" maxlength="120">
                @error('linea')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label">Código / referencia</label>
                <input name="codigo" value="{{ old('codigo', $p->codigo) }}" class="field" maxlength="60">
                @error('codigo')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label">Tono (cuando aplique)</label>
                <input name="tono" value="{{ old('tono', $p->tono) }}" class="field" placeholder="Ej: 6-0, 8-17" maxlength="60">
                @error('tono')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label">Proveedor</label>
                <select name="proveedor_id" class="field">
                    <option value="">—</option>
                    @foreach($proveedores as $prov)
                        <option value="{{ $prov->id }}" @selected((int) old('proveedor_id', $p->proveedor_id) === $prov->id)>{{ $prov->nombre }}</option>
                    @endforeach
                </select>
                @error('proveedor_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>
    </fieldset>

    {{-- Unidades --}}
    <fieldset class="space-y-4 border-t border-cream-200 pt-5">
        <legend class="font-serif text-lg font-semibold text-ink-900">Unidad y presentación</legend>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
            <div>
                <label class="label">Unidad de control *</label>
                <select name="unidad_control" class="field @error('unidad_control') border-red-400 @enderror" required>
                    @foreach($unidades as $valor => $etiqueta)
                        <option value="{{ $valor }}" @selected(old('unidad_control', $p->unidad_control) === $valor)>{{ $etiqueta }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-ink-500">Cómo se mide y descuenta el producto (gramos, ml, bolsas o unidades).</p>
                @error('unidad_control')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label">Presentación</label>
                <input name="presentacion" value="{{ old('presentacion', $p->presentacion) }}" class="field" maxlength="80" placeholder="Ej: 1000 ml, 12 x 10 ml, bolsa 1000 g">
                <p class="mt-1 text-xs text-ink-500">Cómo se presenta el envase en la estantería.</p>
                @error('presentacion')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label">Tipo de uso *</label>
                <select name="tipo_uso" class="field @error('tipo_uso') border-red-400 @enderror" required>
                    @foreach($usos as $valor => $etiqueta)
                        <option value="{{ $valor }}" @selected(old('tipo_uso', $p->tipo_uso) === $valor)>{{ $etiqueta }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-ink-500">Profesional (uso interno), venta al público, o ambos.</p>
                @error('tipo_uso')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label">Contenido por envase</label>
                <input name="contenido_envase" type="number" step="0.001" min="0" value="{{ old('contenido_envase', $p->contenido_envase) }}" class="field">
                <p class="mt-1 text-xs text-ink-500">Ej: un envase de 60 g → escribe 60.</p>
                @error('contenido_envase')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>
    </fieldset>

    {{-- Costos y precios --}}
    <fieldset class="space-y-4 border-t border-cream-200 pt-5">
        <legend class="font-serif text-lg font-semibold text-ink-900">Costos y precios</legend>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="label">Costo de compra por envase</label>
                <input name="costo_envase" type="number" step="0.01" min="0" value="{{ old('costo_envase', $p->costo_envase) }}" class="field">
                <p class="mt-1 text-xs text-ink-500">Lo que se paga al proveedor por el envase completo.</p>
                @error('costo_envase')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label">Costo por unidad (gramo / ml / und)</label>
                <input name="costo_unitario" type="number" step="0.000001" min="0" value="{{ old('costo_unitario', $p->costo_unitario) }}" class="field" placeholder="Ej: 450">
                <p class="mt-1 text-xs text-ink-500">Valor usado para costear lo consumido en un servicio. Si se deja vacío se calcula como costo del envase ÷ contenido.</p>
                @error('costo_unitario')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label">Precio de costo (referencia)</label>
                <input name="precio_costo" type="number" step="0.01" min="0" value="{{ old('precio_costo', $p->precio_costo) }}" class="field">
                @error('precio_costo')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label">Precio sugerido de venta</label>
                <input name="precio_sugerido" type="number" step="0.01" min="0" value="{{ old('precio_sugerido', $p->precio_sugerido) }}" class="field">
                <p class="mt-1 text-xs text-ink-500">Referencia del catálogo; no se aplica automáticamente.</p>
                @error('precio_sugerido')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label">Precio de venta personalizado</label>
                <input name="precio_venta" type="number" step="0.01" min="0" value="{{ old('precio_venta', $p->precio_venta) }}" class="field">
                <p class="mt-1 text-xs text-ink-500">Precio con el que se vende en la peluquería.</p>
                @error('precio_venta')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>
    </fieldset>

    {{-- Inventario --}}
    <fieldset class="space-y-4 border-t border-cream-200 pt-5">
        <legend class="font-serif text-lg font-semibold text-ink-900">Inventario y vencimiento</legend>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            @unless($esEdicion)
                <div>
                    <label class="label">Stock inicial</label>
                    <input name="stock" type="number" step="0.001" min="0" value="{{ old('stock', '0') }}" class="field">
                    <p class="mt-1 text-xs text-ink-500">Crea un lote de apertura con el costo indicado. Después usa “Registrar entrada”.</p>
                    @error('stock')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            @endunless
            <div>
                <label class="label">Stock mínimo (alerta)</label>
                <input name="stock_minimo" type="number" step="0.001" min="0" value="{{ old('stock_minimo', $p->stock_minimo) }}" class="field">
                <p class="mt-1 text-xs text-ink-500">Se avisa cuando la existencia baja de este valor.</p>
                @error('stock_minimo')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label">Fecha de compra</label>
                <input name="fecha_compra" type="date" value="{{ old('fecha_compra', optional($p->fecha_compra)->format('Y-m-d')) }}" class="field">
                @error('fecha_compra')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label">Fecha de vencimiento</label>
                <input name="fecha_vencimiento" type="date" value="{{ old('fecha_vencimiento', optional($p->fecha_vencimiento)->format('Y-m-d')) }}" class="field">
                @error('fecha_vencimiento')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>
        @if($esEdicion)
            <p class="text-xs text-ink-500">Las existencias se cambian desde <strong>Registrar entrada</strong>, consumo o ajuste; editar la ficha no altera el stock.</p>
        @endif
    </fieldset>

    {{-- Notas y estado --}}
    <fieldset class="space-y-4 border-t border-cream-200 pt-5">
        <legend class="font-serif text-lg font-semibold text-ink-900">Notas y estado</legend>
        <div>
            <label class="label">Descripción</label>
            <textarea name="descripcion" class="field" rows="2">{{ old('descripcion', $p->descripcion) }}</textarea>
            @error('descripcion')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="flex flex-wrap gap-x-6 gap-y-2">
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="activo" value="1" class="rounded border-cream-300" @checked(old('activo', $p->activo ?? true))>
                Producto activo
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="pendiente_confirmacion" value="1" class="rounded border-cream-300" @checked(old('pendiente_confirmacion', $p->pendiente_confirmacion ?? false))>
                Pendiente de confirmación (datos por verificar)
            </label>
        </div>
    </fieldset>
</div>
