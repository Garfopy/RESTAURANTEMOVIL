# Desarrollo Del Proyecto Enfocado En La Aplicacion Movil Amare

Este documento contiene el texto propuesto para complementar el reporte de estadia. Esta redactado para integrarse al capitulo **Desarrollo Del Proyecto** y para ajustar la seccion **Entregables** de acuerdo con la guia UTEQ. El contenido se enfoca en la aplicacion movil ubicada en `apps/mobile` y menciona la API PHP solo como backend consumido por la app.

## Ajuste Propuesto Para La Seccion Entregables

El proyecto se organizo en entregables operables y documentales con el proposito de evidenciar el avance funcional de la aplicacion movil y su integracion con el ecosistema del restaurante. En la fase de desarrollo movil se entrego una aplicacion multiplataforma desarrollada con React Native y Expo, conectada a servicios REST de la API PHP, con soporte para los flujos principales del comensal y para funciones operativas de mesero y hostess.

Los entregables operables relacionados con la aplicacion movil fueron los siguientes:

1. Aplicacion movil React Native/Expo compatible con Android e iOS.
2. Flujos de autenticacion, registro, inicio de sesion con correo o telefono, inicio de sesion con Google y recuperacion de contrasena.
3. Pantalla principal para seleccion de sucursal, consulta de categorias, consulta de productos, busqueda, favoritos, promociones y carrito persistente.
4. Flujo de compra para las modalidades de entrega a domicilio, recoleccion en local y consumo en mesa.
5. Integracion con Stripe para pagos, Saldo Amare, puntos de recompensa y confirmacion de pedidos.
6. Escaneo de codigo QR para asociar sucursal y mesa, habilitando el contexto de consumo en restaurante.
7. Modulo de perfil con direcciones, historial de actividad, saldo, recompensas y datos de usuario.
8. Modulo social para activacion de perfil social, descubrimiento de comensales, likes, matches, regalos sociales y cobertura de cuenta.
9. Modulos moviles por rol para mesero y hostess, incluyendo gestion de mesas, pedidos, reservaciones, validacion de salida y operaciones de cuenta.
10. Integracion con Firebase y Expo Notifications para registro de tokens, recepcion de notificaciones push y apertura de vistas mediante enlaces internos.
11. Build de iOS generado con EAS y enviado a App Store Connect/TestFlight para pruebas de distribucion.

Los entregables documentales propuestos para respaldar la entrega son: capturas de las pantallas principales, diagrama de arquitectura movil, diagrama del flujo de pedido, diagrama del flujo de QR de mesa, tabla de modulos y funcionalidades, tabla de servicios/API consumidos por la app y matriz de pruebas funcionales.

La frase anterior del reporte que indicaba que la aplicacion movil fue "publicada en Expo Go" puede reemplazarse por la siguiente redaccion:

> La aplicacion movil fue probada durante la etapa de desarrollo con Expo y distribuida para pruebas mediante EAS/TestFlight, conservando compatibilidad multiplataforma para Android e iOS.

## Desarrollo Del Proyecto

En el presente capitulo se describe el desarrollo de la aplicacion movil Amare, la cual forma parte del sistema integral de gestion operativa para restaurantes. El desarrollo se realizo con una arquitectura cliente-servidor, donde la aplicacion movil consume servicios REST proporcionados por la API PHP y presenta al usuario interfaces especializadas de acuerdo con su rol operativo. La aplicacion permite que el comensal consulte el menu, gestione pedidos, realice pagos, escanee codigos QR de mesa, administre su perfil y utilice funciones sociales; adicionalmente, habilita modulos moviles para mesero y hostess con operaciones de seguimiento de mesas, validacion y cierre de cuentas.

### Arquitectura General De La Aplicacion Movil

La aplicacion movil fue desarrollada con React Native y Expo, utilizando TypeScript como lenguaje principal para mejorar la consistencia de tipos y reducir errores durante el desarrollo. La navegacion se implemento con Expo Router, lo que permitio organizar las pantallas mediante rutas agrupadas por contexto: autenticacion, tabs principales, mesero, hostess, checkout, perfil, tienda y detalle de pedidos.

La comunicacion con el backend se centralizo mediante un cliente HTTP basado en Axios. Dicho cliente agrega automaticamente el token JWT cuando existe una sesion activa, normaliza rutas relativas y transforma respuestas de la API para que la app pueda trabajar con datos consistentes. La aplicacion movil consume endpoints de autenticacion, sucursales, menu, pedidos, pagos, perfil, promociones, recompensas, modo social, mesero, hostess y notificaciones.

