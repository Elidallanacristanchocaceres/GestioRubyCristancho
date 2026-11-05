<?php

/**
 * Catálogo y existencias iniciales de productos.
 *
 * REGLAS APLICADAS
 *  - No se inventa información. Cada entrada apunta al producto existente
 *    por id (producto_id) o crea uno nuevo (producto_id = null).
 *  - 'alias' es el nombre comercial de la lista de precios: no reemplaza el
 *    nombre del catálogo, se guarda en la descripción para poder reconocerlo.
 *  - Un envase 'empezado' NO suma existencias: se registra como pendiente
 *    de medición porque no se puede suponer cuánto queda dentro.
 *  - Los precios se aplican tal como fueron proporcionados. Cuando el valor
 *    cambia respecto al que estaba en la ficha, el producto queda marcado
 *    como 'pendiente' y la descripción guarda el valor anterior: la
 *    corrección queda registrada, nunca silenciosa.
 *
 * CLAVES
 *   producto_id       Id del producto existente (null = crear uno nuevo)
 *   nombre            Nombre (solo para productos nuevos)
 *   alias             Nombre comercial de la lista de precios
 *   categoria         Categoría destino (para productos nuevos)
 *   presentacion      Presentación del envase
 *   unidad            unidad | gramo | mililitro | bolsa
 *   contenido_envase  Contenido por envase
 *   costo_unitario    Costo por gramo/ml/unidad
 *   precio_venta      Precio de venta del envase
 *   stock_envases     Envases NUEVOS completos (suman existencias)
 *   envases_empezados Envases INICIADOS sin medir (no suman existencias)
 *   pendiente         Marca la ficha para revisión
 *   notas             Observación visible en la ficha del producto
 */

