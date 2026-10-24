# NutriTrack de escritorio (Windows y macOS)

App Flutter de la operación diaria (despacho, recepción, dietas activas) contra la API v1 de NutriTrack (`/api/v1`, NUT-RN-004). Lo administrativo sigue en la web.

## Estado
- Paso 4 (listo): servidor configurable, versión mínima, inicio de sesión con 2FA, token en el almacenamiento seguro del sistema, empresa activa (`X-Company-Id`), menú según permisos y aviso de cumplimiento con enlace a la web.
- Pasos 5 y 6 (pendientes): pantallas de Despacho, Recepción, Dietas activas y Despachos del día, y alertas en tiempo real.

## Desarrollo
```bash
flutter pub get
flutter analyze
flutter test
flutter run -d macos      # en Windows: flutter run -d windows
```
La primera vez la app pide la dirección del servidor (la misma de la web). La API debe estar en esa instalación (rama `main` del backend).

## Estructura
- `lib/core/`: tema (paleta NUT-CMP-001), cliente de la API (`dio`, token y `X-Company-Id`), errores en español, almacenamiento.
- `lib/auth/`: modelos (`Meta`, `Me`, `Company`), sesión (`SessionController`, Riverpod) y pantallas de acceso.
- `lib/home/`: marco principal (menú lateral, selector de empresa, cierre de sesión).
- `test/`: pruebas con un servidor simulado (`test/fake_server.dart`).
