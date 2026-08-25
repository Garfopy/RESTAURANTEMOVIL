# Plan de recorte — UTEQ Cafetería (app móvil)

De plataforma multi-módulo (mesas, meseros, anfitrión, social, multi-sucursal) a una app de
cliente enfocada en un solo flujo: **ver menú → pagar con Stripe → el pedido llega a cocina**.

Todo lo eliminado sigue vivo en el historial de git — si algo se necesita de vuelta, se
recupera del commit, no hay que reescribirlo desde cero.

Repo: `RESTAURANTEMOVIL` · rama base: `mejoras-amare-app`

---

## 0. Qué se queda, qué se va

### Se queda

- Login por correo, menú (comida / bebidas / dulcería), carrito
- Checkout: **pickup** y **delivery** (con direcciones guardadas)
- Pago con Stripe (modo prueba), cupones/promociones
- Estado del pedido → alimenta el KDS de cocina
- Historial de pedidos, favoritos, perfil, ayuda

### Se va

- App de mesero y app de anfitrión/portero completas
- Mesas, códigos QR, dine-in, división de cuenta
- Modo social (perfiles, likes, regalos entre mesas)
- Selección de sucursal / multi-sede para el cliente
- Reservaciones

---

## 1. Sprints por rama

Sprint 0 corre en paralelo a todo. Sprints 1–4 parten de `main` al mismo tiempo — solo 3 y 4
se coordinan entre sí (ambos tocan el home). Sprint 5 es la integración final.

### Sprint 0 — Infraestructura y cuentas

**Rama:** `feature/sprint-0-infra`
**Dependencias:** independiente · no bloquea a 1–4 · bloquea el paso de base de datos de Sprint 5.

Tareas:

- [ ] Pegar la llave `pk_test_` real en `apps/mobile/.env` (ya está en modo prueba, solo falta el valor)
- [ ] En el servidor: `STRIPE_SECRET_KEY=sk_test_...` y `STRIPE_LIVE_MODE=false` en el `.env` de `apps/api-php`
- [ ] Apple Developer: registrar App ID `com.uteq.cafeteria` + Merchant ID `merchant.com.uteq.cafeteria`
- [ ] App Store Connect: crear la app nueva (bundle id de arriba)
- [ ] Provisionar la base de datos vacía de UTEQ corriendo `000_baseline_schema_uteq.sql` (ver más abajo) — es el esquema completo desde cero, sin datos de otro cliente
- [ ] Si en vez de partir de cero clonas la base de otro cliente ya existente, usa `086_trim_schema_uteq.sql` para recortarla en lugar de la 000
- [ ] Una vez que la base tenga tu propio restaurante/categorías (ver plantillas al final de `000_baseline_schema_uteq.sql`), correr `085_reduce_menu_scope_to_comida_bebidas_dulceria.sql` para dejar solo comida/bebidas/dulcería — revisar el `SELECT` antes de los `UPDATE`

### Sprint 1 — Eliminar app de mesero y anfitrión

**Rama:** `feature/sprint-1-remove-staff-apps`
**Dependencias:** ninguna, son módulos autocontenidos.

Borrar completo:

- [x] `app/(waiter)/` completo (layout, index, orders, gifts, table/[id])
- [x] `app/(hostess)/` completo (layout, index, tables)
- [x] `services/waiter.service.ts`, `services/hostess.service.ts`
- [x] `store/waiter-cart.store.ts`
- [x] `components/waiter/` completo, `components/staff/StaffTabBar.tsx`

Cirugía (editar, no borrar):

- [x] `store/user.store.ts` — quitar la llamada al wallet-cart de mesero dentro de `clearSessionState()` (~línea 110)
- [x] `app/_layout.tsx` — quitar los redirects por rol (mesero/anfitrión) y los `Stack.Screen` de `(waiter)`/`(hostess)`

