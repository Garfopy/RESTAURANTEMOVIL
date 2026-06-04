<?php

declare(strict_types=1);

/** Amare API - PHP
 * 
 * API REST para la aplicación Amare
 * Versión PHP compatible con hosting cPanel
 */

// Manejar errores
error_reporting(E_ALL);
if ($_ENV['APP_DEBUG'] ?? false) {
    ini_set('display_errors', '1');
} else {
    ini_set('display_errors', '0');
}

// Cargar configuración
require_once __DIR__ . '/src/Config/Config.php';

// Cargar autoloader de Composer
require_once __DIR__ . '/vendor/autoload.php';

// Cargar rutas
require_once __DIR__ . '/routes/api.php';

echo '<pre>';
print_r($_ENV);
exit;