Para la administracion del estado local se empleo Zustand. Con esta herramienta se implementaron stores para usuario, carrito, sucursal seleccionada, sesion de mesa, favoritos, tema visual y carrito de mesero. Adicionalmente, se utilizo React Query para manejar consultas asincronas, cache, refresco de datos y sincronizacion de informacion de menu, pedidos y configuraciones.

**Figura 1**  
*Arquitectura cliente-servidor de la aplicacion movil Amare*

```text
Aplicacion movil React Native/Expo
        |
        | Axios apiClient + JWT
        v
Servicios REST API PHP
        |
        | Modelos, controladores y reglas de negocio
        v
Base de datos MySQL / Servicios externos
        |
        | Stripe, Firebase, Google Sign-In, App Store Connect/TestFlight
        v
Integraciones de pago, notificacion, autenticacion y distribucion
```

### Autenticacion Y Administracion De Sesion

El primer bloque desarrollado correspondio al flujo de autenticacion. La aplicacion incluye pantallas para registro, inicio de sesion por correo o telefono, inicio de sesion con Google y recuperacion de contrasena. La autenticacion se conecta con la API PHP mediante endpoints de login, registro, validacion de sesion y recuperacion de contrasena.

Una vez autenticado el usuario, la app almacena la informacion de sesion en el store de usuario y conserva el token de autenticacion para las peticiones posteriores. La capa global de la aplicacion valida el estado de autenticacion y redirige al usuario segun su rol. Si el usuario es comensal, se muestra el flujo principal de tabs; si tiene rol de mesero, se redirige al modulo de mesero; y si tiene rol de hostess o anfitrion, se redirige al modulo correspondiente.

La recuperacion de contrasena se implemento en tres pasos: solicitud de codigo, verificacion del codigo y definicion de una nueva contrasena. Este flujo permite restablecer el acceso sin exponer directamente la contrasena original y mantiene la separacion entre interfaz movil y reglas de validacion del backend.

### Seleccion De Sucursal, Catalogo Y Carrito

El flujo principal del comensal inicia en la pantalla de inicio, donde se muestran sucursales, promociones, categorias y productos destacados. La aplicacion permite seleccionar una sucursal manualmente o apoyarse en permisos de ubicacion para detectar la sucursal mas cercana. Esta seleccion se almacena en el estado global para que el catalogo, los productos y el carrito trabajen con el mismo restaurante.

El catalogo movil consume servicios de categorias y productos. La app permite navegar por categorias, consultar detalle de platillos, buscar productos y marcar favoritos. El carrito se implemento como un estado persistente que conserva productos, cantidades, modificadores, notas, subtotal, total, modalidad de pedido y sucursal asociada. Cuando el usuario cambia de sucursal, el sistema valida que el carrito pertenezca al mismo restaurante para evitar pedidos inconsistentes.

Las modalidades soportadas son entrega a domicilio, recoleccion en local y consumo en mesa. En el caso de entrega a domicilio, el usuario debe seleccionar o registrar una direccion. En recoleccion local, se utiliza la sucursal seleccionada. En consumo en mesa, la app requiere que exista una sesion de mesa obtenida mediante escaneo de QR.

### Flujo De Pedido, Pago Y Facturacion

El checkout se dividio en pantallas especializadas para definir el tipo de pedido, capturar datos necesarios y procesar el pago. La aplicacion construye el pedido con los productos del carrito, modalidad seleccionada, sucursal, direccion o mesa, promociones aplicables y datos de facturacion en caso de ser requeridos.

La integracion de pagos se realizo mediante Stripe. La app solicita al backend la creacion de un Payment Intent, presenta el formulario de pago y confirma la transaccion antes de registrar el pedido como pagado. Tambien se incorporaron funciones de Saldo Amare y puntos de recompensa, permitiendo consultar wallet, cotizar descuentos, recargar saldo y aplicar beneficios dentro de los flujos habilitados.

Para facturacion, la aplicacion integra formularios de datos fiscales y permite adjuntar la solicitud fiscal al pedido o al cierre de cuenta. Esto evita que la facturacion sea un proceso externo separado y mantiene la trazabilidad entre consumo, pago y solicitud fiscal.

