import 'dart:convert';
import 'dart:io';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:integration_test/integration_test.dart';
import 'package:nutritrack_desktop/auth/session.dart';
import 'package:nutritrack_desktop/core/storage.dart';
import 'package:nutritrack_desktop/live/alerts.dart';
import 'package:nutritrack_desktop/main.dart';

/// Prueba de humo contra un servidor real (Herd + Soketi), con la app de macOS o Windows de
/// verdad: tiempo real, alertas y pantallas con datos reales. No corre sin los parámetros:
///
///   flutter test integration_test/live_server_test.dart -d macos \
///     --dart-define=NT_SERVER=http://nutrition-platform.test \
///     --dart-define=NT_DISPATCH_TOKEN=… --dart-define=NT_NUTRI_TOKEN=… --dart-define=NT_DIET=ID_DE_LA_DIETA
///
/// Tokens de Sanctum de un despachador y un nutricionista, y una dieta de prueba activa de su
/// empresa con cena recibida hoy y «Continuar» hecho (la prueba la edita y le despacha la
/// fraccionada). Las capturas quedan en el directorio temporal de la app.
const server = String.fromEnvironment('NT_SERVER');
const dispatchToken = String.fromEnvironment('NT_DISPATCH_TOKEN');
const nutriToken = String.fromEnvironment('NT_NUTRI_TOKEN');
const dietId = int.fromEnvironment('NT_DIET');
const skip = server == '' || dispatchToken == '' || nutriToken == '' || dietId == 0;

final _shotKey = GlobalKey();

class RecordingAlerts implements Alerts {
  final sent = <(String, String)>[];

  @override
  Future<void> notify(String title, String body) async => sent.add((title, body));
}

/// Llamada directa a la API como otro usuario (lo que haría la web en otro equipo)
Future<Map<String, dynamic>> api(String method, String path, String token, [Map<String, dynamic>? body]) async {
  final client = HttpClient();
  try {
    final request = await client.openUrl(method, Uri.parse('$server/api/v1$path'));
    request.headers
      ..set('Accept', 'application/json')
      ..set('Content-Type', 'application/json')
      ..set('Authorization', 'Bearer $token');
    if (body != null) request.write(jsonEncode(body));
    final response = await request.close();
    final text = await response.transform(utf8.decoder).join();
    if (response.statusCode >= 300) throw StateError('$method $path → ${response.statusCode}: $text');
    return text.isEmpty ? {} : jsonDecode(text) as Map<String, dynamic>;
  } finally {
    client.close();
  }
}

Future<RecordingAlerts> launch(WidgetTester tester, String token) async {
  final alerts = RecordingAlerts();
  await tester.pumpWidget(RepaintBoundary(
    key: _shotKey,
    child: ProviderScope(
      overrides: [
        storageProvider.overrideWithValue(MemoryStorage(server: server, savedToken: token)),
        appVersionProvider.overrideWithValue('1.0.0'),
        deviceNameProvider.overrideWithValue('Prueba de integración'),
        alertsProvider.overrideWithValue(alerts),
      ],
      child: const NutriTrackApp(),
    ),
  ));
  return alerts;
}

/// Espera en tiempo real (red y WebSocket de verdad) hasta que aparezca [finder]
Future<Duration> waitFor(WidgetTester tester, Finder finder, {Duration timeout = const Duration(seconds: 15)}) async {
  final watch = Stopwatch()..start();
  while (watch.elapsed < timeout) {
    await tester.runAsync(() => Future<void>.delayed(const Duration(milliseconds: 100)));
    await tester.pump();
    if (finder.evaluate().isNotEmpty) return watch.elapsed;
  }
  throw TestFailure('No apareció $finder en ${timeout.inSeconds} s');
}

Future<void> shot(WidgetTester tester, String name) async {
  await tester.runAsync(() async {
    final boundary = tester.renderObject<RenderRepaintBoundary>(find.byKey(_shotKey));
    final image = await boundary.toImage(pixelRatio: 1);
    final png = await image.toByteData(format: ui.ImageByteFormat.png);
    final file = File('${Directory.systemTemp.path}/nt-$name.png');
    await file.writeAsBytes(png!.buffer.asUint8List());
    // ignore: avoid_print
    print('Captura: ${file.path}');
  });
}

