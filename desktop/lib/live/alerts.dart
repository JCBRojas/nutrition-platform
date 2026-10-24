import 'package:flutter/services.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../auth/session.dart';

/// Alerta al usuario: sonido del sistema y notificación nativa (Centro de notificaciones en
/// macOS, notificaciones de Windows). Equivale a «Activar alertas» de la web.
abstract class Alerts {
  Future<void> notify(String title, String body);
}

class DesktopAlerts implements Alerts {
  final _plugin = FlutterLocalNotificationsPlugin();
  bool? _ready;
  int _id = 0;

  /// La notificación va sin sonido propio: suena una vez el del sistema, aunque no haya permiso
  static final _details = NotificationDetails(
    macOS: const DarwinNotificationDetails(presentAlert: true, presentBanner: true, presentList: true, presentSound: false),
    windows: WindowsNotificationDetails(audio: WindowsNotificationAudio.silent()),
  );

  @override
  Future<void> notify(String title, String body) async {
    await SystemSound.play(SystemSoundType.alert);
    try {
      // La primera vez macOS pide permiso para mostrar notificaciones
      _ready ??= await _plugin.initialize(
            settings: const InitializationSettings(
              macOS: DarwinInitializationSettings(requestBadgePermission: false),
              windows: WindowsInitializationSettings(
                appName: 'NutriTrack',
                appUserModelId: 'Co.NutriTrack.Desktop',
                guid: 'd7a3c2e1-5b4f-4e8a-9c61-2f0b8e7d4a95',
              ),
            ),
          ) ??
          false;
      if (_ready!) await _plugin.show(id: _id++, title: title, body: body, notificationDetails: _details);
    } catch (_) {
      // Sin permiso de notificaciones: queda el sonido y el aviso dentro de la app
    }
  }
}

final alertsProvider = Provider<Alerts>((ref) => DesktopAlerts());

/// Alertas activadas (sonido y notificación); se recuerda en el equipo. Por defecto, sí.
final alertsEnabledProvider = NotifierProvider<AlertsEnabled, bool>(AlertsEnabled.new);

class AlertsEnabled extends Notifier<bool> {
  @override
  bool build() {
    Future.microtask(() async {
      final saved = await ref.read(storageProvider).alertsEnabled();
      if (ref.mounted) state = saved;
    });
    return true;
  }

  Future<void> set(bool value) async {
    state = value;
    await ref.read(storageProvider).setAlertsEnabled(value);
  }
}

/// Avisa solo si el usuario tiene las alertas activadas
Future<void> alert(Ref ref, String title, String body) async {
  if (ref.read(alertsEnabledProvider)) await ref.read(alertsProvider).notify(title, body);
}
