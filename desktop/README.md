# NutriTrack de escritorio (Windows y macOS)

App Flutter de la operación diaria (despacho, recepción, dietas activas) contra la API v1 de NutriTrack (`/api/v1`, NUT-RN-004). Lo administrativo sigue en la web.

## Estado
- Paso 4 (listo): servidor configurable, versión mínima, inicio de sesión con 2FA, token en el almacenamiento seguro del sistema, empresa activa (`X-Company-Id`), menú según permisos y aviso de cumplimiento con enlace a la web.
- Paso 5 (listo): Despacho (servicio, pestañas, «Mostrar todas», búsqueda, cambios sin confirmar con su diferencia, despachar seleccionadas, «Anular despacho», «No llegó: vuelve a despacharla») y Recepción (confirmar varias, «No llegó» con motivo). Tiempo real por WebSocket (protocolo Pusher) con respaldo por consulta (3 s en Despacho, 5 s en Recepción) y alertas: sonido del sistema y notificación nativa, que se pueden silenciar. Contadores de pendientes en el menú lateral.
- Paso 6 (listo): Dietas activas (indicadores, pestañas Activas/Canceladas/Finalizadas, búsqueda, estado del día y lo entregado, «Continuar», registrar y editar con el aviso de habitación ocupada, cancelar y dar de alta con el resumen de cierre y el envío a otro lugar; solo lectura sin permisos de registro o edición) y Despachos del día (Premium: fecha, servicio, estado, resumen por tiempo, anulados y que no llegaron).
- Paso 7 (listo): tamaño mínimo de ventana (1100 × 700), paquetes firmados (.dmg notarizado en macOS, .msix en Windows) y GitHub Actions (`.github/workflows/desktop.yml`).

## Desarrollo
```bash
flutter pub get
flutter analyze
flutter test
flutter run -d macos      # en Windows: flutter run -d windows
```
La primera vez la app pide la dirección del servidor (la misma de la web). La API debe estar en esa instalación (rama `main` del backend).

## Prueba contra un servidor real

`integration_test/live_server_test.dart` abre la app de verdad (macOS o Windows) contra un servidor con Soketi: verifica que un cambio de dieta llegue en vivo con su alerta, lo confirma, despacha y reporta «No llegó» desde Recepción, y guarda capturas en el directorio temporal de la app. Sin los parámetros se omite.

```bash
flutter test integration_test/live_server_test.dart -d macos \
  --dart-define=NT_SERVER=http://nutrition-platform.test \
  --dart-define=NT_DISPATCH_TOKEN=… --dart-define=NT_NUTRI_TOKEN=… --dart-define=NT_DIET=…
```

Necesita tokens de Sanctum de un despachador y un nutricionista (p. ej. `$user->createToken('prueba', ['*'], now()->addHours(3))` en `php artisan tinker`) y una dieta de prueba activa con la cena recibida hoy y «Continuar» hecho. Usar una dieta de prueba y borrarla después, junto con los tokens.

## Distribución

**CI** (`.github/workflows/desktop.yml`): con cada push a `main` que toque `desktop/` (y en los PR) corre `flutter analyze` y `flutter test`, y genera los paquetes de macOS y Windows como artefactos del run. Con una etiqueta `desktop-vX.Y.Z` (igual a la `version` de `pubspec.yaml`) publica además una versión en GitHub con los paquetes:

```bash
git tag desktop-v1.0.0 && git push origin desktop-v1.0.0
```

Sin los secretos de firma, los paquetes salen sin firmar (o con un certificado de prueba en Windows) y solo sirven para pruebas internas.

| Secreto | Para qué |
|---|---|
| `MACOS_CERTIFICATE`, `MACOS_CERTIFICATE_PASSWORD` | Certificado «Developer ID Application» (.p12 en base64: `base64 -i cert.p12 \| pbcopy`) |
| `MACOS_SIGN_IDENTITY` | Nombre del certificado: `Developer ID Application: Nombre (TEAMID)` |
| `APPLE_API_KEY`, `APPLE_API_KEY_ID`, `APPLE_API_ISSUER` | Clave de la API de App Store Connect (.p8 en base64) para notarizar |
| `WINDOWS_CERTIFICATE`, `WINDOWS_CERTIFICATE_PASSWORD` | Certificado de firma de código (.pfx en base64) |
| `WINDOWS_PUBLISHER` | Sujeto exacto del certificado (`CN=…, O=…, C=CO`): el MSIX lo exige igual |

**macOS a mano:** `./scripts/package_macos.sh` compila, firma (con `MACOS_SIGN_IDENTITY`), crea `dist/NutriTrack-X.Y.Z-macos.dmg` y lo notariza (con `APPLE_API_KEY_PATH`, `APPLE_API_KEY_ID` y `APPLE_API_ISSUER`). Con `MACOS_SIGN_IDENTITY=-` hace una firma ad-hoc de prueba (sin runtime endurecido: solo abre en el equipo que la firmó).

**Windows a mano** (en Windows): `flutter build windows --release` y `dart run msix:create --build-windows false --certificate-path firma.pfx --certificate-password … --publisher "CN=…"`. La configuración del MSIX está en `msix_config` de `pubspec.yaml` (incluye el activador de notificaciones con el mismo guid de `lib/live/alerts.dart`).

**Tamaño mínimo de la ventana:** 1100 × 700 (`lib/core/window.dart`), aplicado en `macos/Runner/MainFlutterWindow.swift` y `windows/runner/win32_window.h`/`.cpp` (escalado al DPI del monitor). `test/window_test.dart` comprueba que todas las secciones caben en ese tamaño.

## Estructura
- `lib/core/`: tema (paleta NUT-CMP-001), cliente de la API (`dio`, token y `X-Company-Id`), errores en español, almacenamiento.
- `lib/auth/`: modelos (`Meta`, `Me`, `Company`), sesión (`SessionController`, Riverpod) y pantallas de acceso.
- `lib/home/`: marco principal (menú lateral con contadores, selector de empresa, cierre de sesión). Mantiene vivos el tablero de despacho y la recepción para que las alertas lleguen en cualquier sección.
- `lib/live/`: tiempo real (`LiveFeed`: `PusherLiveFeed` con `dart_pusher_channels`, canal privado autorizado en `/api/v1/broadcasting/auth`; `LiveTicker`, equivalente a `window.ntLive`) y alertas (`flutter_local_notifications` + `SystemSound`).
- `lib/dispatch/`, `lib/receipts/`: modelos, controladores (Riverpod) y pantallas de Despacho y Recepción.
- `lib/diets/`: panel de dietas, formulario de registrar/editar (`diet_form.dart`) y modal de cancelar o dar de alta (`close_dialog.dart`).
- `lib/dispatch_log/`: Despachos del día.
- `lib/common/`: etiquetas del catálogo (colores `--dieta-*`), diálogos de confirmación y de motivo, paginación, estado «En vivo».

Atajos en Despacho: ⌘/Ctrl+F buscar, ⌘/Ctrl+R actualizar, ⌘/Ctrl+Enter despachar las seleccionadas. En Dietas activas: ⌘/Ctrl+F, ⌘/Ctrl+R y ⌘/Ctrl+N registrar.

La primera alerta en macOS pide permiso para mostrar notificaciones (Ajustes del Sistema → Notificaciones → NutriTrack). Sin ese permiso, suena igual y el aviso se ve en la app.
- `test/`: pruebas con un servidor simulado (`test/fake_server.dart`) y la app completa (`test/harness.dart`).
