# Plan Web Para Facturacion

Este documento describe lo que debe implementarse en la web externa para operar la facturacion v1 ya expuesta por la app/API. En esta version no se timbra CFDI automaticamente; la web captura y da seguimiento a solicitudes.

## 1. Preparacion

1. Ejecutar la migracion `apps/api-php/migrations/043_create_invoice_requests.sql`.
2. Verificar que `GET /branches/:id/config` responda con el bloque:

```json
{
  "facturacion": {
    "habilitada": true,
    "modo": "solicitud",
    "emisor_configurado": true,
    "emisor": {
      "rfc": "AAA010101AAA",
      "nombre_fiscal": "Restaurante Demo SA de CV",
      "regimen_fiscal": "601",
      "codigo_postal": "64000"
    },
    "email_notificacion": "facturacion@restaurante.com"
  }
}
```

## 2. Configuracion De Sucursal

Agregar una seccion **Facturacion** en la pantalla de configuracion de sucursal.

Campos:

- Activar/desactivar facturacion.
- RFC emisor.
- Razon social / nombre fiscal emisor.
- Regimen fiscal emisor.
- Codigo postal / lugar de expedicion.
- Email administrativo para avisos.

Guardar con:

```http
PUT /branches/:id/config
```

```json
{
  "facturacion": {
    "habilitada": true,
    "emisor": {
      "rfc": "AAA010101AAA",
      "nombre_fiscal": "Restaurante Demo SA de CV",
      "regimen_fiscal": "601",
      "codigo_postal": "64000"
    },
    "email_notificacion": "facturacion@restaurante.com"
  }
}
```

Notas:

- Si `habilitada` es `false`, la app no muestra el toggle de factura.
- Si `habilitada` es `true`, la app permite capturar solicitud antes de pagar.
- `emisor_configurado` es informativo para la UI.

## 3. Vista Admin: Solicitudes De Factura

Crear una pagina admin llamada **Solicitudes de factura**.

Endpoint principal:

```http
GET /admin/invoice-requests
```

Filtros soportados:

- `restaurant_id`
- `estado`
- `from`
- `to`
- `page`
- `per_page`

Ejemplo:

```http
GET /admin/invoice-requests?restaurant_id=1&estado=pendiente&from=2026-07-01&to=2026-07-31
```

## 4. Listado

Columnas recomendadas:

- Fecha.
- Sucursal.
- Origen: `cliente` o `mesero`.
- Tipo: `pedido`, `mesa`, `cuenta_separada`.
- Pedido / mesa / cuenta separada.
- RFC receptor.
- Nombre fiscal receptor.
- Monto.
- Metodo de pago.
- Estado.

Estados posibles:

- `pendiente`
- `en_proceso`
- `facturada`
- `cancelada`

## 5. Detalle De Solicitud

Endpoint:

```http
GET /admin/invoice-requests/:id
```

Mostrar:

- Datos del receptor.
- Sucursal.
- Pedido, mesa o cuenta separada relacionada.
- Monto.
- Metodo de pago.
- Fecha de solicitud.
- Estado actual.
- UUID CFDI, PDF, XML y notas si existen.

## 6. Actualizar Solicitud

Endpoint:

```http
PUT /admin/invoice-requests/:id
```

Payload ejemplo para marcar como facturada:

```json
{
  "estado": "facturada",
  "cfdi_uuid": "550e8400-e29b-41d4-a716-446655440000",
  "pdf_url": "https://cdn.example.com/facturas/factura.pdf",
  "xml_url": "https://cdn.example.com/facturas/factura.xml",
  "notas": "Factura generada manualmente"
}
```

Payload ejemplo para tomarla en proceso:

```json
{
  "estado": "en_proceso",
  "notas": "Contabilidad revisando datos fiscales"
}
```

## 7. UX Recomendada

- Badge visual por estado.
- Boton rapido **Marcar en proceso**.
- Boton **Marcar facturada**.
- Validar en frontend que `cfdi_uuid`, `pdf_url` y `xml_url` esten presentes antes de marcar como `facturada`.
- Boton para copiar email del receptor.
- Filtro rapido de pendientes.
- Export CSV opcional para contabilidad.

## 8. Pruebas Web

1. Sucursal sin facturacion:
   - `facturacion.habilitada = false`.
   - La app no debe mostrar el toggle de factura.

2. Sucursal con facturacion:
   - Activar desde web con `PUT /branches/:id/config`.
   - Recargar config en app.
   - La app debe mostrar el toggle.

3. Pago cliente con factura:
   - Pagar desde checkout con `invoice_request.required = true`.
   - Debe aparecer una solicitud en `GET /admin/invoice-requests`.

4. Pago mesero con factura:
   - Cerrar cuenta completa con factura.
   - Debe aparecer solicitud con `origen = mesero` y `scope = mesa`.

5. Cuenta separada:
   - Cobrar una cuenta separada con factura.
   - Debe aparecer solicitud con `scope = cuenta_separada`.

6. Cambios de estado:
   - Marcar como `en_proceso`.
   - Marcar como `facturada` con UUID/PDF/XML.
   - Confirmar que `GET /admin/invoice-requests/:id` refleja el cambio.

## 9. Contrato De Solicitud

Una solicitud regresa con forma general:

```json
{
  "id": 123,
  "restaurante_id": 1,
  "restaurante_nombre": "Sucursal Centro",
  "pedido_id": 456,
  "mesa_id": 12,
  "division_id": null,
  "division_cuenta_id": null,
  "origen": "cliente",
  "scope": "pedido",
  "monto": 320.5,
  "metodo_pago": "card",
  "estado": "pendiente",
  "receptor": {
    "rfc": "XAXX010101000",
    "nombre_fiscal": "Publico en General",
    "regimen_fiscal": "616",
    "codigo_postal": "64000",
    "uso_cfdi": "S01",
    "email": "cliente@example.com"
  },
  "cfdi_uuid": null,
  "pdf_url": null,
  "xml_url": null,
  "notas": null,
  "created_at": "2026-07-02 12:00:00"
}
```
