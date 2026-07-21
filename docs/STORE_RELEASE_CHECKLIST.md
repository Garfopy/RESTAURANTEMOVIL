# Lista de salida a App Store y Google Play

No envies una build hasta que todos los puntos marcados como obligatorios esten comprobados sobre la misma revision de Git.

## 1. Produccion y seguridad (obligatorio)

- [ ] Desplegar `apps/api-php` en el directorio fisico `api-php`, conservando la URL publica `/api_restaurante`.
- [ ] Ejecutar las migraciones `075` a `083` y verificar tablas, indices y claves foraneas.
- [ ] Configurar `APP_ENV=production`, `APP_DEBUG=false`, CORS explicito y un `JWT_SECRET` aleatorio de al menos 48 caracteres.
- [ ] Rotar la clave TLS retirada de `ragatha/SSL/certificate.key` y revocar las credenciales Stripe Test que estuvieron en Git.
- [ ] Limpiar esos secretos del historial antes de volver a dar acceso al repositorio a terceros.
- [ ] Confirmar que ninguna `sk_live`, `whsec`, clave privada, contraseña o token quede dentro de IPA/AAB, Git o logs.
- [ ] Mantener escritura publica solo en los directorios de carga necesarios.
- [ ] Probar los limites de login, recuperacion, registro, reportes y fotos; deben devolver `429` ante abuso.

## 2. Pagos y wallets (obligatorio si quedan visibles)

- [ ] EAS Production: `EXPO_PUBLIC_ENABLE_CARD_PAYMENTS=true`, `EXPO_PUBLIC_STRIPE_LIVE_MODE=true`, `EXPO_PUBLIC_ENABLE_NATIVE_WALLETS=true` y `pk_live`.
- [ ] PHP Production: `STRIPE_LIVE_MODE=true`, `sk_live` y secreto del webhook Live.
- [ ] Webhook Live activo en `https://amarerestaurant.club/api_restaurante/payments/webhook` con los cinco eventos documentados.
- [ ] Apple Pay: Merchant ID, certificado vigente, App ID asociado y provisioning profile regenerado.
- [ ] Google Pay habilitado en Stripe y probado en Android certificado con Google Wallet, Play Services actualizados y una tarjeta compatible.
- [ ] Probar tarjeta, Apple Pay, Google Pay, 3DS, rechazo, cancelacion, doble toque, cierre de app y perdida de red.
- [ ] Ejecutar un cobro Live pequeno y un reembolso; comprobar Stripe, pedido, puntos, saldo y webhook.
- [ ] Repetir eventos webhook y confirmar que no duplican pedidos, regalos, puntos ni recargas.

## 3. Cuenta, privacidad y contenido social (obligatorio)

- [ ] Probar correo, Google y Apple; Apple debe solicitar nombre cuando Apple ya no lo vuelve a entregar.
- [ ] Probar cuenta suspendida con la app abierta y cerrada, reactivacion y eliminacion de cuenta.
- [ ] Publicar y revisar `/soporte/`, `/aviso-de-privacidad/`, `/legal/terminos/` y `/eliminar-cuenta/`.
- [ ] Completar en los textos legales la razon social, domicilio y contacto responsable con asesoria juridica; no usar datos inventados.
- [ ] Declarar de forma consistente los datos recogidos en App Privacy y Data Safety: cuenta, contacto, ubicacion, fotos, contenido social, compras, identificadores y diagnostico que realmente se recopilen.
- [ ] Mantener marketing apagado por defecto y comprobar que se puede retirar el consentimiento.
- [ ] Probar reporte, bloqueo, reglas de comunidad y filtrado de texto.
- [ ] Conectar la web de moderacion a `GET /admin/social/photos` y `POST /admin/social/photos/{id}/decision`; ninguna foto pendiente debe publicarse.
- [ ] Definir una persona responsable y un tiempo operativo de respuesta para reportes.

## 4. Calidad de la build (obligatorio)

- [ ] Arbol Git limpio y commit identificado; EAS Production debe rechazar builds con cambios sin commit.
- [ ] TypeScript, ESLint, Expo Doctor, export iOS/Android y lint PHP sin errores.
- [ ] Revisar IPA: icono opaco, Apple Pay entitlement, Apple Sign In, notificaciones y privacy manifest.
- [ ] Revisar AAB firmado con credenciales de Play, no con debug; verificar Google Pay en esa build.
- [ ] Probar iPhone pequeno/grande y Android pequeno/grande, texto ampliado, teclado, camara y permisos denegados.
- [ ] Probar foreground, background y app cerrada para notificaciones; confirmar sonido en dispositivo sin silencio ni Focus.
- [ ] Probar modo sin internet y reintento de las operaciones principales.
- [ ] Confirmar que cliente, mesero y hostess solo ven las acciones de su rol.

## 5. App Store Connect y Play Console (obligatorio)

- [ ] Crear cuentas permanentes de revision para cliente, mesero y hostess.
- [ ] Adjuntar un QR de mesa vigente e instrucciones cortas para pedidos, social, reportes y moderacion.
- [ ] Informar que Stripe cobra bienes y servicios fisicos del restaurante.
- [ ] Completar descripcion, palabras clave, categoria, clasificacion de edad, copyright y datos de contacto.
- [ ] Cargar capturas reales sin marcos enganosos ni funciones no disponibles.
- [ ] Usar `https://amarerestaurant.club/soporte/` como Support URL y las URLs publicas correctas para privacidad y eliminacion.
- [ ] Completar App Privacy, Data Safety, Content Rights y cuestionarios de contenido social sin omitir datos.
- [ ] Verificar politicas de privacidad, terminos y correo de soporte desde una red externa.
- [ ] Subir primero a TestFlight y prueba interna de Play; enviar a revision solo despues de firmar esta lista.

## Bloqueadores externos que el codigo no puede confirmar

Apple Developer, App Store Connect, Play Console, Stripe Live, DNS/hosting, credenciales EAS, datos legales, cuentas de revision y operacion humana de moderacion deben validarse manualmente. Una build tecnicamente correcta no sustituye esas comprobaciones.
