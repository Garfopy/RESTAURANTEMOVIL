<?php
/**
 * CapiRest — Global Configuration
 * Auto-detects BASE_URL regardless of subdirectory installation.
 */

// Detect protocol
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host     = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Detect base path from actual script location
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
$basePath  = ($scriptDir === '/') ? '/' : rtrim($scriptDir, '/') . '/';

define('BASE_URL',      $protocol . $host . $basePath);
define('BASE_URL_PATH', $basePath);
define('BASE_PATH',     dirname(__DIR__));   // project root

// Application
define('APP_NAME',      'CapiRest');
define('APP_VERSION',   '1.0.0');
define('APP_LOCALE',    'es_MX');

// Default brand color (overridable from global_settings)
define('BRAND_COLOR',   '#C8102E');

// Session name
define('SESSION_NAME',  'capirest_session');

// Despliegue standalone (solo restaurante, sin SaaS multi-empresa)
if (!defined('RESTAURANTE_STANDALONE')) define('RESTAURANTE_STANDALONE', true);

// Upload directories
define('UPLOAD_PATH',   BASE_PATH . '/public/uploads/');
define('UPLOAD_URL',    BASE_URL  . 'public/uploads/');

// Pagination
define('PER_PAGE', 20);

// Google Maps Places API key (agregar en cPanel o aquí directamente)
define('GOOGLE_MAPS_KEY', getenv('GOOGLE_MAPS_KEY') ?: '');

// Stripe — pago con tarjeta (MXN)
define('STRIPE_PUBLIC_KEY', getenv('STRIPE_PUBLIC_KEY') ?: '');
define('STRIPE_SECRET_KEY', getenv('STRIPE_SECRET_KEY') ?: '');
