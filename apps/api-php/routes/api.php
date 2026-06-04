<?php

declare(strict_types=1);

$method = $_SERVER['REQUEST_METHOD'];
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$basePath = dirname($_SERVER['SCRIPT_NAME']);

$routes = [
    // Auth routes
    ['POST', '/auth/register', ['Amare\Api\Controllers\AuthController', 'register']],
    ['POST', '/auth/login', ['Amare\Api\Controllers\AuthController', 'login']],
    ['POST', '/auth/google', ['Amare\Api\Controllers\AuthController', 'google']],
    ['GET', '/auth/me', ['Amare\Api\Controllers\AuthController', 'me']],
    ['PUT', '/auth/update-password', ['Amare\Api\Controllers\AuthController', 'updatePassword']],
    
    // Branches routes
    ['GET', '/branches', ['Amare\Api\Controllers\BranchController', 'index']],
    ['GET', '/branches/:id', ['Amare\Api\Controllers\BranchController', 'show']],
    
    // Menu routes
    ['GET', '/menu/categories', ['Amare\Api\Controllers\MenuController', 'categories']],
    ['GET', '/menu/products', ['Amare\Api\Controllers\MenuController', 'products']],
    ['GET', '/menu/products/:id', ['Amare\Api\Controllers\MenuController', 'showProduct']],
    
    // Orders routes
    ['GET', '/orders', ['Amare\Api\Controllers\OrderController', 'index']],
    ['GET', '/orders/:id', ['Amare\Api\Controllers\OrderController', 'show']],
    ['POST', '/orders', ['Amare\Api\Controllers\OrderController', 'store']],
    
    // Payments routes
    ['POST', '/payments/create-intent', ['Amare\Api\Controllers\PaymentController', 'createPaymentIntent']],
    ['POST', '/payments/webhook', ['Amare\Api\Controllers\PaymentController', 'webhook']],
    
    // Profile routes
    ['GET', '/profile', ['Amare\Api\Controllers\ProfileController', 'show']],
    ['PUT', '/profile', ['Amare\Api\Controllers\ProfileController', 'update']],
    ['GET', '/profile/orders', ['Amare\Api\Controllers\ProfileController', 'orders']],
    
    // Favorites routes
    ['GET', '/favorites', ['Amare\Api\Controllers\FavoritesController', 'index']],
    ['POST', '/favorites/:product_id', ['Amare\Api\Controllers\FavoritesController', 'store']],
    ['DELETE', '/favorites/:product_id', ['Amare\Api\Controllers\FavoritesController', 'destroy']],
    
    // Promotions routes
    ['GET', '/promotions', ['Amare\Api\Controllers\PromotionsController', 'index']],
    ['GET', '/promotions/:id', ['Amare\Api\Controllers\PromotionsController', 'show']],
    ['POST', '/promotions/validate', ['Amare\Api\Controllers\PromotionsController', 'validateCode']],
];

// Remove base path from request URI
$requestPath = str_replace($basePath, '', $requestUri);

if (empty($requestPath)) {
    $requestPath = '/';
}

// Find matching route
$matchedRoute = null;
$routeParams = [];

foreach ($routes as $route) {
    [$routeMethod, $routePath, $handler] = $route;
    
    if ($routeMethod !== $method) {
        continue;
    }
    
    // Convert route pattern to regex
    $pattern = preg_replace('/:\w+/', '(\d+)', $routePath);
    $pattern = '#^' . $pattern . '$#';
    
    if (preg_match($pattern, $requestPath, $matches)) {
        $matchedRoute = $route;
        
        // Extract route parameters
        preg_match_all('/:(\w+)/', $routePath, $paramNames);
        foreach ($paramNames[1] as $index => $paramName) {
            $routeParams[$paramName] = (int)$matches[$index + 1];
        }
        
        break;
    }
}

if (!$matchedRoute) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'Endpoint no encontrado'
    ]);
    exit;
}

// Call handler
$handler = $matchedRoute[2];

if (is_array($handler)) {
    $controller = new $handler[0]();
    $method = $handler[1];
    
    if (!empty($routeParams)) {
        call_user_func_array([$controller, $method], $routeParams);
    } else {
        $controller->$method();
    }
}