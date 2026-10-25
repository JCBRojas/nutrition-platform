import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nutritrack_desktop/live/live_feed.dart';

import 'fake_server.dart';
import 'harness.dart';

Map<String, dynamic> diet(int id, String room, {bool pending = false, String? block, Map<String, dynamic>? dispatch, Map<String, dynamic>? changes, Map<String, dynamic>? missing}) => {
      'id': id,
      'habitation': room,
      'name_patient': 'Paciente $room',
      'status': 1,
      'is_active': true,
      'version': 2,
      'time_food': ['desayuno', 'almuerzo'],
      'consistency': ['blanda_mecanica'],
      'specifications': ['hiposodica'],
      'observations': 'Sin lácteos',
      'isolation': 'No',
      'has_pending': pending,
      'pending_changes': changes ?? [],
      'last_change': pending ? 'Cambió consistencia' : 'Creación',
      'transfer_meals': [],
      'transfer_destination': null,
      'authorized_for_day': true,
      'block_reason': block,
      'dispatch': dispatch,
      'missing': missing,
      'delivery': {'desayuno': 'received'},
    };

Map<String, dynamic> pendingChange(int dietId, String room, int version) =>
    {'diet_id': dietId, 'key': '$dietId:$version', 'habitation': room, 'name_patient': 'Paciente $room', 'last_change': 'Cambió consistencia', 'is_active': true};

