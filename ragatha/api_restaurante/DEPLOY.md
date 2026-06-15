# Guía de Despliegue en cPanel

## Paso 1: Preparación Local

### 1.1 Instalar dependencias
```bash
cd apps/api-php
composer install --no-dev --optimize-autoloader
```

### 1.2 Configurar .env
Edita el archivo `.env` con los datos de tu hosting:

```env
# Base de datos (usa los datos de cPanel)
DB_HOST=localhost
DB_PORT=3306
DB_USER=usuario_cpanel_basedatos
DB_PASS=contraseña_cpanel
DB_NAME=usuario_cpanel_nombre_basedatos

# API
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tudominio.com/api

# JWT (genera una clave segura)
JWT_SECRET=tu_clave_secreta_muy_larga_y_segura_cambiala_en_produccion
JWT_EXPIRY=720

# Google OAuth (si usas login con Google)
GOOGLE_CLIENT_ID=tu_client_id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=tu_client_secret

# Stripe
STRIPE_SECRET_KEY=sk_live_... (usa tu clave real de Stripe)
STRIPE_WEBHOOK_SECRET=whsec_... (configura tu webhook secret)

# CORS
CORS_ORIGIN=https://tudominio.com

# Uploads
UPLOAD_MAX_SIZE=10485760
UPLOAD_DIR=uploads
```

## Paso 2: Subir Archivos a cPanel

### Opción A: Usando File Manager
1. Inicia sesión en cPanel
2. Ve a **File Manager**
3. Navega a `public_html` (o al directorio de tu dominio)
4. Crea una carpeta llamada `api`
5. Sube todos los archivos de `apps/api-php/` a esta carpeta
6. **Importante:** Sube también la carpeta `vendor/` completa

### Opción B: Usando FTP
1. Conéctate via FTP (FileZilla, Cyberduck, etc.)
2. Navega a `/public_html/api/`
3. Sube todos los archivos

## Paso 3: Configurar Base de Datos en cPanel

1. En cPanel, ve a **MySQL® Databases**
2. Crea una nueva base de datos (ej: `usuario_amare`)
3. Crea un usuario de base de datos (ej: `usuario_api`)
4. Asigna el usuario a la base de datos con **TODOS** los privilegios
5. Anota:
   - Nombre de la base de datos (usualmente `usuario_amare`)
   - Usuario (usualmente `usuario_api`)
   - Contraseña

## Paso 4: Verificar Configuración

### 4.1 Verificar PHP
En cPanel, ve a **Select PHP Version** o **Setup Node.js App**:
- Asegúrate de que PHP 8.2 esté seleccionado
- Verifica que estas extensiones estén activas:
  - ✅ pdo_mysql
  - ✅ json
  - ✅ openssl
  - ✅ mbstring
  - ✅ curl
  - ✅ zip (necesaria para Composer)

### 4.2 Verificar .htaccess
El archivo `.htaccess` debe estar en la raíz de `api/`. Si no funciona:
1. En cPanel File Manager, asegúrate de ver archivos ocultos
2. Verifica que el archivo exista
3. Permisos: 644

## Paso 5: Probar la API

### 5.1 Health Check
Abre en tu navegador:
```
https://tudominio.com/api/health
```

Deberías ver:
```json
{
  "success": true,
  "message": "API funcionando",
  "version": "1.0.0"
}
```

### 5.2 Probar endpoints básicos

**Listar sucursales:**
```bash
curl https://tudominio.com/api/branches
```

**Listar categorías:**
```bash
curl https://tudominio.com/api/menu/categories
```

## Paso 6: Configurar Aplicación Móvil

Actualiza la URL de la API en `apps/mobile/services/api.ts`:

```typescript
const API_URL = 'https://tudominio.com/api';
```

## Paso 7: Configurar Stripe Webhook

1. Ve al Dashboard de Stripe
2. Developers → Webhooks
3. Add endpoint: `https://tudominio.com/api/payments/webhook`
4. Selecciona eventos: `payment_intent.succeeded`, `payment_intent.payment_failed`
5. Copia el **Signing secret** y actualiza tu `.env`:
   ```env
   STRIPE_WEBHOOK_SECRET=whsec_...
   ```

## Paso 8: Configurar Google OAuth

1. Ve a Google Cloud Console
2. Credentials → OAuth 2.0 Client IDs
3. Agrega tu dominio en **Authorized domains**: `tudominio.com`
4. Actualiza `.env`:
   ```env
   GOOGLE_CLIENT_ID=tu_client_id.apps.googleusercontent.com
   GOOGLE_CLIENT_SECRET=tu_client_secret
   ```

## Solución de Problemas Comunes

### Error 500 - Internal Server Error
**Causa:** Extensiones PHP faltantes o error en el código

**Solución:**
1. Revisa los logs de error en cPanel (Error Log o logs/ directory)
2. Verifica que todas las extensiones PHP estén activas
3. Asegúrate de que el archivo `.env` esté configurado correctamente

### Error 404 - Not Found
**Causa:** .htaccess no está funcionando

**Solución:**
1. Verifica que `AllowOverride All` esté activado en tu hosting
2. Asegúrate de que `mod_rewrite` esté habilitado
3. Comprueba que el archivo `.htaccess` exista y tenga permisos 644

### Error de conexión a base de datos
**Causa:** Credenciales incorrectas

**Solución:**
1. Verifica que los datos en `.env` coincidan con los de cPanel
2. Asegúrate de que el usuario tenga todos los privilegios
3. El host usualmente es `localhost` en cPanel

### Error "JWT_SECRET not set"
**Causa:** Variable de entorno faltante

**Solución:**
1. Asegúrate de que el archivo `.env` esté en la raíz de `api/`
2. Verifica que `JWT_SECRET` esté configurada
3. Los permisos del archivo `.env` deben ser 644

### Errores de Composer
**Causa:** Dependencias no instaladas

**Solución:**
1. Si no tienes SSH, instala localmente y sube `vendor/`
2. Asegúrate de subir TODA la carpeta `vendor/`
3. Verifica que `composer.json` esté en la raíz

## Permisos Recomendados

```bash
# Carpetas: 755
chmod 755 .
chmod 755 config
chmod 755 src
chmod 755 routes
chmod 755 uploads

# Archivos: 644
chmod 644 .htaccess
chmod 644 .env
chmod 644 index.php
chmod 644 composer.json
```

## Verificación Final

✅ La API responde en `https://tudominio.com/api/health`  
✅ Los endpoints básicos funcionan (branches, menu)  
✅ La autenticación JWT funciona  
✅ Stripe está configurado correctamente  
✅ Google OAuth está configurado (si se usa)  
✅ La aplicación móvil se conecta a la nueva URL  
✅ Los uploads tienen permisos de escritura  

## Soporte

Si encuentras problemas:
1. Revisa los logs de error de cPanel
2. Verifica la configuración paso a paso
3. Contacta a tu proveedor de hosting si hay problemas de configuración del servidor