Future<void> open(WidgetTester tester, String section) async {
  await tester.tap(find.descendant(of: find.byType(NavigationRail), matching: find.text(section)));
  await tester.pump();
}

void main() {
  IntegrationTestWidgetsFlutterBinding.ensureInitialized();

  testWidgets('despacho: el cambio de dieta llega en vivo con alerta y se confirma', skip: skip, (tester) async {
    final alerts = await launch(tester, dispatchToken);
    await waitFor(tester, find.text('Despachar seleccionadas (0)'));
    await waitFor(tester, find.text('En vivo'));
    await shot(tester, '1-despacho');

    // El nutricionista cambia la dieta desde «otro equipo»: debe llegar por WebSocket (sin él, cada 3 s)
    await tester.runAsync(() => api('PUT', '/diets/$dietId', nutriToken, {
          'timeFood': ['cena', 'fraccionada'],
          'consistency': ['normal'],
          'specifications': ['hiposodica'],
          'isolation': 'No',
          'changes': 'con_cambios',
        }));
    final took = await waitFor(tester, find.bySemanticsLabel('Confirmar cambio de la habitación T99'), timeout: const Duration(seconds: 10));
    // ignore: avoid_print
    print('El cambio llegó en ${took.inMilliseconds} ms');
    expect(alerts.sent.where((a) => a.$2.contains('T99')), isNotEmpty, reason: 'alerta del cambio');
    await shot(tester, '2-despacho-cambio');

    // El botón «Confirmar» de T99 (puede haber otros cambios pendientes)
    await tester.tap(find.ancestor(of: find.bySemanticsLabel('Confirmar cambio de la habitación T99').first, matching: find.byType(FilledButton)).first);
    await tester.pump();
    await waitFor(tester, find.textContaining('Cambios de la hab. T99 confirmados'));
    await shot(tester, '3-despacho-confirmado');
  });

  testWidgets('nutrición: panel, despacho en vivo en Recepción, «No llegó» y despachos del día', skip: skip, (tester) async {
    final alerts = await launch(tester, nutriToken);
    await waitFor(tester, find.text('Prueba Escritorio'));
    await waitFor(tester, find.text('En vivo'));
    expect(find.text('Mañana'), findsWidgets);
    await shot(tester, '4-dietas');

    // Editar: el formulario abre con los datos actuales (sin tipo de facturación: se deduce)
    final edit = find.byTooltip('Editar dieta de Prueba Escritorio');
    await tester.ensureVisible(edit);
    await tester.pump();
    await tester.tap(edit);
    await waitFor(tester, find.text('Editar dieta · Hab. T99'));
    expect(find.text('Tipo de dieta (facturación) *'), findsNothing);
    await shot(tester, '5-editar');
    await tester.tap(find.widgetWithText(OutlinedButton, 'Cancelar'));
    await tester.pump();

    await open(tester, 'Recepción');
    await waitFor(tester, find.text('Recepción'));
    // Cocina despacha la fraccionada: debe aparecer con alerta
    final dispatched = await tester.runAsync(() => api('POST', '/dispatch', dispatchToken, {'diet_ids': [dietId], 'meal': 'fraccionada'}));
    expect(dispatched?['dispatched'], ['T99'], reason: 'respuesta del despacho: $dispatched');
    await waitFor(tester, find.text('Prueba Escritorio'), timeout: const Duration(seconds: 10));
    expect(alerts.sent.where((a) => a.$1 == 'Cocina despachó'), isNotEmpty, reason: 'alerta del despacho');
    await shot(tester, '6-recepcion');

    await tester.tap(find.bySemanticsLabel(RegExp('No llegó: habitación T99')));
    await tester.pump();
    await waitFor(tester, find.text('Reportar que no llegó'));
    await tester.enterText(find.byType(TextField).last, 'Prueba de integración');
    await tester.tap(find.text('Reportar que no llegó'));
    await tester.pump();
    await waitFor(tester, find.textContaining('no llegó. Cocina lo verá'));

    await open(tester, 'Despachos del día');
    await waitFor(tester, find.text('«Prueba de integración»'));
    await shot(tester, '7-despachos-del-dia');
  });
}
