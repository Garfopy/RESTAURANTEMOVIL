# Instrucciones: Debuggear /api/auth/token 404

## Archivos Creados

### 1. Early Logging en index.php
✅ Se agregó logging al inicio de index.php para rastrear si `/api/auth/token` llega a PHP

### 2. Archivos de Prueba
✅ `/api/test.php` — Prueba simple de acceso a /api/
✅ `/api/auth/token.php` — Endpoint token simplificado

### 3. Cliente Actualizado
✅ `api-client.js` — Ahora usa `/api/auth/token.php` (con `.php`)

---

## Pasos a Seguir

### PASO 1: Verificar que /api/ es accesible
Abre el navegador y accede a:
```
https://idactivos.digital/api/test.php
```

**Esperado:**
```json
{
  "ok": true,
  "message": "Endpoint /api/test.php funciona correctamente",
  "debug": { ... }
}
```

**Resultado:**
- ✅ Funciona JSON → `/api/` es accesible, archivos PHP se ejecutan
- ❌ 404 HTML → `/api/` no está permitido o no es accesible

---

### PASO 2: Verificar que /api/auth/token.php funciona
Abre el navegador y accede a:
```
https://idactivos.digital/api/auth/token.php
```

**Esperado:**
```json
{
  "success": true,
  "message": "Token endpoint works (simplified test)",
  "debug": {
    "session_existe": "SÍ (si estás logueado)",
    "usuario": { ... },
    ...
  }
}
```

**Resultado:**
- ✅ Funciona JSON → El archivo funciona
- ❌ 404 HTML → El archivo no se encuentra o hay restricción

---

### PASO 3: Revisar error.log del servidor
Accede a cPanel o FTP y busca `error.log` en la raíz de tu sitio.

**Revisa las últimas líneas:**
```bash
tail -50 error.log
```

**Busca:**
- `[EARLY]` logs que agregamos
- `[!CRITICAL] /api/auth/token detected` — Indica que SÍ llegó a index.php
- Errores de PHP

---

### PASO 4: Probar desde el navegador
Después de confirmar que `/api/auth/token.php` funciona:

1. Navega a `https://idactivos.digital/restaurante/` (o tu URL de login admin)
2. Abre DevTools → Network
3. Navega a `https://idactivos.digital/rest-promocion/`
4. Busca la solicitud GET a `/api/auth/token.php`
5. ¿Status 200 con JSON?

---

## Interpretación de Resultados

### Escenario A: `/api/test.php` retorna 404 HTML
**Diagnóstico:** El servidor no permite acceso a `/api/`
**Causa probable:** Restricción de servidor, directorios no permitidos, permisos
**Acción:** Contactar hosting para habilitar `/api/`

### Escenario B: `/api/test.php` funciona, `/api/auth/token.php` retorna 404 HTML
**Diagnóstico:** El archivo no existe o hay problema con la ruta
**Causa probable:** El archivo no se creó correctamente
**Acción:** Verificar que `/api/auth/token.php` existe via FTP

### Escenario C: `/api/auth/token.php` funciona pero retorna error en JSON
**Diagnóstico:** El archivo se ejecuta pero hay error en la lógica
**Causa probable:** Problema con config.php o database.php
**Acción:** Revisar error.log para el error específico

### Escenario D: `/api/auth/token.php` funciona en navegador pero no desde JS
**Diagnóstico:** Problema de CORS o credenciales
**Causa probable:** Headers CORS o cookies no se envían
**Acción:** Revisar headers CORS en DevTools → Network → Response Headers

---

## Próximos Pasos Once Confirmado

**Si `/api/auth/token.php` funciona:**
1. Ya tiene el endpoint funcionando ✅
2. El cliente lo está usando ✅
3. Solo falta la lógica real de generar JWT (no el test)

**Entonces haremos:**
1. Reemplazar el contenido de `/api/auth/token.php` con la lógica real
2. Usar ApiController->auth('token') directamente
3. Probar el flujo completo

---

## Recuerda
- Los archivos se crearon en tu workspace local
- Necesitas hacer `git push` o FTP upload para que aparezcan en el servidor
- Los error.log están en el servidor, no en local
