# CarniHub

**Plataforma B2B de abasto inteligente de carne** — taquerías, carnicerías y restaurantes de Querétaro.

## Stack tecnológico

| Capa | Tecnología |
|---|---|
| Backend | PHP 7.4+ (puro, sin frameworks) |
| Base de datos | MySQL 5.7 |
| Frontend | HTML5 + CSS3 + Vanilla JS |
| Estilos | Tailwind CSS CDN + `public/css/carnihub.css` |
| Gráficas | Chart.js CDN |
| Mapas | Leaflet.js CDN |
| Arquitectura | MVC estricto (controladores, modelos, vistas) |
| Servidor | Apache con `mod_rewrite` |

---

## Instalación en cPanel

### 1. Subir archivos

1. Comprime el proyecto en un `.zip`
2. En cPanel → **Administrador de archivos** → sube a `public_html/` (o a un subdirectorio como `public_html/carnihub/`)
3. Descomprime en el servidor

### 2. Crear la base de datos

1. En cPanel → **MySQL Databases** → crea base de datos: `carnihub`
2. Crea usuario MySQL y asígnale **todos los privilegios** a `carnihub`
3. En cPanel → **phpMyAdmin** → selecciona `carnihub` → pestaña **Importar**
4. Sube y ejecuta `CarniHub_DB_Queretaro.sql`

### 3. Configurar credenciales de BD

Edita `config/database.php`:

```php
private string $host   = 'localhost';
private string $dbname = 'tu_usuario_carnihub';  // cPanel prefija: usuario_carnihub
private string $user   = 'tu_usuario_mysql';
private string $pass   = 'tu_password_mysql';
```

> **Nota cPanel:** el nombre de la BD y el usuario llevan el prefijo de tu cuenta cPanel, ej: `miusuario_carnihub`.

### 4. Verificar `.htaccess`

El `.htaccess` ya está configurado. Asegúrate de que `mod_rewrite` esté habilitado (en la mayoría de hostings cPanel sí lo está).

Si el proyecto está en un subdirectorio (ej. `public_html/carnihub/`), el `BASE_URL` se detecta automáticamente sin cambios.

### 5. Crear directorios de uploads

```bash
mkdir -p public/uploads/productos
mkdir -p public/uploads/evidencias
mkdir -p public/uploads/avatars
chmod 755 public/uploads -R
```

O desde cPanel → Administrador de archivos → crear carpetas y establecer permisos 755.

### 6. Verificar instalación

Abre `https://tudominio.com/carnihub/test_conexion.php` — debe mostrar todos los checks en verde.

---

## Credenciales de prueba

| Rol | Email | Contraseña |
|---|---|---|
| SuperAdmin | admin@carnihub.mx | admin123 |
| Comprador (Taquería) | juan.perez@carnihub.mx | admin123 |
| Repartidor | luis.martinez@carnihub.mx | admin123 |

---

## Estructura del proyecto

```
CarniHub/
├── .htaccess                    ← Apache rewrite
├── index.php                    ← Front Controller / Router
├── test_conexion.php            ← Diagnóstico del sistema
├── CarniHub_DB_Queretaro.sql    ← Schema + datos dummy Querétaro
│
├── config/
│   ├── config.php               ← URL base autodetección
│   └── database.php             ← PDO singleton
│
├── app/
│   ├── controllers/             ← Lógica de negocio
│   ├── models/                  ← Queries a BD
│   ├── views/                   ← HTML (sin lógica de BD)
│   │   ├── admin/               ← Panel admin
│   │   ├── cliente/             ← Portal cliente B2B
│   │   └── repartidor/          ← App repartidor (dark theme)
│   └── services/                ← APIs externas
│       ├── HikvisionService.php
│       ├── ShellyService.php
│       ├── WhatsAppService.php
│       ├── TraccarService.php
│       └── FacturaloService.php
│
└── public/
    ├── css/carnihub.css         ← Estilos custom
    ├── js/                      ← Módulos JS
    └── uploads/                 ← Imágenes subidas (755)
```

---

## Módulos incluidos

- **Autenticación** — Roles: SuperAdmin, AdminEmpresa, Comprador, Supervisor, Repartidor
- **Catálogo B2B** — Precios escalonados en tiempo real (AJAX)
- **Carrito multi-sucursal** — 1 pedido distribuido entre N sucursales
- **Checkout 4 pasos** — Carrito → Entrega → Resumen → Confirmación
- **Pedidos recurrentes** — Plantillas diarias/semanales/quincenales
- **Panel admin** — Dashboard KPIs, clientes, productos, pedidos, logística, inventario, reportes, usuarios
- **Logística** — Rutas con Leaflet.js, asignación de choferes, seguimiento Traccar GPS
- **Configuración global** — Todos los settings en BD (`global_settings`), sin tocar código
- **IoT** — N dispositivos HikVision + N dispositivos Shelly (CRUD dinámico)
- **App Repartidor** — Tema oscuro, firma digital canvas, foto evidencia, mapa Leaflet
- **Facturación CFDI** — Integración Factura-lo.mx
- **WhatsApp Business** — Notificaciones automáticas
- **Bitácora** — `action_logs` + `error_logs`

---

## APIs externas — Configuración

Una vez instalado, ve a **Admin → Configuración → APIs** y configura:

| API | Clave en global_settings |
|---|---|
| WhatsApp Business | `whatsapp_token`, `whatsapp_phone_id` |
| Traccar GPS | `traccar_url`, `traccar_user`, `traccar_pass` |
| Factura-lo CFDI | `facturalo_token`, `facturalo_rfc` |
| Shelly Cloud | Por dispositivo en `dispositivos_shelly` |
| HikVision | Por dispositivo en `dispositivos_hikvision` |

---

## Desarrollo local

```bash
# Con XAMPP / Laragon
# 1. Copia el proyecto a htdocs/carnihub/
# 2. Crea BD en phpMyAdmin e importa CarniHub_DB_Queretaro.sql
# 3. Abre http://localhost/carnihub/test_conexion.php
# 4. Abre http://localhost/carnihub/
```

---

## Licencia

Propiedad de **CarniHub / IMPACTOS DIGITALES** — Querétaro, México.