Nota: `components/shared/TableSessionRuntime.tsx`, `GlobalCartButton.tsx` y
`GlobalSocialNotifications.tsx` siguen con checks de rol `mesero`/`anfitrión` —
son inofensivos ahora (esos roles ya no existen), se limpian en los Sprints 2 y 3
como marca el plan, no se tocaron aquí para no salirse del alcance del Sprint 1.

### Sprint 2 — Eliminar modo social

**Rama:** `feature/sprint-2-remove-social`
**Dependencias:** ninguna.

Borrar completo:

- [x] `app/profile/social.tsx` (7,100+ líneas) y `app/(tabs)/social.tsx`
- [x] `services/social-account.service.ts`, `services/social-gifts.service.ts`
- [x] `components/shared/GlobalSocialNotifications.tsx`

Cirugía:

- [x] `app/_layout.tsx` — quitar el `<GlobalSocialNotifications/>` montado globalmente y su import
- [x] Revisar `packages/types` / `services/auth.service.ts` por campos de perfil social
      (`social_photos`, `edad`, `genero`, `sexualidad`, `gustos`, `biografia`, `que_busca`,
      `redes_sociales`, `instagram`, `tiktok`, `social_consent_*`) — quitados del tipo
      `MobileUser` y del mapeo de `auth.service.ts`. **`is_social_active`/`modo_social` NO se
      quitaron**: `table-scanner.tsx`, `checkout/exit-pass.tsx` y `TableSessionRuntime.tsx`
      (Sprint 3, pendiente) todavía los usan para limpiar la sesión de mesa — se quitan cuando
      esos archivos se toquen.
- [ ] `app/checkout/order-type.tsx` y `app/checkout/exit-pass.tsx` invalidan queries `['social']` — limpieza menor, no urgente (se deja para cuando se toquen esos archivos en Sprint 3)

Extra no listado en el plan original, pero necesario para no dejar rutas rotas:

- [x] `app/(tabs)/_layout.tsx` — quitado el `Tabs.Screen name="social"` y las variables
      `socialActive`/`socialAvailableInRestaurant`/`showSocialTab` que lo controlaban
- [x] `app/(tabs)/profile.tsx` — quitado el `MenuItem` "Perfil social" (enlazaba a la ruta borrada)
- [x] `services/deep-links.service.ts` — quitado el branch que resolvía deep links `social://...` a `/social`

Pendiente de revisar (no se tocó, fuera de alcance de código):

- `app/profile/help.tsx` tiene una FAQ ("Reportes y bloqueo") que menciona perfiles sociales — contenido de ayuda desactualizado, no es un bug funcional.
- `app/legal/terms.tsx` y `app/legal/privacy.tsx` describen el "modo social" como parte del aviso de privacidad/términos — esto es texto legal, mejor que lo revise quien mantenga esos documentos antes de tocarlo.
- `app/table-scanner.tsx` (se borra completo en Sprint 3) todavía navega a `/profile/social` bajo `activateSocial=1`, pero nada en la app llama a esa ruta con ese parámetro — código inalcanzable, no se tocó para no editar un archivo que Sprint 3 borra de todos modos.

### Sprint 3 — Eliminar mesas, QR y dine-in

**Rama:** `feature/sprint-3-remove-dine-in`
**Dependencias:** coordina con Sprint 4 — **no tocar** `app/(tabs)/index.tsx`, Sprint 4 lo reescribe completo y absorbe ahí la parte de dine-in.

> ⚠️ **El build queda roto hasta que corra el Sprint 4.** `app/(tabs)/index.tsx` y
> `components/shared/OrderTypeSelector.tsx` todavía importan `store/table-session.store`
> y navegan a `/table-scanner` — ambos archivos, tal como pide este plan, no se tocaron.
> Como `table-session.store.ts` ya no existe, `index.tsx` no compila hasta que el Sprint 4
> lo reescriba y quite esas referencias. Esto es intencional (son sprints pensados para
> ramas paralelas que se integran en el Sprint 5), pero si vas a compilar/correr la app
> ahora mismo en un solo checkout, el Sprint 4 tiene que ir enseguida.

