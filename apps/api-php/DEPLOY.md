# Despliegue de la API PHP de Amare

## Rutas de produccion

- Directorio fisico en el servidor: `api-php`.
- URL publica usada por la app: `https://amarerestaurant.club/api_restaurante`.
- No cambies la URL publica a `/api-php`; el alias o rewrite del servidor conserva `/api_restaurante`.

## Preparacion

```bash
cd apps/api-php
composer install --no-dev --optimize-autoloader
```

El `.env` de produccion debe permanecer fuera de Git:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://amarerestaurant.club/api_restaurante
CORS_ORIGIN=https://amarerestaurant.club
JWT_SECRET=GENERAR_UN_VALOR_ALEATORIO_DE_64_CARACTERES_O_MAS
JWT_EXPIRY=720
APPLE_CLIENT_ID=com.amare.app
STRIPE_LIVE_MODE=true
STRIPE_SECRET_KEY=sk_live_REEMPLAZAR
STRIPE_WEBHOOK_SECRET=whsec_REEMPLAZAR
```

`JWT_SECRET` es obligatorio y debe tener al menos 48 caracteres. La API se negara a autenticar si falta. No uses `CORS_ORIGIN=*` en produccion.

## Archivos

1. Sube el contenido de `apps/api-php/` al directorio fisico `api-php` del hosting.
2. Incluye `vendor/`, `.htaccess`, `routes/`, `src/`, las paginas legales y las migraciones.
3. Conserva el `.env` real del servidor; no lo reemplaces con `.env.example`.
4. Da permisos de escritura solo a `uploads/`. Archivos `644`, directorios `755`.
5. Nunca subas archivos `.key`, keystores, certificados privados ni credenciales JSON dentro del sitio publico.

## Migraciones

Ejecuta todas las migraciones pendientes en orden. Para esta entrega son obligatorias:

1. `075_create_stripe_webhook_events.sql`
2. `076_split_amare_wallet_balances.sql`
3. `077_track_stripe_refunds.sql`
4. `078_create_stripe_pending_invoices.sql`
5. `079_create_stripe_refund_audit.sql`
6. `080_create_stripe_payment_incidents.sql`
7. `081_create_api_rate_limits.sql`
8. `082_create_social_photo_moderation.sql`
9. `083_add_stripe_payment_state_to_orders.sql`

Verifica que las tablas y columnas existan antes de publicar la nueva build. La migracion `082` usa `INT UNSIGNED` para coincidir con `mobile_usuarios.id`; la `083` conserva fallos, cancelaciones, reembolsos y disputas de Stripe en cada pedido.

## Verificacion

```bash
curl -i https://amarerestaurant.club/api_restaurante/branches
```

Debe responder HTTP `200` y JSON. No existe un endpoint `/health` en esta API.

Comprueba ademas:

- Login por correo, Google y Apple.
- Cuenta suspendida y eliminacion de cuenta.
- Subida de foto social: debe quedar `pending` en `social_photo_moderation`.
- Lista administrativa: `GET /admin/social/photos?status=pending`.
- Decision administrativa: `POST /admin/social/photos/{id}/decision` con `decision=approved` o `rejected`.
- Limites de intentos: una rafaga debe terminar en HTTP `429`.

## Stripe

Webhook Live:

```text
https://amarerestaurant.club/api_restaurante/payments/webhook
```

Eventos: `payment_intent.succeeded`, `payment_intent.payment_failed`, `payment_intent.canceled`, `charge.refunded` y `charge.dispute.created`.

Confirma entregas HTTP `200`, realiza un cobro Live pequeno y un reembolso. Repetir un evento no debe duplicar pedidos, puntos ni saldo.

## Paginas web

Las paginas de soporte, privacidad, terminos y eliminacion pertenecen a la raiz web publica, no al directorio visible de la API:

- `/soporte/`
- `/aviso-de-privacidad/`
- `/legal/terminos/`
- `/eliminar-cuenta/`

Sube `ragatha/public/soporte/index.html` a la carpeta publica `/soporte/` para conservar el logo y la informacion de moderacion actualizados.
