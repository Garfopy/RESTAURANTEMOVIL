<?php
/**
 * ProductImageHelper — Resuelve la URL de imagen de un producto.
 * Disponible globalmente (cargado desde index.php junto con el resto de helpers).
 */
if (!function_exists('getProductImageUrl')) {
    function getProductImageUrl(array $prod): string
    {
        // Si ya tiene imagen subida, usarla
        if (!empty($prod['imagen'])) {
            return htmlspecialchars($prod['imagen']);
        }

        $baseUrl = 'https://images.unsplash.com/photo-';
        $meatImages = [
            'res'       => '1588347818579-a62e21b490b0?w=800&h=600&fit=crop&auto=format',
            'cerdo'     => '1603048297172-c92544798d5a?w=800&h=600&fit=crop&auto=format',
            'pollo'     => '1587593810167-a84920ea0781?w=800&h=600&fit=crop&auto=format',
            'pescado'   => '1559847844-5315695dadae?w=800&h=600&fit=crop&auto=format',
            'cordero'   => '1529692236671-f1f6cf9683ba?w=800&h=600&fit=crop&auto=format',
            'embutidos' => '1589935512933-ac5c032fa7c8?w=800&h=600&fit=crop&auto=format',
            'mariscos'  => '1615485736162-5bf79b76e4cc?w=800&h=600&fit=crop&auto=format',
        ];

        $categoria = strtolower($prod['categoria_nombre'] ?? '');
        foreach ($meatImages as $key => $imgId) {
            if ($categoria !== '' && strpos($categoria, $key) !== false) {
                return $baseUrl . $imgId;
            }
        }

        return 'https://source.unsplash.com/800x600/?raw+meat,' . urlencode($categoria);
    }
}
