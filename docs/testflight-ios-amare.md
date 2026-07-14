# Guia Para Subir Amare A TestFlight

Este documento resume el flujo para generar un build iOS de la app movil Amare con EAS y enviarlo a App Store Connect/TestFlight.

## Requisitos Previos

1. Tener sesion iniciada en Expo/EAS con la cuenta del proyecto `@impactosdigitales/amare-app`.
2. Tener acceso a Apple Developer/App Store Connect del equipo de Amare.
3. Confirmar que el backend de produccion tiene configurado:
   - `GOOGLE_CLIENT_ID=859009059542-0k2foa27gsah58utigs0kvp2nnsnvgnl.apps.googleusercontent.com`
   - `EXPO_PUBLIC_API_URL=https://amarerestaurant.club/api_restaurante`
4. Confirmar que `apps/mobile/app.json` contiene:
   - `ios.bundleIdentifier`: `com.amare.app`
   - `GoogleService-Info.plist`
   - plugin `@react-native-google-signin/google-signin`
   - `extra.googleWebClientId`

## Antes De Construir

Desde la raiz del repo:

```powershell
cd apps/mobile
.\node_modules\.bin\expo.CMD config --type public
..\..\node_modules\.bin\tsc.CMD --noEmit
```

Si TypeScript pasa y Expo muestra el plugin de Google Sign-In, el proyecto esta listo para build.

## Generar Build iOS

Usar el perfil `production`, porque TestFlight requiere un build de distribucion para App Store.

```powershell
cd apps/mobile
$env:NODE_OPTIONS='--use-system-ca'
eas build --platform ios --profile production
```

Notas:

- `eas.json` tiene `appVersionSource: remote` y `autoIncrement: true`, por lo que EAS incrementa el build number remoto automaticamente.
- Si EAS pregunta por credenciales, seleccionar la opcion de administrar/generar credenciales automaticamente.
- Si pide Apple ID, iniciar sesion con la cuenta autorizada en App Store Connect.

## Enviar A TestFlight

Cuando el build termine, se puede enviar de una de estas formas.

Opcion recomendada desde EAS:

```powershell
eas submit --platform ios --profile production
```

Cuando pregunte que enviar:

1. Seleccionar `Select a build from EAS`.
2. Elegir el build iOS mas reciente.
3. Esperar a que diga que el binario fue enviado correctamente a App Store Connect.

Opcion alternativa si ya tienes URL o archivo:

```powershell
eas submit --platform ios --profile production --url URL_DEL_ARCHIVE
```

## Revisar En App Store Connect

1. Entrar a App Store Connect.
2. Abrir la app Amare.
3. Ir a TestFlight > iOS.
4. Esperar procesamiento de Apple.
5. Cuando aparezca el build, agregarlo a testers internos o externos.

El procesamiento normalmente tarda entre 5 y 30 minutos, aunque Apple puede tardar mas.

## Pruebas Para Google Sign-In

En TestFlight validar:

1. Abrir app limpia o cerrar sesion.
2. Tocar `Continuar con Google`.
3. Seleccionar cuenta Google.
4. Confirmar que regresa a Amare.
5. Confirmar que entra al home correcto segun rol.
6. Revisar en backend que el usuario tenga `google_id`.

Si falla:

- Revisar que `GOOGLE_CLIENT_ID` en backend sea el Web Client ID.
- Revisar que App Store/TestFlight tenga el build nuevo, no uno anterior.
- Revisar que el bundle id sea `com.amare.app`.
- Revisar que el OAuth client de Google permita el bundle de iOS y package Android correctos.

## Checklist Rapido

- Build nuevo generado despues de agregar Google Sign-In.
- Build enviado a App Store Connect.
- Build procesado en TestFlight.
- Usuario tester agregado.
- Login con Google probado en dispositivo real.

