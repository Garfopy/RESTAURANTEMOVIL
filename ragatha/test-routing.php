<?php
header('Content-Type: application/json; charset=utf-8');

// Este archivo está en la raíz, así que si accedes a:
// https://idactivos.digital/test-routing.php
// Debería devolver el JSON abajo.
// Luego intenta acceder a:
// https://idactivos.digital/api/admin/test
// Si eso también devuelve el JSON, significa que el .htaccess está funcionando

echo json_encode([
    'success' => true,
    'message' => 'El enrutamiento está funcionando',
    'url' => $_GET['url'] ?? 'sin url',
    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'sin URI',
    'script_name' => $_SERVER['SCRIPT_NAME'] ?? 'sin script',
]);