**Figura 2**  
*Flujo de pedido desde seleccion de sucursal hasta pago*

```text
Seleccion de sucursal
        v
Consulta de categorias y productos
        v
Producto -> carrito -> modalidad de pedido
        v
Delivery / Pickup / Eat-in
        v
Direccion, sucursal o mesa
        v
Resumen, promociones, saldo y datos fiscales
        v
Pago Stripe / wallet / cuenta
        v
Confirmacion del pedido y seguimiento
```

### Escaneo QR Y Contexto De Mesa

Para la modalidad de consumo en restaurante se desarrollo un flujo de escaneo QR. La pantalla de escaneo utiliza la camara del dispositivo para leer el codigo de la mesa y enviarlo a la API. El backend valida el QR, identifica restaurante y mesa, y devuelve informacion normalizada para establecer una sesion local de mesa.

Al establecerse la sesion, la app guarda el restaurante, mesa, etiqueta visible y sucursal relacionada. Este contexto se utiliza para habilitar pedidos en mesa, asociar consumos, activar el modo social dentro del restaurante y validar que el usuario no cambie de mesa mientras exista una cuenta abierta. El escaneo tambien contempla casos de conflicto, como intentar cambiar de mesa con una visita activa o con carrito de otra sucursal.

El flujo de salida se complemento con la generacion y validacion de pase de salida. Despues de pagar o cerrar la cuenta, la app puede presentar un codigo QR que posteriormente es validado por el rol hostess, reduciendo el riesgo de salida sin pago registrado.

**Figura 3**  
*Flujo QR de mesa y sesion eat_in*

```text
Usuario selecciona "Comer aqui"
        v
Pantalla de escaneo QR
        v
API valida restaurante y mesa
        v
Se guarda sesion de mesa en la app
        v
Menu, pedidos y modo social usan el mismo contexto
        v
Pago o cierre de cuenta
        v
Generacion y validacion de pase de salida
```

### Perfil, Recompensas Y Modo Social

El modulo de perfil permite al usuario consultar y actualizar informacion personal, foto de perfil, direcciones, actividad reciente, pedidos y recompensas. Desde esta seccion tambien se accede a Saldo Amare, donde se visualiza el balance disponible y se integran recargas mediante Stripe.

El modo social se desarrollo como una extension del contexto de mesa. Para activarlo, el usuario debe contar con perfil social completo y aceptar el aviso de privacidad correspondiente. La app permite configurar fotos, nombre visible, edad, genero, sexualidad, descripcion, intereses y redes sociales. Una vez activo, el usuario puede descubrir otros comensales del mismo restaurante, enviar likes, ver matches y consultar likes recibidos o enviados.

Tambien se implementaron interacciones sociales vinculadas al consumo en restaurante, como envio de regalos sociales y solicitud de cobertura de cuenta. Estas funciones consumen servicios especificos del backend, se apoyan en notificaciones y permiten a meseros visualizar entregas sociales pendientes.

### Modulos Operativos Moviles: Mesero Y Hostess

Ademas del flujo del comensal, la aplicacion incluye modulos moviles por rol. El modulo de mesero permite consultar sucursales asignadas, visualizar mesas, reclamar mesas, revisar cuentas abiertas, aceptar pedidos entrantes, confirmar entregas, cerrar cuentas, dividir cuentas y gestionar regalos sociales. Este modulo se conecta con endpoints especificos de mesero y utiliza un carrito operativo separado del carrito del comensal.

El modulo de hostess permite monitorear reservaciones, pedidos de pickup o delivery, mesas y validaciones de salida. La hostess puede consultar sucursales, filtrar reservaciones activas o completadas, validar pases de salida y completar entregas o reservaciones. Con ello, la app no solo atiende al cliente final, sino que tambien apoya la operacion interna del restaurante.

Estos roles se controlan mediante la sesion del usuario. La capa de navegacion global redirige automaticamente al usuario autenticado hacia el modulo que corresponde a su rol, evitando que un comensal entre a pantallas operativas sin autorizacion.

### Notificaciones, Configuracion Y Distribucion

La aplicacion integra notificaciones push mediante Firebase y Expo Notifications. Al iniciar sesion, el dispositivo registra su token en el backend para recibir mensajes relacionados con pedidos, promociones, regalos sociales, solicitudes de cobertura de cuenta y otros eventos relevantes. Tambien se implemento el manejo de enlaces internos para abrir pantallas especificas cuando el usuario toca una notificacion.