void main() {
  group('Despacho', () {
    testWidgets('muestra el tablero, los cambios sin confirmar y despacha las seleccionadas', (tester) async {
      final h = Harness();
      h.server
        ..on('GET', '/dispatch/board', boardJson(
          diets: [
            diet(2, '102', pending: true, block: 'Tiene cambios sin confirmar.', changes: {
              'consistency': {'added': ['blanda_mecanica'], 'removed': ['normal']},
            }),
            diet(1, '101'),
          ],
          dispatchable: [1],
          pending: [pendingChange(2, '102', 2)],
        ))
        ..on('POST', '/dispatch', {'dispatched': ['101'], 'skipped': []});
      await h.start(tester);

      expect(find.text('1 cambio sin confirmar'), findsOneWidget);
      expect(find.text('Tiene cambios sin confirmar.'), findsOneWidget);
      expect(find.text('Lista para despachar'), findsOneWidget);
      // Lo quitado se ve tachado y lo entregado hoy con check
      expect(tester.widget<Text>(find.text('Normal')).style?.decoration, TextDecoration.lineThrough);
      expect(find.byIcon(Icons.check), findsNWidgets(2));
      // Contador de cambios en el menú lateral
      expect(find.descendant(of: find.byType(Badge), matching: find.text('1')), findsOneWidget);
      expect(find.text('Despachar seleccionadas (0)'), findsOneWidget);

      await tester.tap(find.text('Paciente 101'));
      await tester.pump();
      await tester.tap(find.text('Paciente 102').last); // con cambios: no se puede seleccionar
      await tester.pump();
      await tester.tap(find.text('Despachar seleccionadas (1)'));
      await tester.pumpAndSettle();
      expect(find.textContaining('¿Despachar 1 dieta de almuerzo?'), findsOneWidget);
      await tester.tap(find.widgetWithText(FilledButton, 'Despachar'));
      await tester.pumpAndSettle();

      expect(h.body('POST', '/dispatch'), {'diet_ids': [1], 'meal': 'almuerzo'});
      expect(find.textContaining('Despachadas: hab. 101'), findsOneWidget);
    });

    testWidgets('alerta una sola vez cuando llega un cambio nuevo por tiempo real', (tester) async {
      final h = Harness();
      await h.start(tester);
      expect(h.alerts.sent, isEmpty);

      h.server.on('GET', '/dispatch/board', boardJson(diets: [diet(2, '102', pending: true)], pending: [pendingChange(2, '102', 3)]));
      h.feed.push({'diet'});
      await tester.pumpAndSettle();

      expect(h.alerts.sent.single.$1, 'Cambio de dieta');
      expect(h.alerts.sent.single.$2, contains('Hab. 102 · Cambió consistencia'));
      expect(find.text('1 cambio sin confirmar'), findsOneWidget);

      // El mismo cambio no vuelve a alertar; una versión nueva sí
      h.feed.push({'dispatch'});
      await tester.pumpAndSettle();
      expect(h.alerts.sent, hasLength(1));
      h.server.on('GET', '/dispatch/board', boardJson(diets: [diet(2, '102', pending: true)], pending: [pendingChange(2, '102', 4)]));
      h.feed.push({'diet'});
      await tester.pumpAndSettle();
      expect(h.alerts.sent, hasLength(2));
    });

    testWidgets('con las alertas silenciadas no suena ni notifica', (tester) async {
      final h = Harness(alertsOn: false);
      await h.start(tester);

      h.server.on('GET', '/dispatch/board', boardJson(pending: [pendingChange(2, '102', 3)]));
      h.feed.push({'diet'});
      await tester.pumpAndSettle();

      expect(h.alerts.sent, isEmpty);
    });

    testWidgets('confirma un cambio y anula un despacho con motivo', (tester) async {
      final h = Harness();
      h.server
        ..on('GET', '/dispatch/board', boardJson(
          diets: [
            diet(2, '102', pending: true, block: 'Tiene cambios sin confirmar.'),
            diet(1, '101', dispatch: {'id': 55, 'dispatched_at': '2026-10-24T17:05:00Z', 'dispatched_by': 'Cocina', 'received_at': null}),
          ],
          pending: [pendingChange(2, '102', 2)],
        ))
        ..on('POST', '/diets/2/confirm-changes', {'message': 'Cambios de la hab. 102 confirmados.'})
        ..on('POST', '/dispatches/55/void', {'id': 9, 'diet_id': 1, 'meal': 'almuerzo', 'kind': 'cancelled', 'label': 'Anulado por cocina'});
      await h.start(tester);

      await tester.tap(find.bySemanticsLabel('Confirmar cambio de la habitación 102').first);
      await tester.pumpAndSettle();
      expect(h.count('POST', '/diets/2/confirm-changes'), 1);
      expect(find.text('Cambios de la hab. 102 confirmados.'), findsOneWidget);

      expect(find.text('Por Cocina'), findsOneWidget);
      await tester.tap(find.text('Anular despacho'));
      await tester.pumpAndSettle();
      await tester.enterText(find.byType(TextField).last, 'Se marcó la habitación equivocada');
      await tester.tap(find.widgetWithText(FilledButton, 'Anular despacho'));
      await tester.pumpAndSettle();

      expect(h.body('POST', '/dispatches/55/void'), {'kind': 'cancelled', 'reason': 'Se marcó la habitación equivocada'});
      expect(find.textContaining('Despacho anulado: hab. 101'), findsOneWidget);
    });

    testWidgets('«No llegó» se muestra para volver a despachar', (tester) async {
      final h = Harness();
      h.server.on('GET', '/dispatch/board', boardJson(diets: [diet(1, '101', missing: {'reason': 'Bandeja fría', 'voided_at': '2026-10-24T17:30:00Z'})], dispatchable: [1]));
      await h.start(tester);

      expect(find.text('No llegó: vuelve a despacharla'), findsOneWidget);
    });

    testWidgets('sin WebSocket consulta cada 3 s', (tester) async {
      final h = Harness(connected: false);
      await h.start(tester);
      final before = h.count('GET', '/dispatch/board');

      await tester.pump(const Duration(seconds: 4));
      await tester.pumpAndSettle();
      expect(h.count('GET', '/dispatch/board'), greaterThan(before));
    });

    testWidgets('cambiar el servicio y la pestaña consulta con esos filtros', (tester) async {
      final h = Harness();
      await h.start(tester);

      await tester.tap(find.text('Almuerzo (12:00)'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Cena (18:00)').last);
      await tester.pumpAndSettle();
      expect(h.server.requests.last.queryParameters['servicio'], 'cena');

      await tester.tap(find.textContaining('Canceladas y finalizadas'));
      await tester.pumpAndSettle();
      expect(h.server.requests.last.queryParameters['tab'], 'closed');
    });
  });

  group('Recepción', () {
    final receipts = [
      {'id': 1, 'diet_id': 10, 'habitation': '101', 'name_patient': 'Ana', 'meal': 'almuerzo', 'meal_label': 'Almuerzo', 'service_date': '2026-10-24', 'dispatched_at': '2026-10-24T17:00:00Z', 'dispatched_by': 'Cocina'},
      {'id': 2, 'diet_id': 11, 'habitation': '102', 'name_patient': 'Luis', 'meal': 'almuerzo', 'meal_label': 'Almuerzo', 'service_date': '2026-10-24', 'dispatched_at': '2026-10-24T17:01:00Z', 'dispatched_by': 'Cocina'},
    ];

    testWidgets('confirma la recepción de todos y avisa el día completado', (tester) async {
      final h = Harness(permissions: ['confirmReception']);
      h.server
        ..on('GET', '/receipts/pending', {'data': receipts})
        ..on('POST', '/receipts', {'received': 2, 'completed': ['101']});
      await h.start(tester);

      expect(find.text('2 despachos por confirmar'), findsOneWidget);
      expect(find.text('Despacho'), findsNothing); // sin confirmDelivery
      await tester.tap(find.byType(Checkbox).first); // seleccionar todos
      await tester.pump();
      await tester.tap(find.text('Confirmar recepción (2)'));
      await tester.pumpAndSettle();
      expect(find.textContaining('Se contarán en la facturación'), findsOneWidget);

      h.server.on('GET', '/receipts/pending', {'data': []});
      await tester.tap(find.widgetWithText(FilledButton, 'Confirmar recepción'));
      await tester.pumpAndSettle();

      expect(h.body('POST', '/receipts'), {'dispatch_ids': [1, 2]});
      expect(find.textContaining('Día completado: hab. 101'), findsOneWidget);
      expect(find.text('No hay despachos por confirmar.'), findsOneWidget);
    });

    testWidgets('alerta cuando cocina despacha y solo escucha avisos de despachos', (tester) async {
      final h = Harness(permissions: ['confirmReception']);
      await h.start(tester);

      final before = h.count('GET', '/receipts/pending');
      h.feed.push({'diet'});
      await tester.pumpAndSettle();
      expect(h.count('GET', '/receipts/pending'), before);

      h.server.on('GET', '/receipts/pending', {'data': receipts});
      h.feed.push({'dispatch'});
      await tester.pumpAndSettle();

      expect(h.alerts.sent.single, ('Cocina despachó', 'Hab. 101, 102. Confirma la recepción.'));
      expect(find.descendant(of: find.byType(Badge), matching: find.text('2')), findsOneWidget);
    });

    testWidgets('reporta «No llegó» con motivo', (tester) async {
      final h = Harness(permissions: ['confirmReception']);
      h.server
        ..on('GET', '/receipts/pending', {'data': receipts})
        ..on('POST', '/dispatches/2/void', {'id': 3, 'diet_id': 11, 'meal': 'almuerzo', 'kind': 'not_arrived', 'label': 'No llegó'});
      await h.start(tester);

      await tester.tap(find.bySemanticsLabel('No llegó: habitación 102, Almuerzo'));
      await tester.pumpAndSettle();
      await tester.enterText(find.byType(TextField).last, 'No subió la bandeja');
      await tester.tap(find.widgetWithText(FilledButton, 'Reportar que no llegó'));
      await tester.pumpAndSettle();

      expect(h.body('POST', '/dispatches/2/void'), {'kind': 'not_arrived', 'reason': 'No subió la bandeja'});
      expect(find.textContaining('no llegó. Cocina lo verá'), findsOneWidget);
    });
  });

  test('los avisos de otra empresa se ignoran', () {
    expect(PusherLiveFeed.parseEvent({'kinds': ['diet'], 'companies': [2]}, 1), isNull);
    expect(PusherLiveFeed.parseEvent({'kinds': ['diet'], 'companies': [1, 2]}, 1), {'diet'});
    expect(PusherLiveFeed.parseEvent({'kinds': ['dispatch'], 'companies': []}, 1), {'dispatch'});
    expect(PusherLiveFeed.parseEvent(null, 1), isNull);
  });
}
