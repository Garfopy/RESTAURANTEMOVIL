# Resumen de Migración: Node.js → PHP

## ✅ ¡Migración Completada!

Tu API ha sido completamente migrada de Node.js/Express a PHP 8.2+ compatible con cPanel.

## 📁 Estructura Creada

```
apps/api-php/
├── config/
│   ├── config.php           # Configuración general y CORS
│   └── database.php         # Conexión PDO a MySQL
├── src/
│   ├── controllers/         # 8 controladores
│   │   ├── AuthController.php
│   │   ├── BranchController.php
│   │   ├── MenuController.php
│   │   ├── OrderController.php
│   │   ├── PaymentController.php
│   │   ├── ProfileController.php
│   │   ├── FavoritesController.php
│   │   └── PromotionsController.php
│   ├── middleware/
│   │   ├── AuthMiddleware.php    # JWT authentication
│   │   └── ValidationMiddleware.php
│   ├── models/              # 7 modelos
│   │   ├── User.php
│   │   ├── Branch.php
│   │   ├── Category.php
│   │   ├── Product.php
│   │   ├── Order.php
│   │   ├── Favorite.php
│   │   └── Promotion.php
│   └── helpers/
│       └── Response.php     # Respuestas JSON estandarizadas
├── routes/
│   └── api.php              # Enrutador RESTful
├── uploads/                 # Para imágenes de productos
├── .htaccess                # Rewrite rules y seguridad
├── .env                     # Variables de entorno
├── .env.example             # Ejemplo de configuración
├── composer.json            # Dependencias de PHP
├── index.php                # Entry point
├── README.md                # Documentación completa
└── DEPLOY.md                # Guía de despliegue en cPanel
```

## 🔧 Endpoints Migrados

### ✅ Autenticación (5 endpoints)
- `POST /api/auth/register` - Registro de usuario
- `POST /api/auth/login` - Login con email/password
- `POST /api/auth/google` - Login con Google OAuth
- `GET /api/auth/me` - Obtener perfil actual (requiere auth)
- `PUT /api/auth/update-password` - Cambiar contraseña (requiere auth)

### ✅ Sucursales (2 endpoints)
- `GET /api/branches` - Listar todas las sucursales
- `GET /api/branches/:id` - Obtener detalles de sucursal

### ✅ Menú (3 endpoints)
- `GET /api/menu/categories` - Listar categorías
- `GET /api/menu/products` - Listar productos (con filtros opcionales)
- `GET /api/menu/products/:id` - Obtener detalle de producto

### ✅ Pedidos (3 endpoints)
- `GET /api/orders` - Listar pedidos del usuario (requiere auth)
- `GET /api/orders/:id` - Obtener detalle de pedido (requiere auth)
- `POST /api/orders` - Crear nuevo pedido (requiere auth)

### ✅ Pagos (2 endpoints)
- `POST /api/payments/create-intent` - Crear PaymentIntent de Stripe
- `POST /api/payments/webhook` - Webhook de Stripe para actualizaciones

### ✅ Perfil (3 endpoints)
- `GET /api/profile` - Obtener perfil de usuario (requiere auth)
- `PUT /api/profile` - Actualizar perfil (requiere auth)
- `GET /api/profile/orders` - Historial de pedidos (requiere auth)

### ✅ Favoritos (3 endpoints)
- `GET /api/favorites` - Listar favoritos del usuario (requiere auth)
- `POST /api/favorites/:product_id` - Agregar a favoritos (requiere auth)
- `DELETE /api/favorites/:product_id` - Eliminar de favoritos (requiere auth)

### ✅ Promociones (3 endpoints)
- `GET /api/promotions` - Listar promociones activas
- `GET /api/promotions/:id` - Obtener detalle de promoción
- `POST /api/promotions/validate` - Validar código promocional

## 🔐 Características de Seguridad

✅ **JWT Authentication** - Tokens JWT con expiración configurable  
✅ **Password Hashing** - Contraseñas hasheadas con `password_hash()` de PHP  
✅ **SQL Injection Protection** - Prepared statements con PDO  
✅ **CORS Configuration** - Headers CORS configurables  
✅ **Input Validation** - Validación de datos de entrada  
✅ **Error Handling** - Manejo seguro de errores (no expone detalles en producción)  
✅ **HTTPS Ready** - Listo for HTTPS en producción  

## 📦 Dependencias

```json
{
  "firebase/php-jwt": "^6.0",      // JWT tokens
  "stripe/stripe-php": "^13.0",    // Stripe payments
  "google/apiclient": "^2.0",      // Google OAuth
  "vlucas/phpdotenv": "^5.5"       // Variables de entorno
}
```

## 🚀 Próximos Pasos

### 1. Instalar dependencias localmente
```bash
cd apps/api-php
composer install --no-dev --optimize-autoloader
```

### 2. Configurar .env
Edita `apps/api-php/.env` con:
- Tus credenciales de base de datos de cPanel
- Tu dominio real en `APP_URL`
- Una clave JWT segura única
- Tus credenciales de Stripe (si usas pagos reales)
- Tus credenciales de Google OAuth (si usas login con Google)

### 3. Subir a cPanel
Sigue las instrucciones en `DEPLOY.md`:
- Sube todos los archivos via FTP/File Manager
- Configura la base de datos en cPanel
- Verifica que PHP 8.2 esté activo
- Prueba los endpoints

### 4. Actualizar aplicación móvil
Cambia la URL de la API en `apps/mobile/services/api.ts`:
```typescript
const API_URL = 'https://tudominio.com/api';
```

## 📊 Comparación Node.js vs PHP

| Característica | Node.js (Antes) | PHP (Ahora) |
|---------------|----------------|-------------|
| Lenguaje | JavaScript/TypeScript | PHP 8.2 |
| Framework | Express.js | PHP nativo + PDO |
| Autenticación | JWT (jsonwebtoken) | JWT (firebase/php-jwt) |
| Base de Datos | mysql2 | PDO MySQL |
| Stripe | stripe npm package | stripe/stripe-php |
| Google OAuth | google-auth-library | google/apiclient |
| Hosting | Requiere Node.js | Cualquier hosting cPanel |
| Performance | Excelente | Excelente (PHP 8.2) |

## ⚠️ Importante

1. **Base de Datos**: La estructura de la base de datos permanece igual, solo cambia la forma de conectar
2. ** JWT Secret**: Cambia `JWT_SECRET` en `.env` por una clave segura única
3. **Stripe**: Usa tus claves reales de Stripe (live keys) en producción
4. **HTTPS**: Asegúrate de usar HTTPS en producción para seguridad
5. **Backup**: Haz backup de tu base de datos antes de desplegar

## 🆘 Soporte

- Lee `README.md` para documentación completa
- Lee `DEPLOY.md` para guía paso a paso de despliegue
- Revisa los logs de error en cPanel si hay problemas
- Verifica que todas las extensiones PHP estén activas

## ✨ ¡Listo!

Tu API PHP está lista para ser desplegada en cPanel. Cualquier dispositivo podrá acceder a ella mediante:
```
https://tudominio.com/api
```

¡Éxito con el despliegue! 🎉