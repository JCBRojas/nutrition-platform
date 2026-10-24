# NutriTrack de escritorio (Windows y macOS)

App Flutter de la operación diaria (despacho, recepción, dietas activas) contra la API v1 de NutriTrack (`/api/v1`, NUT-RN-004). Lo administrativo sigue en la web.

## Estado
- Paso 4 (listo): servidor configurable, versión mínima, inicio de sesión con 2FA, token en el almacenamiento seguro del sistema, empresa activa (`X-Company-Id`), menú según permisos y aviso de cumplimiento con enlace a la web.
- Paso 5 (listo): Despacho (servicio, pestañas, «Mostrar todas», búsqueda, cambios sin confirmar con su diferencia, despachar seleccionadas, «Anular despacho», «No llegó: vuelve a despacharla») y Recepción (confirmar varias, «No llegó» con motivo). Tiempo real por WebSocket (protocolo Pusher) con respaldo por consulta (3 s en Despacho, 5 s en Recepción) y alertas: sonido del sistema y notificación nativa, que se pueden silenciar. Contadores de pendientes en el menú lateral.
- Paso 6 (pendiente): Dietas activas y Despachos del día.

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
- `lib/home/`: marco principal (menú lateral con contadores, selector de empresa, cierre de sesión). Mantiene vivos el tablero de despacho y la recepción para que las alertas lleguen en cualquier sección.
- `lib/live/`: tiempo real (`LiveFeed`: `PusherLiveFeed` con `dart_pusher_channels`, canal privado autorizado en `/api/v1/broadcasting/auth`; `LiveTicker`, equivalente a `window.ntLive`) y alertas (`flutter_local_notifications` + `SystemSound`).
- `lib/dispatch/`, `lib/receipts/`: modelos, controladores (Riverpod) y pantallas de Despacho y Recepción.
- `lib/common/`: etiquetas del catálogo (colores `--dieta-*`), diálogos de confirmación y de motivo, paginación, estado «En vivo».

Atajos en Despacho: ⌘/Ctrl+F buscar, ⌘/Ctrl+R actualizar, ⌘/Ctrl+Enter despachar las seleccionadas.

La primera alerta en macOS pide permiso para mostrar notificaciones (Ajustes del Sistema → Notificaciones → NutriTrack). Sin ese permiso, suena igual y el aviso se ve en la app.
- `test/`: pruebas con un servidor simulado (`test/fake_server.dart`).
