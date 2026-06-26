<?php

declare(strict_types=1);

$requestMethod = $_SERVER['REQUEST_METHOD'];
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
    ['GET', '/branches/nearest', ['Amare\Api\Controllers\BranchController', 'nearest']],

    // Config routes (configuración por restaurante) - DEBEN ir antes de /branches/:id
    ['GET', '/settings/theme', ['Amare\Api\Controllers\SettingsController', 'theme']],
    ['GET', '/branches/:id/config', ['Amare\Api\Controllers\ConfigController', 'show']],
    ['PUT', '/branches/:id/config', ['Amare\Api\Controllers\ConfigController', 'update']],

    ['GET', '/branches/:id', ['Amare\Api\Controllers\BranchController', 'show']],
    
    // Menu routes
    ['GET', '/menu/categories', ['Amare\Api\Controllers\MenuController', 'categories']],
    ['GET', '/menu/products', ['Amare\Api\Controllers\MenuController', 'products']],
    ['GET', '/menu/products/:id', ['Amare\Api\Controllers\MenuController', 'showProduct']],
    ['PUT', '/branches/:id/menu-items/:platilloId/modifiers', ['Amare\Api\Controllers\MenuController', 'syncModifiers']],
    ['GET', '/branches/:id/menu-items/:platilloId/modifiers', ['Amare\Api\Controllers\MenuController', 'showModifiers']],
    
    // Orders routes
    ['GET', '/orders', ['Amare\Api\Controllers\OrderController', 'index']],
    ['GET', '/orders/:id', ['Amare\Api\Controllers\OrderController', 'show']],
    ['GET', '/orders/:id/exit-pass', ['Amare\Api\Controllers\OrderController', 'exitPass']],
    ['POST', '/orders', ['Amare\Api\Controllers\OrderController', 'store']],
    ['POST', '/orders/:id/confirm-payment', ['Amare\Api\Controllers\OrderController', 'confirmPayment']],
    ['POST', '/orders/exit-pass/scan', ['Amare\Api\Controllers\OrderController', 'scanExitPass']],

    // Waiter routes
    ['GET', '/waiter/branches', ['Amare\Api\Controllers\WaiterController', 'branches']],
    ['GET', '/waiter/tables', ['Amare\Api\Controllers\WaiterController', 'tables']],
    ['GET', '/waiter/gifts', ['Amare\Api\Controllers\WaiterController', 'gifts']],
    ['POST', '/waiter/gifts/:id/claim', ['Amare\Api\Controllers\WaiterController', 'claimGift']],
    ['POST', '/waiter/gifts/:id/release', ['Amare\Api\Controllers\WaiterController', 'releaseGift']],
    ['POST', '/waiter/gifts/:id/deliver', ['Amare\Api\Controllers\WaiterController', 'deliverGift']],
    ['POST', '/waiter/tables/:id/claim', ['Amare\Api\Controllers\WaiterController', 'claimTable']],
    ['POST', '/waiter/tables/:id/release', ['Amare\Api\Controllers\WaiterController', 'releaseTable']],
    ['GET', '/waiter/tables/:id/account', ['Amare\Api\Controllers\WaiterController', 'account']],
    ['POST', '/waiter/tables/:id/orders', ['Amare\Api\Controllers\WaiterController', 'createOrder']],
    ['POST', '/waiter/tables/:id/splits', ['Amare\Api\Controllers\WaiterController', 'createSplit']],
    ['POST', '/waiter/tables/:id/splits/:splitId/accounts/:accountId/pay', ['Amare\Api\Controllers\WaiterController', 'paySplitAccount']],
    ['DELETE', '/waiter/tables/:id/splits/:splitId', ['Amare\Api\Controllers\WaiterController', 'cancelSplit']],
    ['POST', '/waiter/tables/:id/close', ['Amare\Api\Controllers\WaiterController', 'closeAccount']],
    
    // Payments routes
    ['POST', '/payments/create-intent', ['Amare\Api\Controllers\PaymentController', 'createPaymentIntent']],
    ['POST', '/payments/webhook', ['Amare\Api\Controllers\PaymentController', 'webhook']],
    
    // Profile routes
    ['GET', '/profile', ['Amare\Api\Controllers\ProfileController', 'show']],
    ['PUT', '/profile', ['Amare\Api\Controllers\ProfileController', 'update']],
    ['GET', '/profile/orders', ['Amare\Api\Controllers\ProfileController', 'orders']],
    ['POST', '/profile/avatar', ['Amare\Api\Controllers\ProfileController', 'updateAvatar']],
    
    // Address routes
    ['GET', '/profile/addresses', ['Amare\Api\Controllers\AddressController', 'index']],
    ['POST', '/profile/addresses', ['Amare\Api\Controllers\AddressController', 'store']],
    ['GET', '/profile/addresses/:id', ['Amare\Api\Controllers\AddressController', 'show']],
    ['PUT', '/profile/addresses/:id', ['Amare\Api\Controllers\AddressController', 'update']],
    ['DELETE', '/profile/addresses/:id', ['Amare\Api\Controllers\AddressController', 'destroy']],
    
    // Favorites routes
    ['GET', '/favorites', ['Amare\Api\Controllers\FavoritesController', 'index']],
    ['POST', '/favorites/toggle', ['Amare\Api\Controllers\FavoritesController', 'toggle']],

    // Social routes
    ['POST', '/users/social-status', ['Amare\Api\Controllers\SocialController', 'updateStatus']],
    ['PATCH', '/users/social-status', ['Amare\Api\Controllers\SocialController', 'updateStatus']],
    ['GET', '/users/social-profile', ['Amare\Api\Controllers\SocialController', 'getProfile']],
    ['PUT', '/users/social-profile', ['Amare\Api\Controllers\SocialController', 'updateProfile']],
    ['POST', '/users/social-profile/photo', ['Amare\Api\Controllers\SocialController', 'uploadPhoto']],
    ['DELETE', '/users/social-profile/photo', ['Amare\Api\Controllers\SocialController', 'deletePhoto']],
    ['POST', '/users/social-profile/photo/primary', ['Amare\Api\Controllers\SocialController', 'setPrimaryPhoto']],
    ['POST', '/users/social-photo', ['Amare\Api\Controllers\SocialController', 'uploadPhoto']],
    ['GET', '/users/:id/public-profile', ['Amare\Api\Controllers\SocialController', 'publicProfile']],
    ['GET', '/restaurants/:id/active-diners', ['Amare\Api\Controllers\SocialController', 'activeDiners']],
    ['GET', '/restaurants/:id/active-users', ['Amare\Api\Controllers\SocialController', 'activeDiners']],
    ['GET', '/restaurants/:id/tables', ['Amare\Api\Controllers\SocialController', 'restaurantTables']],
    ['POST', '/restaurants/tables/scan', ['Amare\Api\Controllers\SocialController', 'scanTable']],
    ['POST', '/social/likes', ['Amare\Api\Controllers\SocialController', 'likeDiner']],
    ['DELETE', '/social/likes/:id', ['Amare\Api\Controllers\SocialController', 'unlikeDiner']],
    ['GET', '/social/likes/received', ['Amare\Api\Controllers\SocialController', 'receivedLikes']],
    ['GET', '/social/likes/sent', ['Amare\Api\Controllers\SocialController', 'sentLikes']],
    ['GET', '/social/matches', ['Amare\Api\Controllers\SocialController', 'matches']],
    ['GET', '/social/account-notifications', ['Amare\Api\Controllers\SocialController', 'accountNotifications']],
    ['GET', '/social/diners/:id/account', ['Amare\Api\Controllers\SocialController', 'dinerAccount']],
    ['POST', '/social/diners/:id/cover-account', ['Amare\Api\Controllers\SocialController', 'coverDinerAccount']],
    ['POST', '/social/account-covers/:id/confirm-payment', ['Amare\Api\Controllers\SocialController', 'confirmAccountCoverPayment']],
    ['GET', '/gift-products', ['Amare\Api\Controllers\SocialController', 'giftProducts']],
    ['POST', '/social-gifts', ['Amare\Api\Controllers\SocialController', 'createGiftPayment']],
    ['POST', '/social-gifts/:id/confirm-payment', ['Amare\Api\Controllers\SocialController', 'confirmGiftPayment']],
    
    // Promotions routes (app movil - SOLO LECTURA)
    ['GET', '/promotions', ['Amare\Api\Controllers\PromotionsController', 'index']],
    ['GET', '/promotions/:id', ['Amare\Api\Controllers\PromotionsController', 'show']],

    // Admin - Promotions routes (panel web, requiere rol=admin)
    ['POST', '/admin/promotions/upload', ['Amare\Api\Controllers\PromotionsController', 'uploadImage']],
    ['GET', '/admin/promotions', ['Amare\Api\Controllers\PromotionsController', 'adminIndex']],
    ['POST', '/admin/promotions', ['Amare\Api\Controllers\PromotionsController', 'adminStore']],
    ['POST', '/admin/promotions/validate', ['Amare\Api\Controllers\PromotionsController', 'adminValidateCode']],
    ['GET', '/admin/promotions/:id', ['Amare\Api\Controllers\PromotionsController', 'show']],
    ['PUT', '/admin/promotions/:id', ['Amare\Api\Controllers\PromotionsController', 'adminUpdate']],
    ['DELETE', '/admin/promotions/:id', ['Amare\Api\Controllers\PromotionsController', 'adminDestroy']],
    ['PUT', '/admin/promotions/:id/deactivate', ['Amare\Api\Controllers\PromotionsController', 'adminDeactivate']],

    // Admin - Users routes (panel web, requiere rol=admin)
    ['GET', '/admin/users', ['Amare\Api\Controllers\AdminController', 'users']],
    
    // Store routes (tienda de productos físicos)
    ['GET', '/store/categories', ['Amare\Api\Controllers\StoreController', 'categories']],
    ['GET', '/store/products', ['Amare\Api\Controllers\StoreController', 'products']],
    ['GET', '/store/products/:id', ['Amare\Api\Controllers\StoreController', 'showProduct']],
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
    
    if ($routeMethod !== $requestMethod) {
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
        'ok' => false,
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
        call_user_func_array([$controller, $method], array_values($routeParams));
    } else {
        $controller->$method();
    }
}
