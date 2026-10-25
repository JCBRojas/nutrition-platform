import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nutritrack_desktop/auth/session.dart';
import 'package:nutritrack_desktop/core/storage.dart';
import 'package:nutritrack_desktop/home/home_shell.dart';

import 'fake_server.dart';

Widget app(FakeServer server, MemoryStorage storage, {String version = '1.0.0'}) => ProviderScope(
      overrides: [
        storageProvider.overrideWithValue(storage),
        appVersionProvider.overrideWithValue(version),
        deviceNameProvider.overrideWithValue('Equipo de pruebas'),
        apiClientFactoryProvider.overrideWithValue(server.client),
      ],
      child: const MaterialApp(home: AuthGate()),
    );

void main() {
  testWidgets('primera vez: pide el servidor, luego inicia sesión y muestra el menú según permisos', (tester) async {
    final server = FakeServer()
      ..on('GET', '/meta', metaJson)
      ..on('POST', '/auth/login', {'token': 't-123', 'expires_at': '2026-10-25T00:00:00Z'})
      ..on('GET', '/me', meJson(permissions: ['confirmDelivery', 'viewDiets']));
    final storage = MemoryStorage();

    await tester.pumpWidget(app(server, storage));
    await tester.pumpAndSettle();
    expect(find.text('Servidor'), findsOneWidget);

    await tester.enterText(find.byType(TextField), 'nutritrack.test');
    await tester.tap(find.text('Conectar'));
    await tester.pumpAndSettle();
    expect(storage.server, 'https://nutritrack.test');
    expect(find.text('Iniciar sesión'), findsOneWidget);

    await tester.enterText(find.byType(TextField).at(0), 'mariana@nutritrack.com');
    await tester.enterText(find.byType(TextField).at(1), 'Secreta-2026');
    await tester.tap(find.text('Ingresar'));
    await tester.pumpAndSettle();

    expect(storage.savedToken, 't-123');
    expect(find.text('Despacho'), findsWidgets);
    expect(find.text('Recepción'), findsNothing); // sin confirmReception
    expect(find.text('Clínica Central'), findsOneWidget);
    final me = server.requests.lastWhere((r) => r.path == '/me');
    expect(me.headers['Authorization'], 'Bearer t-123');
  });

  testWidgets('con token guardado entra directo; si venció vuelve al login', (tester) async {
    final server = FakeServer()
      ..on('GET', '/meta', metaJson)
      ..on('GET', '/me', {'message': 'Unauthenticated.'}, status: 401);
    final storage = MemoryStorage(server: 'https://nutritrack.test', savedToken: 'vencido');

    await tester.pumpWidget(app(server, storage));
    await tester.pumpAndSettle();

    expect(find.text('Tu sesión venció. Inicia sesión de nuevo.'), findsOneWidget);
    expect(storage.savedToken, isNull);
  });

  testWidgets('pide el código 2FA cuando el usuario lo tiene', (tester) async {
    final server = FakeServer()
      ..on('GET', '/meta', metaJson)
      ..on('POST', '/auth/login', {'two_factor_required': true, 'ticket': 'tk'})
      ..on('POST', '/auth/two-factor', {'token': 't-2fa'})
      ..on('GET', '/me', meJson());
    final storage = MemoryStorage(server: 'https://nutritrack.test');

    await tester.pumpWidget(app(server, storage));
    await tester.pumpAndSettle();
    await tester.enterText(find.byType(TextField).at(0), 'a@nutritrack.com');
    await tester.enterText(find.byType(TextField).at(1), 'x');
    await tester.tap(find.text('Ingresar'));
    await tester.pumpAndSettle();

    expect(find.text('Verificación en dos pasos'), findsOneWidget);
    await tester.enterText(find.byType(TextField), '123456');
    await tester.tap(find.text('Verificar'));
    await tester.pumpAndSettle();

    expect(storage.savedToken, 't-2fa');
    expect(server.requests.firstWhere((r) => r.path == '/auth/two-factor').data['ticket'], 'tk');
  });

  testWidgets('versión menor a la mínima: pide actualizar', (tester) async {
    final server = FakeServer()..on('GET', '/meta', {...metaJson, 'min_desktop_version': '2.0.0'});
    await tester.pumpWidget(app(server, MemoryStorage(server: 'https://nutritrack.test'), version: '1.0.0'));
    await tester.pumpAndSettle();

    expect(find.text('Actualiza la aplicación'), findsOneWidget);
  });

  testWidgets('cambiar de empresa envía X-Company-Id y la recuerda', (tester) async {
    final companies = [
      {'id': 1, 'name': 'Clínica Central', 'is_default': true},
      {'id': 2, 'name': 'Clínica Norte', 'is_default': false},
    ];
    final server = FakeServer()
      ..on('GET', '/meta', metaJson)
      ..on('GET', '/me', meJson(companies: companies));
    final storage = MemoryStorage(server: 'https://nutritrack.test', savedToken: 't');

    await tester.pumpWidget(app(server, storage));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Clínica Central'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Clínica Norte').last);
    await tester.pumpAndSettle();

    expect(storage.company, 2);
    expect(server.requests.last.headers['X-Company-Id'], '2');
  });

  testWidgets('falta aceptar la política: aviso con enlace a la web', (tester) async {
    final server = FakeServer()
      ..on('GET', '/meta', metaJson)
      ..on('GET', '/me', meJson(privacy: true));
    await tester.pumpWidget(app(server, MemoryStorage(server: 'https://nutritrack.test', savedToken: 't')));
    await tester.pumpAndSettle();

    expect(find.textContaining('Debes aceptar la política'), findsOneWidget);
  });
}
