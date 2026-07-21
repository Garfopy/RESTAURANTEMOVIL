# Stripe Live, Apple Pay y Google Pay en Amare

## Arquitectura implementada

- La app crea primero un pedido pendiente y PHP calcula el total desde la base de datos.
- Stripe PaymentSheet muestra tarjeta y, cuando el dispositivo es compatible, Apple Pay o Google Pay.
- La app nunca recibe `sk_live`, `whsec`, numeros de tarjeta ni criptogramas.
- PHP valida usuario, pedido, importe, MXN, metadata, estado y modo Live.
- El webhook termina pedidos, puntos, recargas, regalos, cuentas sociales y solicitudes de factura aunque la app se cierre.
- Los eventos, reembolsos e incidentes quedan registrados de forma idempotente.

## Variables EAS

No guardes la clave real en `eas.json`. Configura el entorno Production desde `apps/mobile`:

```bash
eas env:create --environment production --name EXPO_PUBLIC_STRIPE_KEY --value pk_live_REEMPLAZAR --visibility plaintext
eas env:create --environment production --name EXPO_PUBLIC_ENABLE_CARD_PAYMENTS --value true --visibility plaintext
eas env:create --environment production --name EXPO_PUBLIC_STRIPE_LIVE_MODE --value true --visibility plaintext
eas env:create --environment production --name EXPO_PUBLIC_ENABLE_NATIVE_WALLETS --value true --visibility plaintext
eas env:list --environment production
```

Preview debe usar su propia `pk_test` en EAS Preview y `EXPO_PUBLIC_STRIPE_LIVE_MODE=false`. Cada cambio de variable publica requiere una build nueva.

## Variables PHP de produccion

En el `.env` real del directorio fisico `api-php`:

```dotenv
APP_ENV=production
STRIPE_LIVE_MODE=true
STRIPE_SECRET_KEY=sk_live_REEMPLAZAR
STRIPE_WEBHOOK_SECRET=whsec_REEMPLAZAR
```

Nunca uses prefijos `EXPO_PUBLIC_` para secretos. Reinicia PHP/PHP-FPM si el hosting conserva variables en memoria.

## Migraciones obligatorias

Ejecuta en orden:

1. `075_create_stripe_webhook_events.sql`
2. `076_split_amare_wallet_balances.sql`
3. `077_track_stripe_refunds.sql`
4. `078_create_stripe_pending_invoices.sql`
5. `079_create_stripe_refund_audit.sql`
6. `080_create_stripe_payment_incidents.sql`

La migracion 076 convierte el saldo anterior a Live en promocional. No reviertas esa clasificacion manualmente.

## Webhook Live

En Stripe Workbench crea el endpoint:

```text
https://amarerestaurant.club/api_restaurante/payments/webhook
```

Eventos requeridos:

- `payment_intent.succeeded`
- `payment_intent.payment_failed`
- `payment_intent.canceled`
- `charge.refunded`
- `charge.dispute.created`

El endpoint no usa JWT; valida `Stripe-Signature`. Confirma en Stripe que cada entrega responda HTTP 200. Los fallos quedan en `stripe_webhook_events` y Stripe debe reintentarlos.

## Apple Pay

1. En Apple Developer crea `merchant.com.amare.app`.
2. Asocialo al App ID `com.amare.app` y activa Apple Pay.
3. Completa el certificado de procesamiento desde Stripe.
4. Regenera el provisioning profile de EAS.
5. Verifica en la IPA el entitlement `com.apple.developer.in-app-payments`.

No se requiere verificar dominio para Apple Pay nativo. La wallet solo aparece en dispositivo real con una tarjeta compatible.

## Google Pay

1. Habilita Google Pay en Stripe.
2. Usa una build nativa; no funciona en Expo Go.
3. En Preview se usa `testEnv`; Production fuerza modo real.
4. Verifica un dispositivo Android con Google Wallet configurado.

## Operacion y reembolsos

- Las recargas validas las entrega `GET /rewards/wallet`; el telefono no decide importes arbitrarios.
- El saldo promocional se consume antes que el comprado.
- Solo el saldo comprado no utilizado puede volver al metodo Stripe original.
- Administración usa `POST /admin/rewards/refunds` con `user_id`, `amount_mxn`, `reason` y un `request_key` unico.
- Un pago exitoso no aplicado puede reconciliarse con `POST /admin/payments/reconcile` y su `payment_intent_id`.
- Al eliminar una cuenta, el reembolso del saldo comprado se inicia antes de anonimizarla.

## Verificacion antes de tiendas

1. Ejecuta TypeScript, Expo Doctor, `expo config --type introspect` y lint PHP.
2. Prueba tarjeta, Apple Pay, Google Pay, 3DS, rechazo, cancelacion y doble toque.
3. Corta la red despues de PaymentSheet y cierra la app; el webhook debe completar el pedido sin duplicarlo.
4. Repite el mismo webhook y la misma confirmacion; no deben duplicarse puntos, saldo, factura, QR ni regalo.
5. Intenta alterar usuario, importe, moneda, metodo y PaymentIntent.
6. Prueba menu, pickup, tienda, regalo, cubrir cuenta y recarga.
7. Haz un cobro Live pequeno y despues un reembolso real.
8. Revisa la IPA/AAB: debe contener `pk_live`, nunca `sk_live`, `whsec` ni claves Test.
9. Genera TestFlight y APK interno antes de crear las builds candidatas.

## Builds

```bash
eas build --platform ios --profile production
eas build --platform android --profile production
```

No uses `preview-apk` para tiendas. Stripe Live, Merchant ID, certificado Apple Pay, webhook y variables EAS son configuracion externa: el codigo no puede confirmarlos sin entrar a esas cuentas.
