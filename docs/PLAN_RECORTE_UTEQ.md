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
- [ ] Correr la migración `085_reduce_menu_scope_to_comida_bebidas_dulceria.sql` contra la base real — revisar el `SELECT` antes de los `UPDATE`
- [ ] Aplicar la migración `086_trim_schema_uteq.sql` (ver más abajo) sobre una copia nueva de la base para provisionar la base final de UTEQ

### Sprint 1 — Eliminar app de mesero y anfitrión

**Rama:** `feature/sprint-1-remove-staff-apps`
**Dependencias:** ninguna, son módulos autocontenidos.

Borrar completo:

- [ ] `app/(waiter)/` completo (layout, index, orders, gifts, table/[id])
- [ ] `app/(hostess)/` completo (layout, index, tables)
- [ ] `services/waiter.service.ts`, `services/hostess.service.ts`
- [ ] `store/waiter-cart.store.ts`
- [ ] `components/waiter/` completo, `components/staff/StaffTabBar.tsx`

Cirugía (editar, no borrar):

- [ ] `store/user.store.ts` — quitar la llamada al wallet-cart de mesero dentro de `clearSessionState()` (~línea 110)
- [ ] `app/_layout.tsx` — quitar los redirects por rol (mesero/anfitrión) y los `Stack.Screen` de `(waiter)`/`(hostess)`

### Sprint 2 — Eliminar modo social

**Rama:** `feature/sprint-2-remove-social`
**Dependencias:** ninguna.

Borrar completo:

- [ ] `app/profile/social.tsx` (7,100+ líneas) y `app/(tabs)/social.tsx`
- [ ] `services/social-account.service.ts`, `services/social-gifts.service.ts`
- [ ] `components/shared/GlobalSocialNotifications.tsx`

Cirugía:

- [ ] `app/_layout.tsx` — quitar el `<GlobalSocialNotifications/>` montado globalmente (~línea 626) y su import
- [ ] Revisar `packages/types` / `store/user.store.ts` por campos `is_social_active`, `modo_social` — quitar si nada más los usa
- [ ] `app/checkout/order-type.tsx` y `app/checkout/exit-pass.tsx` invalidan queries `['social']` — limpieza menor, no urgente

### Sprint 3 — Eliminar mesas, QR y dine-in

**Rama:** `feature/sprint-3-remove-dine-in`
**Dependencias:** coordina con Sprint 4 — **no tocar** `app/(tabs)/index.tsx`, Sprint 4 lo reescribe completo y absorbe ahí la parte de dine-in.

Borrar completo:

- [ ] `app/table-scanner.tsx`, `app/checkout/exit-pass.tsx`
- [ ] `services/table-session.service.ts`, `store/table-session.store.ts`
- [ ] `components/shared/TableSessionRuntime.tsx`, `components/shared/TableContextBanner.tsx`

Cirugía — quitar ramas "eat_in" / sesión de mesa:

- [ ] `app/cart.tsx`
- [ ] `app/product/[id].tsx`
- [ ] `app/checkout/order-type.tsx` (mezclado con pickup/delivery en `handleContinue`)
- [ ] `app/checkout/payment.tsx`
- [ ] `app/order/[id].tsx`
- [ ] `app/(tabs)/_layout.tsx`
- [ ] `app/_layout.tsx` — quitar mount de `TableSessionRuntime`, `hydrateTableSession()` y los `Stack.Screen` de `table-scanner`/`checkout/exit-pass`

### Sprint 4 — Eliminar sucursales/reservaciones + reescribir Home

**Rama:** `feature/sprint-4-home-rewrite`
**Dependencias:** el sprint más grande y de mayor riesgo. Recomendado para quien tenga más contexto del repo.

> ⚠️ Antes de empezar: confirmar que Sprint 3 ya mergeó, para no reescribir sobre una versión desactualizada del archivo.

`app/(tabs)/index.tsx` tiene 2,400+ líneas con selección de sucursal, reservaciones y el menú
entrelazados en el mismo árbol de componentes.

Borrar completo:

- [ ] `app/branch-selector.tsx`
- [ ] `services/reservations.service.ts`

Cirugía:

- [ ] `services/branches.service.ts` — quitar `getNearestBranches`/`getBranchById`, dejar `normalizeBranch` (lo usa `branch.store.ts`)
- [ ] `store/branch.store.ts` — quitar el estado de selección de sucursal, **no tocar** `useBranchConfigStore` (alimenta menú/modificadores en varias pantallas)
- [ ] `components/shared/GlobalCartButton.tsx` — quitar el caso especial de la ruta `branch-selector`
- [ ] `app/_layout.tsx` — quitar `Stack.Screen` de `branch-selector`

Reescritura de `app/(tabs)/index.tsx`:

- [ ] Sacar: selección de sucursal para pickup (`SafeMapView`, `selectingPickupBranch`, ~líneas 33–920)
- [ ] Sacar: sistema completo de reservaciones (formulario, disponibilidad, modal ~1487–1680, ~250 líneas de estilos)
- [ ] Sacar: cualquier rama `eat_in` que haya quedado pendiente de Sprint 3 en este archivo
- [ ] Dejar: hero, categorías, búsqueda, platillos destacados, selector simple pickup/delivery sin mapa de sucursales

### Sprint 5 — Integración, base real y TestFlight

**Rama:** `feature/sprint-5-integration` (se crea después de mergear 1→4)
**Dependencias:** Sprints 1, 2, 3 y 4 ya mergeados a `main`, y Sprint 0 para la base de datos final.

- [ ] Mergear 1 → 2 → 3 → 4 en ese orden; resolver los conflictos chicos que salgan en `app/_layout.tsx`
- [ ] Apuntar la app a la base de datos recortada de Sprint 0
- [ ] Probar el flujo completo: login → menú → carrito → checkout (pickup y delivery) → pago Stripe (prueba) → pedido con estado visible para cocina
- [ ] Buscar imports/rutas rotas hacia todo lo eliminado (`grep` por `(waiter)`, `(hostess)`, `social`, `table-scanner`, `branch-selector`, `reserv`)
- [ ] `eas build` + `eas submit` → TestFlight

---

## 2. Recorte de base de datos

Ver migración [`086_trim_schema_uteq.sql`](../apps/api-php/migrations/086_trim_schema_uteq.sql).
Aplícala sobre una **copia nueva** del esquema de referencia, no sobre una base en producción
con datos que te importen.

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