Borrar completo:

- [x] `app/table-scanner.tsx`, `app/checkout/exit-pass.tsx`
- [x] `services/table-session.service.ts`, `store/table-session.store.ts`
- [x] `components/shared/TableSessionRuntime.tsx`, `components/shared/TableContextBanner.tsx`

Cirugía — quitar ramas "eat_in" / sesión de mesa:

- [x] `app/cart.tsx`
- [x] `app/product/[id].tsx`
- [x] `app/checkout/order-type.tsx` (mezclado con pickup/delivery en `handleContinue`)
- [x] `app/checkout/payment.tsx` — incluye `finishOrderFlow`, que ya no redirige a `checkout/exit-pass`
- [x] `app/order/[id].tsx` — la pantalla de "cuenta abierta" (timeline, botones de pagar/generar QR) era la mitad del archivo; se quitó completa
- [x] `app/(tabs)/_layout.tsx` — no tenía nada de eat_in/mesa (ya se había limpiado en el Sprint 2)
- [x] `app/_layout.tsx` — quitado el mount de `TableSessionRuntime`, `hydrateTableSession()` y los `Stack.Screen` de `table-scanner`/`checkout/exit-pass`

Extra no listado en el plan original, necesario para no dejar referencias rotas:

- [x] `store/user.store.ts` — quitada la llamada a `useTableSessionStore.getState().clearSession()` en `clearSessionState()`
- [x] `services/orders.service.ts` — quitados `getExitPass`/`scanExitPass` (solo los usaba `checkout/exit-pass.tsx`), la normalización especial de `eat_in` en `createOrder`, y `mesa_id`/`consumo_por_mesa` del payload
- [x] `app/(tabs)/orders.tsx` — el listado de pedidos tenía 4 funciones (`isEatInConsumption`, `getOrderStatusLabel`, `getOrderStatusColor`, `getOrderTitle`, `getOrderModeMeta`) con una rama eat_in cada una; simplificadas a solo pickup/delivery
- [x] `packages/types/src/order.types.ts` — quitados `mesa_id`/`consumo_por_mesa` de `CreateOrderPayload` (ya nadie los llenaba)

**Dejado a propósito sin tocar** — `TipoPedido` sigue siendo `'delivery' | 'pickup' | 'eat_in'` y el tipo `Pedido` conserva sus campos de mesa/cuenta abierta (`mesa_id`, `cuenta_abierta`, `salida_qr_generado_at`, etc.) y el tipo `ExitPass` completo. Encontré que **`apps/api` (un backend Node/TS aparte de `apps/api-php`) también importa `@amare/types` y sí usa esos campos** (`apps/api/src/routes/orders.routes.ts`) — angostar esos tipos aquí habría roto la compilación de ese servicio. No sé si `apps/api` sigue vivo/desplegado o es un backend viejo de antes de migrar a PHP — vale la pena confirmarlo antes de limpiar esos tipos compartidos.

### Sprint 4 — Eliminar sucursales/reservaciones + reescribir Home

**Rama:** `feature/sprint-4-home-rewrite`
**Dependencias:** el sprint más grande y de mayor riesgo. Recomendado para quien tenga más contexto del repo.

> ⚠️ Antes de empezar: confirmar que Sprint 3 ya mergeó, para no reescribir sobre una versión desactualizada del archivo.

`app/(tabs)/index.tsx` tiene 2,400+ líneas con selección de sucursal, reservaciones y el menú
entrelazados en el mismo árbol de componentes.

Borrar completo:

- [x] `app/branch-selector.tsx`
- [x] `services/reservations.service.ts`

Cirugía:

