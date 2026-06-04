# Amare API - PHP

API REST para la aplicación Amare, desarrollada en PHP 8.2+ compatible con hosting cPanel.

## Requisitos

- PHP 8.2 o superior
- MySQL 5.7+ o MariaDB 10.2+
- Composer
- Extensiones PHP: PDO, PDO_MySQL, JSON, OpenSSL, mbstring

## Instalación

### 1. Subir archivos al hosting

Sube todos los archivos a tu hosting cPanel (recomendado: en una carpeta llamada `api` en el directorio público).

### 2. Configurar base de datos

1. Crea una base de datos MySQL en cPanel
2. Crea un usuario y asígnale privilegios a la base de datos
3. Actualiza el archivo `.env` con las credenciales:

```env
DB_HOST=localhost
DB_PORT=3306
DB_USER=tu_usuario
DB_PASS=tu_contraseña
DB_NAME=tu_base_de_datos
```

### 3. Instalar dependencias

Si tienes acceso SSH, ejecuta:

```bash
cd public_html/api
composer install --no-dev --optimize-autoloader
```

Si no tienes SSH, instala las dependencias localmente y sube la carpeta `vendor`:

```bash
# En tu computadora local
composer install --no-dev --optimize-autoloader

# Sube la carpeta vendor completa via FTP/File Manager
```

### 4. Configurar .htaccess

El archivo `.htaccess` ya está configurado. Asegúrate de que `AllowOverride All` esté activado en tu hosting.

### 5. Permisos de carpetas

Establece permisos 755 para carpetas y 644 para archivos:

```bash
chmod -R 755 .
chmod 644 .env
```

## Estructura del Proyecto

```
api/
├── config/
│   ├── config.php          # Configuración general
│   └── database.php        # Conexión a base de datos
├── src/
│   ├── controllers/        # Controladores de la API
│   ├── middleware/         # Middleware (auth, validación)
│   ├── models/             # Modelos de datos
│   └── helpers/            # Funciones auxiliares
├── routes/
│   └── api.php             # Definición de rutas
├── uploads/                # Imágenes de productos
├── .htaccess               # Configuración Apache
├── .env                    # Variables de entorno
├── .env.example            # Ejemplo de variables
├── composer.json           # Dependencias
└── index.php               # Entry point
```

## Endpoints de la API

### Autenticación
- `POST /api/auth/register` - Registro de usuario
- `POST /api/auth/login` - Login
- `POST /api/auth/google` - Login con Google
- `GET /api/auth/me` - Obtener perfil actual
- `PUT /api/auth/update-password` - Actualizar contraseña

### Sucursales
- `GET /api/branches` - Listar sucursales
- `GET /api/branches/:id` - Obtener sucursal

### Menú
- `GET /api/menu/categories` - Listar categorías
- `GET /api/menu/products` - Listar productos
- `GET /api/menu/products/:id` - Obtener producto

### Pedidos
- `GET /api/orders` - Listar pedidos del usuario
- `GET /api/orders/:id` - Obtener pedido
- `POST /api/orders` - Crear pedido

### Pagos
- `POST /api/payments/create-intent` - Crear payment intent de Stripe
- `POST /api/payments/webhook` - Webhook de Stripe

### Perfil
- `GET /api/profile` - Obtener perfil
- `PUT /api/profile` - Actualizar perfil
- `GET /api/profile/orders` - Historial de pedidos

### Favoritos
- `GET /api/favorites` - Listar favoritos
- `POST /api/favorites/:product_id` - Agregar a favoritos
- `DELETE /api/favorites/:product_id` - Eliminar de favoritos

### Promociones
- `GET /api/promotions` - Listar promociones
- `GET /api/promotions/:id` - Obtener promoción
- `POST /api/promotions/validate` - Validar código promocional

## Configuración del Cliente Móvil

Actualiza la URL de la API en tu aplicación móvil:

```typescript
// apps/mobile/services/api.ts
const API_URL = 'https://tudominio.com/api';
```

## Seguridad

- La API usa JWT para autenticación
- Las contraseñas se hashean con bcrypt
- Se recomienda usar HTTPS en producción
- Configura correctamente los CORS en `.env`

## Solución de Problemas

### Error 500
- Verifica que las extensiones PHP estén activadas
- Revisa los logs de error de Apache
- Asegúrate de que el archivo `.env` esté configurado

### Error de conexión a base de datos
- Verifica las credenciales en `.env`
- Asegúrate de que el usuario tenga privilegios
- Comprueba que el host sea correcto (usualmente `localhost`)

### Rutas no encontradas (404)
- Verifica que `.htaccess` esté funcionando
- Asegúrate de que `AllowOverride All` esté activado
- Comprueba que `mod_rewrite` esté habilitado

## Soporte

Para problemas o preguntas, contacta al equipo de desarrollo.