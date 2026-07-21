# Stripe Live en Amare

## 1. Requisitos en Stripe

1. Completar la activacion de la cuenta de Stripe: negocio, representante, cuenta bancaria y datos fiscales.
2. Cambiar el Dashboard de Stripe a modo live.
3. En Developers > API keys, obtener:
   - `pk_live_...`: clave publicable para la app movil.
   - `sk_live_...`: clave secreta exclusiva del servidor.
4. No colocar `sk_live_...` ni `whsec_...` en Expo, React Native, Git o variables `EXPO_PUBLIC_*`.

## 2. Backend PHP

Configurar en el `.env` real de `apps/api-php`:

```dotenv
APP_ENV=production
STRIPE_LIVE_MODE=true
STRIPE_SECRET_KEY=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...
```

`STRIPE_SECRET_KEY` del entorno tiene prioridad. Si no existe, la API puede leer
`global_settings.stripe_secret_key`, que es el valor guardado por el panel web.
Para produccion se recomienda el `.env` del servidor porque permite rotar y aislar
la clave sin depender de la base de datos.

## 3. Webhook live

En Stripe, crear un endpoint live con esta URL:

```text
https://amarerestaurant.club/api_restaurante/payments/webhook
```

Suscribir como minimo:

- `payment_intent.succeeded`
- `payment_intent.payment_failed`

Copiar el signing secret `whsec_...` del endpoint a `STRIPE_WEBHOOK_SECRET` y
reiniciar PHP/PHP-FPM si el hosting conserva variables en memoria. El webhook no
usa JWT de usuario: valida `Stripe-Signature` con ese secreto.

## 4. Panel web de Amare

En Credenciales Stripe:

- Publishable Key: `pk_live_...`
- Secret Key: `sk_live_...`
- Metodo habilitado: tarjeta

Dejar Apple Pay y Google Pay desactivados hasta registrar sus dominios,
merchant IDs y capacidades nativas. Guardar la llave publica en el panel no
actualiza un APK ya compilado; la app recibe su llave mediante EAS.

## 5. Variables EAS de produccion

Desde `apps/mobile`:

```bash
eas env:create --environment production --name EXPO_PUBLIC_STRIPE_KEY --value pk_live_... --visibility plaintext
eas env:create --environment production --name EXPO_PUBLIC_ENABLE_CARD_PAYMENTS --value true --visibility plaintext
eas env:create --environment production --name EXPO_PUBLIC_STRIPE_LIVE_MODE --value true --visibility plaintext
eas env:create --environment production --name EXPO_PUBLIC_ENABLE_NATIVE_WALLETS --value false --visibility plaintext
eas env:list --environment production
```

La clave publicable puede ser visible porque forma parte del binario. Nunca crear
una variable `EXPO_PUBLIC_STRIPE_SECRET_KEY`. Las variables `EXPO_PUBLIC_*` se
integran en el build, por lo que cambiar la llave requiere generar otro APK/AAB.

## 6. Generar builds live

```bash
eas build --platform android --profile production
eas build --platform ios --profile production
```

No promover el APK `preview-apk` a tiendas: ese perfil conserva la llave de prueba
y los pagos con tarjeta desactivados.

## 7. Flujo implementado

1. La app crea primero el pedido autenticado mediante `POST /orders`.
2. El backend ignora precios y totales del cliente, consulta el catalogo y guarda
   en el pedido un snapshot con productos, modificadores, promocion y total.
3. La app solicita `POST /payments/create-intent` usando ese `order_id`.
4. El backend crea un PaymentIntent idempotente en MXN desde el snapshot, lo asocia
   inmediatamente al pedido y devuelve `client_secret`, `payment_intent_id` e importe.
5. Si el importe oficial difiere del mostrado, la app actualiza el total y exige
   una nueva confirmacion; Stripe no se confirma en ese primer intento.
6. La app confirma la tarjeta con CardField. Los datos de tarjeta no pasan por Amare.
7. La app llama `POST /orders/{id}/confirm-payment` y el backend recupera el intent
   desde Stripe para validar usuario, pedido, moneda, importe y estado `succeeded`.
8. El webhook firmado sirve como confirmacion asincrona si la app se cierra o se
   pierde la conexion despues del cobro.

Los pedidos para recoger usan el mismo flujo autenticado de `/orders`; ya no se
confia en un booleano `pagado` enviado por el dispositivo.

## 8. Archivos principales

- `apps/api-php/src/Services/StripeConfig.php`: carga y valida secretos/mode live.
- `apps/api-php/src/Controllers/PaymentController.php`: cotiza y crea intents; procesa webhook.
- `apps/api-php/src/Controllers/OrderController.php`: verifica el pago antes de confirmar pedido.
- `apps/mobile/constants/stripe.ts`: habilita tarjeta y separa wallets nativos.
- `apps/mobile/services/orders.service.ts`: contrato entre app y API de pagos.
- `apps/mobile/app/checkout/payment.tsx`: pago de menu y pickup.
- `apps/mobile/app/checkout/payment-store.tsx`: pago de productos de tienda.
- `apps/mobile/eas.json`: perfil de build que consume el entorno EAS `production`.

## 9. Checklist antes de publicar

1. Probar primero en test mode con llaves `pk_test_` y `sk_test_`.
2. Verificar tarjeta aprobada, rechazada, autenticacion 3DS, doble toque y perdida de red.
3. Confirmar que un total manipulado por el cliente no modifica el cobro.
4. Revisar en Stripe que el webhook live entregue HTTP 200.
5. Confirmar en DB el mismo `payment_intent_id`, total, moneda y usuario del pedido.
6. Hacer una compra real legitima de bajo importe tras activar live y comprobar pedido, recibo y reembolso.
7. Rotar cualquier secreto que haya estado en ZIP, logs, commits o capturas.