- [x] `services/branches.service.ts` — quitados `getNearestBranches`/`getBranchById`, se dejó `getBranches`/`normalizeBranch`
- [x] `hooks/useBranches.ts` — quitados `useNearestBranches`/`useBranch` (envolvían las funciones de arriba, sin más consumidores); se dejó `useBranches()`
- [x] `store/branch.store.ts` — quitados `fetchSucursales` y `loading` (solo los usaba `branch-selector.tsx`); **no se tocó** `useBranchConfigStore`
- [x] `components/shared/GlobalCartButton.tsx` — quitado el caso especial de `branch-selector`, y de paso los leftovers ya muertos de `(waiter)`/`(hostess)`/`social` (Sprints 1-2) y el check de rol mesero/anfitrión que ya no aplica
- [x] `app/_layout.tsx` — quitado `Stack.Screen` de `branch-selector` y su entrada en `inPublicCatalog`

Reescritura de `app/(tabs)/index.tsx` (2,438 → 720 líneas):

- [x] Sacado: selección de sucursal para pickup (mapa, dropdown, `SafeMapView`, `selectingPickupBranch`)
- [x] Sacado: sistema completo de reservaciones (formulario, disponibilidad, modal, ~275 líneas de estilos)
- [x] Sacado: toda la detección de sucursal por geolocalización y las ramas `eat_in` que Sprint 3 no pudo tocar (bootstrap, `openDeliveryFlow`, `handleInitialTypeSelect`, etc.)
- [x] Dejado: hero, banners, categorías, búsqueda, platillos destacados, selector simple pickup/delivery (`OrderTypeSelector`, ahora sin la opción "En mesa") — sin mapa ni lista de sucursales
- [x] `components/shared/OrderTypeSelector.tsx` — quitada la opción `eat_in` del selector (quedó pendiente en el Sprint 3 porque solo lo usaba este archivo, que Sprint 3 tenía prohibido tocar)

**`availableTypes` ahora es dinámico**, no una lista fija: se calcula desde `menuBranch.tipos_entrega` (lo que tenga configurado el restaurante en `rest_configuracion`/`Sucursal.tipos_entrega`), cayendo a `['delivery','pickup']` si no hay nada configurado — así que si UTEQ solo activa pickup, el selector solo muestra pickup.

**Dejado a propósito sin tocar** (mismo motivo que en Sprint 3): `packages/types/src/order.types.ts`'s `TipoPedido` sigue incluyendo `'eat_in'`, y el tipo `Sucursal` conserva `mesas_habilitadas`/`reservas_habilitadas`/`distancia_km` — `apps/api` (el backend Node/TS aparte de `apps/api-php`, ver nota en Sprint 3) los sigue usando en `apps/api/src/routes/branches.routes.ts` y `LocationService.ts`.

### Sprint 5 — Integración, base real y TestFlight

**Rama:** `feature/sprint-5-integration` (se crea después de mergear 1→4)
**Dependencias:** Sprints 1, 2, 3 y 4 ya mergeados a `main`, y Sprint 0 para la base de datos final.

> Nota: en esta pasada los 4 sprints se hicieron secuencialmente en una sola rama
> (`feature/sprint-1-remove-staff-apps`, sin crear ramas 2/3/4 separadas) en vez de en
> paralelo — así que no hay nada que mergear entre ellos. El barrido de imports/rutas
> rotas de abajo ya se corrió después de cada sprint (`grep` por `(waiter)`, `(hostess)`,
> `social`, `table-scanner`, `table-session`, `branch-selector`, `eat_in`, `reservations`
> en todo `apps/mobile`) y no quedó nada — pero **no se corrió `tsc`/`eslint`** porque
> `node_modules` no está instalado en este entorno, así que vale la pena correrlo antes
> de dar por bueno el build.

- [ ] Mergear 1 → 2 → 3 → 4 en ese orden; resolver los conflictos chicos que salgan en `app/_layout.tsx`
- [ ] Apuntar la app a la base de datos recortada de Sprint 0
- [ ] Probar el flujo completo: login → menú → carrito → checkout (pickup y delivery) → pago Stripe (prueba) → pedido con estado visible para cocina
- [ ] Buscar imports/rutas rotas hacia todo lo eliminado (`grep` por `(waiter)`, `(hostess)`, `social`, `table-scanner`, `branch-selector`, `reserv`)
- [ ] `eas build` + `eas submit` → TestFlight