return [

    /* ==================================================================
     | CHAMPÚS
     * ================================================================== */
    [
        'producto_id' => 15,
        'alias' => 'Ever Young',
        'presentacion' => '1000 ml',
        'precio_venta' => 164000,
        'costo_unitario' => 164,
    ],
    [
        'producto_id' => 17,
        'alias' => 'AVO',
        'presentacion' => '1000 ml',
        'precio_venta' => 145000,
        'costo_unitario' => 145,
        'notas' => 'Costo por gramo según la lista de precios: $145 (el catálogo tenía $81).',
    ],
    [
        'producto_id' => 24,
        'alias' => 'Coco',
        'presentacion' => '1000 ml',
        'precio_venta' => 145000,
        'costo_unitario' => 145,
    ],
    [
        'producto_id' => 28,
        'alias' => 'Leche',
        'presentacion' => '1000 ml',
        'precio_venta' => 145000,
        'costo_unitario' => 145,
    ],
    [
        'producto_id' => 34,
        'alias' => 'Lino',
        'presentacion' => '1000 ml',
        'precio_venta' => 122000,
        'costo_unitario' => 122,
    ],
    [
        'producto_id' => 37,
        'alias' => 'Energy Scalp',
        'presentacion' => '950 ml',
        'precio_venta' => 186000,
        'costo_unitario' => 186,
        'stock_envases' => 1,
        'pendiente' => true,
        'notas' => 'Costo por gramo según la lista de precios: $186 (el catálogo tenía $164). La lista de existencias dice "Energizing Shampoo" sin presentación: se le asignó este de 950 ml, confirmar.',
    ],
    [
        'producto_id' => 41,
        'alias' => 'Balancing',
        'presentacion' => '950 ml',
        'precio_venta' => 186000,
        'costo_unitario' => 186,
    ],
    [
        'producto_id' => 42,
        'alias' => 'Calming',
        'presentacion' => '950 ml',
        'precio_venta' => 186000,
        'costo_unitario' => 186,
    ],
    [
        'producto_id' => 43,
        'alias' => 'Detoxifying',
        'presentacion' => '950 ml',
        'precio_venta' => 186000,
        'costo_unitario' => 186,
        'notas' => 'En la lista de precios aparece como "Detoxifying"; en el catálogo es "Densifying Shampoo 950ml". Verificar si son el mismo producto.',
    ],
    [
        'producto_id' => 47,
        'alias' => 'Hidratante',
        'presentacion' => '950 ml',
        'precio_venta' => 161000,
        'costo_unitario' => 161,
    ],
    [
        'producto_id' => 53,
        'alias' => 'Silk Oil',
        'presentacion' => '950 ml',
        'precio_venta' => 186000,
        'costo_unitario' => 186,
        'notas' => 'Costo por gramo según la lista de precios: $186 (el catálogo tenía $182).',
    ],
    [
        'producto_id' => 60,
        'alias' => 'Fill-er',
        'presentacion' => '950 ml',
        'precio_venta' => 186000,
        'costo_unitario' => 186,
    ],
    [
        'producto_id' => 65,
        'alias' => 'Color Care',
        'presentacion' => '950 ml',
        'precio_venta' => 161000,
        'costo_unitario' => 161,
        'notas' => 'Costo por gramo según la lista de precios: $161 (el catálogo tenía $167).',
    ],
    [
        'producto_id' => 71,
        'alias' => 'Silvery',
        'presentacion' => '950 ml',
        'precio_venta' => 161000,
        'costo_unitario' => 161,
        'notas' => 'En la lista de precios aparece como "Silvery"; en el catálogo es "Silver Shampoo 950ml". Costo por gramo según la lista: $161 (el catálogo tenía $167).',
    ],
    [
        'producto_id' => 81,
        'alias' => 'Shampoo Argan Waf',
        'presentacion' => '500 ml',
        'precio_venta' => 116000,
        'costo_unitario' => 232,
        'notas' => 'La lista reporta $116.000 con costo de $232 por gramo: 500 g × $232 = $116.000 exactos, es decir el precio coincide con el costo.',
    ],
    [
        'producto_id' => 83,
        'alias' => 'Shampoo Proteins Huevo',
        'presentacion' => '1000 ml',
        'precio_venta' => 190000,
        'costo_unitario' => 200,
        'stock_envases' => 2,
        'envases_empezados' => 1,
        'notas' => '2 envases nuevos y 1 empezado sin medir.',
    ],

    /* ------------------------------------------------------------------
     | Línea Terra (productos de 950/1000 ml a precio premium)
     * ---------------------------------------------------------------- */
    [
        'producto_id' => 16,
        'alias' => 'Terra Rebalancing',
        'presentacion' => '1000 ml',
        'precio_venta' => 237000,
        'costo_unitario' => 237,
        'notas' => 'En la lista aparece como "Terra Rebalancing"; en el catálogo es "Rebalancing Cream 1000ml" (categoría Tratamientos).',
    ],
    [
        'producto_id' => 18,
        'alias' => 'Terra AVO',
        'presentacion' => '1000 ml',
        'precio_venta' => 237000,
        'costo_unitario' => 237,
        'notas' => 'En la lista aparece como "Terra AVO"; en el catálogo es "Garlic Mask 1000ml". Verificar equivalencia.',
    ],
    [
        'producto_id' => 25,
        'alias' => 'Terra Coco',
        'presentacion' => '1000 ml',
        'precio_venta' => 237000,
        'costo_unitario' => 237,
        'notas' => 'En la lista aparece como "Terra Coco"; en el catálogo es "Coconut Mask 1000ml". Verificar equivalencia.',
        'stock_envases' => 1,
    ],
    [
        'producto_id' => 29,
        'alias' => 'Terra Leche',
        'presentacion' => '1000 ml',
        'precio_venta' => 237000,
        'costo_unitario' => 237,
        'notas' => 'En la lista aparece como "Terra Leche"; en el catálogo es "Milk Mask 1000ml". Verificar equivalencia.',
    ],
    [
        'producto_id' => 56,
        'alias' => 'Terra Silk Oil',
        'presentacion' => '950 ml',
        'precio_venta' => 207000,
        'costo_unitario' => 207,
        'notas' => 'En la lista aparece como "Terra Silk Oil"; en el catálogo es "Silk Oil Conditioner 950ml". Verificar equivalencia.',
        'stock_envases' => 1,
    ],
    [
        'producto_id' => 62,
        'alias' => 'Terra Filler',
        'presentacion' => '950 ml',
        'precio_venta' => 207000,
        'costo_unitario' => 207,
        'notas' => 'En la lista aparece como "Terra Filler"; en el catálogo es "Filler Mask 950ml". Verificar equivalencia.',
        'stock_envases' => 1,
    ],
    [
        'producto_id' => 68,
        'alias' => 'Terra Color Care',
        'presentacion' => '950 ml',
        'precio_venta' => 186000,
        'costo_unitario' => 186,
        'notas' => 'En la lista aparece como "Terra Color Care"; en el catálogo es "Color Care Mask 950ml". Verificar equivalencia.',
    ],
    [
        'producto_id' => 74,
        'alias' => 'Terra Silvery',
        'presentacion' => '950 ml',
        'precio_venta' => 207000,
        'costo_unitario' => 207,
        'notas' => 'En la lista aparece como "Terra Silvery"; en el catálogo es "Silver Conditioner 950ml". Verificar equivalencia.',
    ],

    /* ------------------------------------------------------------------
     | Productos que aún no existen en el catálogo
     * ---------------------------------------------------------------- */
    [
        'producto_id' => null,
        'nombre' => 'Shampoo Terra sin identificar',
        'alias' => 'Terra sin identificar',
        'categoria' => 'Shampoos',
        'presentacion' => 'Por confirmar',
        'unidad' => 'mililitro',
        'precio_venta' => 186000,
        'costo_unitario' => 186,
        'pendiente' => true,
        'notas' => 'Producto reportado como "sin identificar" en la lista de precios ($186.000 / $186 por gramo). Falta identificarlo con la foto.',
    ],
    [
        'producto_id' => null,
        'nombre' => 'Shampoo Bamboo',
        'categoria' => 'Shampoos',
        'presentacion' => 'Por confirmar',
        'unidad' => 'mililitro',
        'stock_envases' => 2,
        'envases_empezados' => 1,
        'pendiente' => true,
        'notas' => '2 envases nuevos y 1 empezado sin medir. Falta confirmar presentación y precio.',
    ],
    [
        'producto_id' => null,
        'nombre' => 'Shampoo Hydrate',
        'categoria' => 'Shampoos',
        'presentacion' => 'Por confirmar',
        'unidad' => 'mililitro',
        'stock_envases' => 2,
        'pendiente' => true,
        'notas' => '2 envases. Falta confirmar presentación y precio.',
    ],
    [
        'producto_id' => null,
        'nombre' => 'Acondicionador Hydrate',
        'categoria' => 'Acondicionadores',
        'presentacion' => '950 ml',
        'unidad' => 'mililitro',
        'contenido_envase' => 950,
        'stock_envases' => 1,
        'envases_empezados' => 1,
        'pendiente' => true,
        'notas' => 'Envase empezado sin medir: queda pendiente de pesaje. Falta confirmar precio y presentación.',
    ],
    [
        'producto_id' => null,
        'nombre' => 'Exfoliante capilar Scrub',
        'categoria' => 'Tratamientos',
        'presentacion' => 'Por confirmar',
        'unidad' => 'mililitro',
        'stock_envases' => 3,
        'pendiente' => true,
        'notas' => '3 envases. Falta confirmar si se mide en ml o en gramos, la presentación y el precio.',
    ],
    [
        'producto_id' => null,
        'nombre' => 'Tratamiento Bamboo',
        'categoria' => 'Tratamientos',
        'presentacion' => 'Por confirmar',
        'unidad' => 'mililitro',
        'envases_empezados' => 1,
        'pendiente' => true,
        'notas' => '1 envase empezado sin medir: queda pendiente de pesaje. Falta confirmar presentación y precio.',
    ],
    [
        'producto_id' => null,
        'nombre' => 'Tratamiento Argan Waf',
        'categoria' => 'Tratamientos',
        'presentacion' => 'Por confirmar',
        'unidad' => 'mililitro',
        'precio_venta' => 229000,
        'costo_unitario' => 558,
        'pendiente' => true,
        'notas' => 'Reportado como $229.000 con costo de $558 por gramo. No hay equivalente en el catálogo actual. Falta confirmar presentación.',
    ],

    /* ------------------------------------------------------------------
     | AcONDICIONADORES, MÁSCARAS Y TRATAMIENTOS (existentes)
     * ---------------------------------------------------------------- */
    [
        'producto_id' => 39,
        'alias' => 'Energizing Shampoo 300 ml',
        'presentacion' => '300 ml',
        'stock_envases' => 1,
    ],
    [
        'producto_id' => 54,
        'alias' => 'Silk Oil Shampoo 300 ml',
        'presentacion' => '300 ml',
        'stock_envases' => 1,
    ],
    [
        'producto_id' => 55,
        'alias' => 'Silk Oil Conditioner 300 ml',
        'presentacion' => '300 ml',
        'stock_envases' => 1,
    ],
    [
        'producto_id' => 50,
        'alias' => 'Acondicionador Hydraday',
        'presentacion' => '950 ml',
        'stock_envases' => 1,
        'pendiente' => true,
        'notas' => 'La lista indica "Acondicionador Hydraday" sin presentación; se asignó el de 950 ml. Confirmar si corresponde al de 200 ml.',
    ],
    [
        'producto_id' => 51,
        'alias' => 'Hydraday Whipped Cream',
        'presentacion' => '200 ml',
        'stock_envases' => 3,
        'pendiente' => true,
        'notas' => 'La lista indica "Hydraday Whipped Cream" sin presentación; se asignó el de 200 ml. Confirmar si corresponde al de 75 ml.',
    ],
    [
        'producto_id' => 89,
        'alias' => 'Tratamiento Huevo',
        'presentacion' => '350 ml',
        'precio_venta' => 154000,
        'costo_unitario' => 550,
        'stock_envases' => 2,
        'envases_empezados' => 1,
        'notas' => '2 envases nuevos y 1 empezado sin medir. En el catálogo el nombre incluye "(últimas unidades)": conviene actualizarlo si ya no aplica.',
    ],

    /* ------------------------------------------------------------------
     | AMPOLLAS
     * ---------------------------------------------------------------- */
    [
        'producto_id' => 33,
        'categoria' => 'Ampollas y Monodosis',
        'alias' => 'Ampolla Milk',
        'presentacion' => '12 x 10 ml',
        'unidad' => 'unidad',
        'contenido_envase' => 12,
        'precio_venta' => 186000,
        'costo_unitario' => 18000,
        'pendiente' => true,
        'notas' => 'La lista reporta $186.000 y $18.000 por unidad; 12 × $18.000 = $216.000, que es el precio que ya estaba en la ficha. Se aplicó el valor de la lista y queda marcado para revisión.',
    ],
    [
        'producto_id' => 38,
        'categoria' => 'Ampollas y Monodosis',
        'alias' => 'Ampolla Energizing',
        'presentacion' => '12 x 10 ml',
        'unidad' => 'unidad',
        'contenido_envase' => 12,
        'precio_venta' => 232000,
        'costo_unitario' => 20000,
        'pendiente' => true,
        'notas' => 'Inconsistencia matemática: 12 × $20.000 = $240.000, pero el precio reportado es $232.000. No se ajustó.',
    ],
    [
        'producto_id' => null,
        'nombre' => 'Ampolla Silk Rin Of 12x10ml',
        'alias' => 'Silk Rin Of',
        'categoria' => 'Ampollas y Monodosis',
        'presentacion' => '12 x 10 ml',
        'unidad' => 'unidad',
        'contenido_envase' => 12,
        'precio_venta' => 216000,
        'costo_unitario' => 18000,
    ],
    [
        'producto_id' => 57,
        'categoria' => 'Ampollas y Monodosis',
        'alias' => 'Ampolla Silk Oil',
        'presentacion' => '12 x 10 ml',
        'unidad' => 'unidad',
        'contenido_envase' => 12,
        'precio_venta' => 168000,
        'costo_unitario' => 15000,
        'pendiente' => true,
        'notas' => 'Inconsistencia matemática: 12 × $15.000 = $180.000, pero el precio reportado es $168.000. No se ajustó.',
    ],
    [
        'producto_id' => 64,
        'categoria' => 'Ampollas y Monodosis',
        'alias' => 'Ampolla Filler',
        'presentacion' => '12 x 10 ml',
        'unidad' => 'unidad',
        'contenido_envase' => 12,
        'precio_venta' => 257000,
        'costo_unitario' => 22000,
        'pendiente' => true,
        'notas' => 'Inconsistencia matemática: 12 × $22.000 = $264.000, pero el precio reportado es $257.000. No se ajustó.',
    ],
    [
        'producto_id' => 97,
        'categoria' => 'Ampollas y Monodosis',
        'alias' => 'Ampolla Proteína Huevo',
        'presentacion' => '12 x 10 ml',
        'unidad' => 'unidad',
        'contenido_envase' => 12,
        'precio_venta' => 277000,
        'costo_unitario' => 27700,
        'pendiente' => true,
        'notas' => 'Inconsistencia matemática: 12 × $27.700 = $332.400, pero el precio reportado es $277.000. No se ajustó. El producto no tenía precio de venta.',
    ],
    [
        'producto_id' => 86,
        'categoria' => 'Ampollas y Monodosis',
        'alias' => 'Ampolla Huevo',
        'presentacion' => '6 x 10 ml',
        'unidad' => 'unidad',
        'contenido_envase' => 6,
        'precio_venta' => 277000,
        'costo_unitario' => 27700,
        'stock_envases' => 1,
        'envases_empezados' => 1,
        'pendiente' => true,
        'notas' => '1 caja nueva y 1 caja empezada (pendiente de medición). Confirmar si el precio corresponde a la caja de 6 o a la de 12.',
    ],

    /* ------------------------------------------------------------------
     | TINTES, DECOLORANTES Y ACTIVADORES
     * ---------------------------------------------------------------- */
    [
        'producto_id' => 1,
        'alias' => 'Tinte',
        'presentacion' => '100 ml',
        'precio_venta' => 44000,
        'costo_unitario' => 450,
        'notas' => 'Verificado contra la lista de precios ($44.000 / $450 por gramo): coincide con la ficha.',
    ],
    [
        'producto_id' => 10,
        'alias' => 'CC a tono',
        'presentacion' => '500 g',
        'precio_venta' => 190000,
        'costo_unitario' => 390,
        'pendiente' => true,
        'notas' => 'La lista lo reporta como "CC a tono $190.000 / $390 por gramo"; coincide precio y costo por gramo con "Ultra 9 Lightener 500gr". Verificar a qué producto corresponde.',
    ],
    [
        'producto_id' => 11,
        'alias' => 'CC Cream',
        'presentacion' => '500 g',
        'precio_venta' => 211000,
        'costo_unitario' => 430,
        'pendiente' => true,
        'notas' => 'La lista lo reporta como "CC Cream $211.000 / $430 por gramo"; coincide con "Pure Light Cream 500gr". Verificar a qué producto corresponde.',
    ],
    [
        'producto_id' => 13,
        'alias' => 'Decolorante sin identificar',
        'presentacion' => '500 g',
        'precio_venta' => 250000,
        'costo_unitario' => 500,
        'pendiente' => true,
        'notas' => 'Reportado como "$250.000 / 500 g" sin identificar: $500 por gramo coincide con "Gentle Protective Lightener 500gr". El precio de venta del catálogo era $252.000.',
    ],
    [
        'producto_id' => 14,
        'alias' => 'Decolorante sin identificar',
        'presentacion' => '1000 g',
        'precio_venta' => 243000,
        'costo_unitario' => 250,
        'pendiente' => true,
        'notas' => 'Reportado como "$243.000 / $250 por gramo" sin identificar: $250 por gramo coincide con "Bleaching White Powder 1000gr". El precio de venta del catálogo era $245.000.',
    ],
    [
        'producto_id' => null,
        'nombre' => 'Corrector',
        'alias' => 'Corrector',
        'categoria' => 'Decolorantes y aclarantes',
        'presentacion' => '50 g',
        'unidad' => 'gramo',
        'contenido_envase' => 50,
        'precio_venta' => 43000,
        'pendiente' => true,
        'notas' => 'Reportado como "$43.000 / 50 g": no se puede confirmar si 50 corresponde a gramos de contenido o a costo por gramo. Dejar pendiente hasta ver la foto.',
    ],
    [
        'producto_id' => null,
        'nombre' => 'Deco White',
        'alias' => 'Deco White',
        'categoria' => 'Decolorantes y aclarantes',
        'presentacion' => '1000 g',
        'unidad' => 'bolsa',
        'contenido_envase' => 1000,
        'stock_envases' => 5,
        'notas' => '5 bolsas nuevas de 1000 g.',
    ],
    [
        'producto_id' => null,
        'nombre' => 'Deco Cream',
        'alias' => 'Deco Cream',
        'categoria' => 'Decolorantes y aclarantes',
        'presentacion' => '1000 g',
        'unidad' => 'bolsa',
        'contenido_envase' => 1000,
        'envases_empezados' => 1,
        'pendiente' => true,
        'notas' => '1 bolsa empezada con aproximadamente 500 g reportados: es una aproximación, por eso NO se descontó stock y queda pendiente de medición.',
    ],
    [
        'producto_id' => null,
        'nombre' => 'Activador 1000 ml 5 Vol',
        'categoria' => 'Oxidantes y Activadores',
        'presentacion' => '1000 ml',
        'unidad' => 'mililitro',
        'contenido_envase' => 1000,
        'pendiente' => true,
        'notas' => 'Referencia individual solicitada para no mezclar volúmenes. Falta confirmar cantidad y estado con la foto.',
    ],
    [
        'producto_id' => null,
        'nombre' => 'Activador 1000 ml 10 Vol',
        'categoria' => 'Oxidantes y Activadores',
        'presentacion' => '1000 ml',
        'unidad' => 'mililitro',
        'contenido_envase' => 1000,
        'pendiente' => true,
        'notas' => 'Referencia individual solicitada para no mezclar volúmenes. Falta confirmar cantidad y estado con la foto.',
    ],
    [
        'producto_id' => null,
        'nombre' => 'Activador 1000 ml 20 Vol',
        'categoria' => 'Oxidantes y Activadores',
        'presentacion' => '1000 ml',
        'unidad' => 'mililitro',
        'contenido_envase' => 1000,
        'pendiente' => true,
        'notas' => 'Referencia individual solicitada para no mezclar volúmenes. Falta confirmar cantidad y estado con la foto.',
    ],
    [
        'producto_id' => null,
        'nombre' => 'Activador 1000 ml 30 Vol',
        'categoria' => 'Oxidantes y Activadores',
        'presentacion' => '1000 ml',
        'unidad' => 'mililitro',
        'contenido_envase' => 1000,
        'pendiente' => true,
        'notas' => 'Referencia individual solicitada para no mezclar volúmenes. Falta confirmar cantidad y estado con la foto.',
    ],
    [
        'producto_id' => null,
        'nombre' => 'Activador 1000 ml 40 Vol',
        'categoria' => 'Oxidantes y Activadores',
        'presentacion' => '1000 ml',
        'unidad' => 'mililitro',
        'contenido_envase' => 1000,
        'pendiente' => true,
        'notas' => 'Referencia individual solicitada para no mezclar volúmenes. Falta confirmar cantidad y estado con la foto.',
    ],
];