La configuracion de produccion se definio mediante `app.json` y `eas.json`. En estos archivos se declaro el identificador de iOS `com.amare.app`, el paquete Android, permisos de ubicacion, camara, notificaciones, archivos de Firebase, configuracion de Stripe y variables de entorno necesarias para la API. Para la distribucion se genero un build de iOS con EAS y se envio a App Store Connect/TestFlight, lo cual constituye evidencia de entrega operable para pruebas externas o internas.

### Validacion Funcional De La Aplicacion

La validacion funcional se planteo por escenarios de uso, verificando que cada flujo de la app cumpliera con su objetivo operativo. Las pruebas se enfocaron en validar autenticacion, consulta de catalogo, construccion de pedidos, pago, escaneo QR, modo social, modulos operativos y notificaciones.

**Tabla 1**  
*Modulos moviles y funcionalidades principales*

| Modulo | Funcionalidades principales | Evidencia sugerida |
| --- | --- | --- |
| Autenticacion | Registro, login, Google Sign-In, recuperacion de contrasena, persistencia de sesion | Capturas de login, registro y recuperacion |
| Home y catalogo | Sucursales, categorias, destacados, busqueda, favoritos, promociones | Capturas de home, categorias y detalle de producto |
| Carrito y checkout | Carrito persistente, delivery, pickup, eat_in, direcciones, resumen | Capturas de carrito y tipo de pedido |
| Pago y facturacion | Stripe, confirmacion de pedido, Saldo Amare, puntos, solicitud fiscal | Capturas de pago, wallet y formulario fiscal |
| QR de mesa | Escaneo, sesion de mesa, asociacion de sucursal, pase de salida | Capturas de escaneo y confirmacion de mesa |
| Perfil y recompensas | Perfil, avatar, direcciones, historial, actividad, recompensas | Capturas de perfil y Saldo Amare |
| Modo social | Perfil social, descubrimiento, likes, matches, regalos y cobertura de cuenta | Capturas de social, matches y regalos |
| Mesero | Mesas, pedidos, entregas, cuenta, division de cuenta, regalos | Capturas de dashboard mesero y mesa |
| Hostess | Reservaciones, mesas, pedidos de salida, validacion de QR | Capturas de dashboard hostess y validacion |
| Notificaciones | Token push, recepcion de eventos, deep links internos | Captura de notificacion y pantalla destino |

**Tabla 2**  
*Servicios/API consumidos por la aplicacion movil*

| Area | Servicios consumidos | Proposito |
| --- | --- | --- |
| Autenticacion | `/auth/login`, `/auth/register`, `/auth/me`, `/auth/password-reset/*` | Gestion de sesion y recuperacion de acceso |
| Sucursales y menu | `/branches`, `/menu/categories`, `/menu/products` | Consulta de sucursales, categorias y productos |
| Pedidos y pagos | `/orders`, `/payments/create-intent`, confirmacion de pago | Crear pedidos, procesar pagos y consultar seguimiento |
| Perfil | `/profile`, `/profile/addresses`, `/profile/fiscal-data`, `/profile/avatar` | Datos personales, direcciones, facturacion y foto |
| Promociones y favoritos | `/promotions`, `/promotions/validate`, `/favorites` | Promociones, cupones y productos favoritos |
| Recompensas | `/rewards/wallet`, `/rewards/quote`, `/rewards/topups/*`, `/rewards/redeem` | Saldo Amare, puntos, recargas y redencion |
| QR y mesa | `/restaurants/tables/scan`, diagnostico de sesion de mesa | Asociar comensal con mesa y sucursal |
| Social | `/users/social-profile`, `/users/social-status`, `/social/likes`, `/social/matches` | Perfil social, activacion, likes y matches |
| Regalos/cuenta social | `/gift-products`, servicios de regalos y cobertura de cuenta | Regalos sociales y solicitudes de cobertura |
| Mesero | `/waiter/branches`, `/waiter/tables`, `/waiter/orders`, `/waiter/gifts` | Operacion de mesas, pedidos, cuentas y regalos |
| Hostess | `/hostess/branches`, `/hostess/tables`, `/hostess/reservations`, `/hostess/orders` | Reservaciones, mesas, pedidos y validacion de salida |
| Notificaciones | `/profile/push-token` | Registro y eliminacion de tokens push |

