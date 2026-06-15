<?php
/**
 * cron/recordatorio_reservas.php
 *
 * Envía un recordatorio por correo a los comensales que tienen
 * reservación MAÑANA (24h antes) y aún no han recibido el aviso.
 *
 * Ejecutar diariamente (sugerido 09:00):
 *   0 9 * * *  php /ruta/al/proyecto/cron/recordatorio_reservas.php
 *
 * Es idempotente: solo manda a quienes tengan recordatorio_enviado = 0.
 */

define('ROOT_PATH', dirname(__DIR__));

require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/config/database.php';
if (file_exists(ROOT_PATH . '/vendor/autoload.php')) {
    require_once ROOT_PATH . '/vendor/autoload.php';
}

foreach (glob(ROOT_PATH . '/app/models/*.php') as $f)   require_once $f;
foreach (glob(ROOT_PATH . '/app/services/*.php') as $f) require_once $f;

$log = function (string $m): void {
    echo '[' . date('Y-m-d H:i:s') . "] $m\n";
};

$reservaModel = new RestReservaModel();
$email        = new EmailService();

if (!$email->isConfigured()) {
    $log('SMTP no configurado. Abortando.');
    exit(1);
}

$pendientes = $reservaModel->getParaRecordatorio();
$log('Recordatorios pendientes: ' . count($pendientes));

$enviados = 0;
$fallos   = 0;

foreach ($pendientes as $r) {
    $restaurante = [
        'nombre'         => $r['rest_nombre']    ?? '',
        'slug'           => $r['rest_slug']      ?? '',
        'telefono'       => $r['rest_telefono']  ?? '',
        'direccion'      => $r['rest_direccion'] ?? '',
        'color_primario' => $r['color_primario'] ?? '#C8102E',
    ];

    $cancelUrl = BASE_URL . 'menu/' . $restaurante['slug'] . '/cancelarReserva/' . (int)$r['id'];

    $ok = $email->enviarRecordatorioReserva(
        $r['email'],
        $restaurante,
        [
            'nombre'      => $r['nombre'],
            'hora'        => $r['hora'],
            'personas'    => $r['personas'],
            'mesa_nombre' => $r['mesa_nombre'] ?? null,
        ],
        $cancelUrl
    );

    if ($ok) {
        $reservaModel->marcarRecordatorioEnviado((int)$r['id']);
        $enviados++;
        $log("OK  → reserva #{$r['id']}  {$r['email']}");
    } else {
        $fallos++;
        $log("FAIL → reserva #{$r['id']}  {$r['email']}");
    }
}

$log("Terminado. Enviados: $enviados — Fallos: $fallos");
