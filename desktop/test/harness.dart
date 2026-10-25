import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nutritrack_desktop/auth/session.dart';
import 'package:nutritrack_desktop/core/storage.dart';
import 'package:nutritrack_desktop/home/home_shell.dart';
import 'package:nutritrack_desktop/live/alerts.dart';
import 'package:nutritrack_desktop/live/live_feed.dart';

import 'fake_server.dart';

/// Catálogos de /meta para las pantallas de operación
final meta = <String, Object>{
  ...metaJson,
  'catalogs': {
    'meals': [
      {'key': 'desayuno', 'label': 'Desayuno', 'time': '07:00'},
      {'key': 'almuerzo', 'label': 'Almuerzo', 'time': '12:00'},
      {'key': 'cena', 'label': 'Cena', 'time': '18:00'},
    ],
    'consistency': {'normal': 'Normal', 'blanda_mecanica': 'Blanda mecánica'},
    'specifications': {'hiposodica': 'Hiposódica'},
  },
};

/// App completa con servidor, tiempo real y alertas simulados
class Harness {
  Harness({List<String> permissions = const ['confirmDelivery'], List<String> premium = const [], String home = 'web', bool connected = true, bool alertsOn = true})
    : feed = FakeLiveFeed(connected: connected),
      storage = MemoryStorage(server: 'https://nutritrack.test', savedToken: 't', alerts: alertsOn) {
    server
      ..on('GET', '/meta', meta)
      ..on('GET', '/me', meJson(permissions: permissions, home: home, premium: premium));
  }

  final server = FakeServer();
  final FakeLiveFeed feed;
  final alerts = RecordingAlerts();
  final MemoryStorage storage;

  Future<void> start(WidgetTester tester, {Size size = const Size(1600, 1000)}) async {
    tester.view.physicalSize = size;
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          storageProvider.overrideWithValue(storage),
          appVersionProvider.overrideWithValue('1.0.0'),
          deviceNameProvider.overrideWithValue('Equipo de pruebas'),
          apiClientFactoryProvider.overrideWithValue(server.client),
          liveFeedFactoryProvider.overrideWithValue((api, meta, company) => feed),
          alertsProvider.overrideWithValue(alerts),
        ],
        child: const MaterialApp(home: AuthGate()),
      ),
    );
    await tester.pumpAndSettle();
  }

  int count(String method, String path) => server.requests.where((r) => r.method == method && r.uri.path == '/api/v1$path').length;

  dynamic body(String method, String path) => server.requests.lastWhere((r) => r.method == method && r.uri.path == '/api/v1$path').data;
}