**Tabla 3**  
*Matriz de pruebas funcionales propuesta*

| Escenario | Procedimiento | Resultado esperado | Resultado obtenido |
| --- | --- | --- | --- |
| Inicio de sesion | Ingresar correo o telefono y contrasena validos | La app autentica al usuario y abre el modulo correspondiente a su rol | Pendiente de documentar con captura |
| Registro | Capturar nombre, telefono, correo opcional y contrasena | Se crea la cuenta y se permite iniciar sesion | Pendiente de documentar con captura |
| Recuperacion de contrasena | Solicitar codigo, validarlo y guardar nueva contrasena | La API confirma el cambio y la app permite volver al login | Pendiente de documentar con captura |
| Consulta de catalogo | Seleccionar sucursal y abrir categorias/productos | Se cargan productos del restaurante seleccionado | Pendiente de documentar con captura |
| Carrito delivery | Agregar productos, seleccionar direccion y continuar checkout | El pedido conserva direccion y modalidad delivery | Pendiente de documentar con captura |
| Carrito pickup | Agregar productos y seleccionar recoleccion en local | El pedido se asocia a la sucursal seleccionada | Pendiente de documentar con captura |
| Consumo en mesa | Escanear QR de mesa y realizar pedido eat_in | La sesion de mesa se guarda y el pedido se asocia a la mesa | Pendiente de documentar con captura |
| Pago con Stripe | Crear intent, capturar tarjeta y confirmar pago | El pedido queda confirmado y se muestra seguimiento | Pendiente de documentar con captura |
| Saldo Amare | Consultar wallet, cotizar descuento o recargar saldo | La app refleja balance y puntos actualizados | Pendiente de documentar con captura |
| Modo social | Completar perfil social y activar modo social | La app muestra perfiles disponibles del restaurante | Pendiente de documentar con captura |
| Likes y matches | Enviar like a un comensal y consultar matches | La relacion se actualiza y aparece en la vista correspondiente | Pendiente de documentar con captura |
| Mesero | Reclamar mesa, entregar pedido y cerrar cuenta | El estado operativo se actualiza en la API | Pendiente de documentar con captura |
| Hostess | Validar pase de salida o completar reservacion | El pedido/reservacion queda marcado como validado o completado | Pendiente de documentar con captura |
| Notificaciones | Registrar token y recibir evento push | La notificacion abre la pantalla relacionada | Pendiente de documentar con captura |
| Distribucion | Generar build EAS y enviar a App Store Connect/TestFlight | El binario aparece en TestFlight para procesamiento de Apple | Carga enviada a App Store Connect/TestFlight para procesamiento |

Con estas validaciones se demuestra que la aplicacion movil cumple con su objetivo de operar como interfaz principal para comensales y como herramienta de apoyo para roles internos, manteniendo comunicacion en tiempo real con la API y con servicios externos de pago, autenticacion, notificaciones y distribucion.

## Evidencias Visuales A Integrar

Para completar el capitulo en el reporte final, se recomienda insertar las siguientes evidencias con formato de figura o tabla, titulo superior para tablas y titulo inferior para figuras, conforme al estilo solicitado en la guia:

1. Figura: arquitectura cliente-servidor de la aplicacion movil.
2. Figura: flujo de pedido desde seleccion de sucursal hasta pago.
3. Figura: flujo QR de mesa y sesion `eat_in`.
4. Tabla: modulos moviles y funcionalidades principales.
5. Tabla: servicios/API consumidos por la aplicacion movil.
6. Tabla: matriz de pruebas funcionales.
7. Capturas: login, registro, recuperacion de contrasena, home, catalogo, detalle de producto, carrito, checkout, pago, pedidos, perfil, Saldo Amare, modo social, mesero, hostess y evidencia de EAS/TestFlight.

## Criterios De Aceptacion Del Capitulo

El capitulo puede considerarse listo para integrarse al reporte cuando cada subtitulo describa una actividad real soportada por la aplicacion movil, las funciones esten relacionadas con entregables operables y documentales, no se atribuyan al movil funciones exclusivas del panel web, las tablas incluyan evidencia tecnica del repositorio y la distribucion se mencione como prueba mediante EAS/TestFlight. La redaccion final debe mantenerse en tercera persona, con fuente Arial 12, texto justificado y subtitulos sin numeracion.