---

## 2. Base de datos

**Para una base nueva y vacía (recomendado):** [`000_baseline_schema_uteq.sql`](../apps/api-php/migrations/000_baseline_schema_uteq.sql)
— esquema completo listo para importar, sin una sola fila de otro cliente. Trae comentadas al
final las plantillas de `INSERT` para tu primera empresa/restaurante/usuario admin.

Construido a partir del dump de referencia que compartiste (de otro cliente, Jungle Pizza,
sobre la misma plataforma), quitando mesas/QR/dine-in, mesero, anfitrión, social y sucursales,
y con las tablas de saldo/puntos renombradas a `amare_wallets`/`amare_wallet_transactions`
— verifiqué esos nombres contra `apps/api-php/src/Services/RewardsService.php`, ya que el
dump de referencia usaba nombres distintos (`jungle_wallets`, etc.) para ese mismo feature.
El resto de las tablas no se pudo verificar contra un dump real de tu base actual (no lo
tengo) — si tienes uno a la mano, sería la referencia más confiable para revisar este archivo
antes de correrlo en algo importante.

**Para recortar una base que ya clonaste de otro cliente:** [`086_trim_schema_uteq.sql`](../apps/api-php/migrations/086_trim_schema_uteq.sql)
(`DROP`/`ALTER` sobre una copia existente, en vez de crear desde cero).

**Se queda igual:** `rest_pedidos` conserva `estado` (pendiente → en_preparacion → listo →
entregado) — eso es lo que alimenta el KDS de cocina, no se toca.

---

## 3. No tocar sin confirmar

Tablas de back-office / panel admin / inventario que no viven en este repo de la app móvil.
Recortar el móvil no significa que estas sobren — probablemente las sigue usando un panel web
aparte.

| Tabla(s) | Por qué se deja en paz |
|---|---|
| `rest_ingredientes`, `rest_recetas`, `rest_receta_ingredientes`, `rest_platillo_armado`, `rest_pasos_preparacion`, `rest_movimientos_inventario` | Inventario y armado de platillos — panel de cocina/admin, no la app de cliente. |
| `rest_gastos`, `rest_cortes`, `rest_retiros` | Caja y contabilidad del restaurante. |
| `carnihub_api_config`, `rest_pedidos_sugeridos`, `rest_pedido_sugerido_items` | Integración con proveedor (CarniHub), ajena al pedido del cliente. |
| `rest_visibilidad_financiera(_historial)`, `rest_regularizaciones_adeudo` | Herramientas administrativas internas. |
| `app_clientes` | Tabla aparte de `mobile_usuarios` — confirmar si sigue en uso antes de decidir algo. |
| `usuarios`, `roles`, `empresas`, `action_logs`, `login_intentos`, `api_rate_limits` | Cuentas de staff/admin y seguridad de la plataforma, no del cliente final. |

---

## 4. Checklist de cuentas externas

- [x] **Logo, ícono y splash** — reemplazados con el logo de UTEQ en todas las variantes (iOS, adaptativo Android, login).
- [x] **Notificaciones, Google e Apple Sign-In** — ocultos en la app y quitados de `app.json`; el código sigue ahí por si se reactivan.
- [ ] **Stripe** — pegar `pk_test_` en `apps/mobile/.env` y `sk_test_` en el servidor (Sprint 0).
- [ ] **Apple Developer / App Store Connect** — registrar `com.uteq.cafeteria` y crear la app nueva (Sprint 0).
- [ ] **Migración de categorías** — correr `085_reduce_menu_scope_to_comida_bebidas_dulceria.sql` contra la base real (Sprint 0).
