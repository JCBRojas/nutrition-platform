import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:nutritrack_desktop/common/widgets.dart';

import 'harness.dart';

const nutritionist = ['viewActiveDiets', 'createDiets', 'editDiets', 'continueDiets', 'viewDiets', 'confirmReception'];

Map<String, Object> metaWith({bool billingRequired = false}) => {
  ...meta,
  'catalogs': {
    ...(meta['catalogs'] as Map<String, Object>),
    'billing_types': {'normal': 'Normal', 'hiposodica': 'Hiposódica'},
  },
  'diet_form': {'billing_type_required': billingRequired, 'habitation_max': 4, 'name_max': 150},
};

Map<String, dynamic> activeDiet(int id, String room, {Map<String, dynamic>? day, Map<String, dynamic> delivery = const {}, String? changes, String? billing}) => {
  'id': id,
  'habitation': room,
  'name_patient': 'Paciente $room',
  'status': 1,
  'is_active': true,
  'version': 3,
  'timeFood': ['desayuno', 'almuerzo'],
  'consistency': ['normal'],
  'specifications': ['hiposodica'],
  'observations': 'Sin lácteos',
  'changes': changes,
  'billing_type': billing,
  'isolation': 'No',
  'updated_at': '2026-10-24T10:00:00-05:00',
  'updated_by': 'Nutricionista',
  'transfer': null,
  'delivery': delivery,
  'day': day ?? {'completed': false, 'authorizedToday': true, 'authorizedTomorrow': false},
};

Map<String, dynamic> dietsJson({List<Map<String, dynamic>> diets = const [], String tab = 'active', List<String> rooms = const []}) => {
  'tab': tab,
  'data': diets,
  'meta': {'current_page': 1, 'last_page': 1, 'per_page': 15, 'total': diets.length},
  'counts': {'active': tab == 'active' ? diets.length : 0, 'cancelled': tab == 'cancelled' ? diets.length : 0, 'finished': 2},
  'stats': {'isolated': 1, 'changes': 0},
  'active_rooms': rooms,
};

Harness nutrition({List<String> permissions = nutritionist, bool billingRequired = false, List<Map<String, dynamic>>? diets}) {
  final h = Harness(permissions: permissions, home: 'diets');
  h.server
    ..on('GET', '/meta', metaWith(billingRequired: billingRequired))
    ..on('GET', '/diets', dietsJson(diets: diets ?? [activeDiet(1, '101')], rooms: ['101']));
  return h;
}

/// La tabla se desplaza a lo ancho: las acciones pueden quedar fuera de la vista
Future<void> tapVisible(WidgetTester tester, Finder finder) async {
  await tester.ensureVisible(finder);
  await tester.pumpAndSettle();
  await tester.tap(finder);
}

void main() {
  group('Dietas activas', () {
    testWidgets('muestra el estado del día y lo entregado, y continúa la dieta para mañana', (tester) async {
      final h = nutrition(
        diets: [
          activeDiet(1, '101', delivery: {'desayuno': 'received', 'almuerzo': 'dispatched'}),
          activeDiet(2, '102', day: {'completed': true, 'authorizedToday': true, 'authorizedTomorrow': true}),
        ],
      );
      h.server.on('POST', '/diets/1/continue', {'message': 'Dieta de la habitación 101 autorizada para mañana.'});
      await h.start(tester);

      expect(find.text('Activas (2)'), findsOneWidget);
      expect(find.text('Finalizadas (2)'), findsOneWidget);
      expect(find.text('En curso'), findsOneWidget);
      expect(find.text('Completada'), findsOneWidget);
      expect(find.text('Mañana'), findsOneWidget);
      // Primera fila (101): desayuno entregado y almuerzo despachado sin recibir
      NtTag tag(String label) => tester.widget<NtTag>(find.widgetWithText(NtTag, label).first);
      expect(tag('Desayuno').delivery, Delivery.received);
      expect(tag('Almuerzo').delivery, Delivery.dispatched);

      await tester.tap(find.text('Continuar'));
      await tester.pumpAndSettle();
      expect(find.textContaining('¿Continuar la dieta de Paciente 101 (hab. 101) mañana?'), findsOneWidget);
      await tester.tap(find.widgetWithText(FilledButton, 'Continuar'));
      await tester.pumpAndSettle();

      expect(h.count('POST', '/diets/1/continue'), 1);
      expect(find.text('Dieta de la habitación 101 autorizada para mañana.'), findsOneWidget);
    });

    testWidgets('registrar avisa la habitación ocupada y envía los catálogos elegidos', (tester) async {
      final h = nutrition();
      h.server.on('POST', '/diets', {'id': 9, 'habitation': '101'}, status: 201);
      await h.start(tester);

      await tester.tap(find.text('Registrar dieta'));
      await tester.pumpAndSettle();
      // Sin datos: los mismos avisos que la web antes de enviar
      await tester.tap(find.widgetWithText(FilledButton, 'Registrar dieta').last);
      await tester.pump();
      expect(find.text('Escribe la habitación.'), findsOneWidget);
      expect(find.text('Elige al menos un tiempo de comida.'), findsOneWidget);
      expect(h.count('POST', '/diets'), 0);

      await tester.enterText(find.widgetWithText(TextField, 'Habitación *'), '101');
      await tester.enterText(find.widgetWithText(TextField, 'Nombre del paciente *'), 'Ana Pérez');
      await tester.pump();
      expect(find.textContaining('La habitación 101 ya tiene una dieta activa'), findsOneWidget);
      expect(tester.widget<FilledButton>(find.widgetWithText(FilledButton, 'Registrar dieta').last).onPressed, isNull);
      await tester.tap(find.byKey(const ValueKey('allow-duplicate-room')));
      await tester.pump();

      for (final label in ['Cena', 'Desayuno', 'Blanda mecánica', 'Hiposódica']) {
        await tester.tap(find.widgetWithText(FilterChip, label));
        await tester.pump();
      }
      await tester.tap(find.widgetWithText(FilledButton, 'Registrar dieta').last);
      await tester.pumpAndSettle();

      expect(h.body('POST', '/diets'), {
        'habitation': '101',
        'name_patient': 'Ana Pérez',
        'timeFood': ['desayuno', 'cena'], // en el orden del catálogo
        'consistency': ['blanda_mecanica'],
        'specifications': ['hiposodica'],
        'observations': null,
        'isolation': 'No',
        'allow_duplicate_room': true,
      });
      expect(find.text('Dieta registrada: hab. 101.'), findsOneWidget);
    });

    testWidgets('muestra junto al campo los errores del servidor y pide el tipo de facturación si no se deduce', (tester) async {
      final h = nutrition(billingRequired: true);
      h.server.on('POST', '/diets', {
        'message': 'La habitación 205 ya tiene una dieta activa.',
        'errors': {
          'habitation': ['La habitación 205 ya tiene una dieta activa. Edítala o, si es una habitación compartida, marca «Registrar de todas formas».'],
        },
      }, status: 422);
      await h.start(tester);

      await tester.tap(find.text('Registrar dieta'));
      await tester.pumpAndSettle();
      await tester.tap(find.widgetWithText(FilledButton, 'Registrar dieta').last);
      await tester.pump();
      expect(find.text('Elige el tipo de dieta (facturación).'), findsOneWidget);

      await tester.enterText(find.widgetWithText(TextField, 'Habitación *'), '205');
      await tester.enterText(find.widgetWithText(TextField, 'Nombre del paciente *'), 'Luis Gómez');
      for (final label in ['Almuerzo', 'Normal', 'Hiposódica']) {
        await tester.tap(find.widgetWithText(FilterChip, label));
        await tester.pump();
      }
      await tester.tap(find.text('Tipo de dieta (facturación) *'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Normal').last);
      await tester.pumpAndSettle();
      await tester.tap(find.widgetWithText(FilledButton, 'Registrar dieta').last);
      await tester.pumpAndSettle();

      expect(h.body('POST', '/diets')['billing_type'], 'normal');
      expect(find.textContaining('marca «Registrar de todas formas»'), findsWidgets);
      // La lista no tenía la habitación, pero el servidor sí: se ofrece registrar de todas formas
      expect(find.byKey(const ValueKey('allow-duplicate-room')), findsOneWidget);
    });

    testWidgets('editar crea una versión nueva con los datos actuales precargados', (tester) async {
      final h = nutrition(diets: [activeDiet(1, '101', changes: 'con_cambios')]);
      h.server.on('PUT', '/diets/1', activeDiet(1, '101'));
      await h.start(tester);

      await tapVisible(tester, find.byTooltip('Editar dieta de Paciente 101'));
      await tester.pumpAndSettle();
      expect(find.text('Editar dieta · Hab. 101'), findsOneWidget);
      expect(find.widgetWithText(TextField, 'Habitación *'), findsNothing);
      await tester.tap(find.widgetWithText(FilterChip, 'Cena'));
      await tester.pump();
      await tester.tap(find.widgetWithText(FilledButton, 'Actualizar'));
      await tester.pumpAndSettle();

      expect(h.body('PUT', '/diets/1'), {
        'timeFood': ['desayuno', 'almuerzo', 'cena'],
        'consistency': ['normal'],
        'specifications': ['hiposodica'],
        'observations': 'Sin lácteos',
        'isolation': 'No',
        'changes': 'con_cambios',
      });
      expect(find.textContaining('Dieta actualizada: hab. 101.'), findsOneWidget);
    });

    testWidgets('dar de alta muestra el resumen de cierre y envía la comida a otro lugar', (tester) async {
      final h = nutrition();
      h.server
        ..on('GET', '/diets/1/closing-summary', {
          'pending': [
            {'date': '24 de octubre', 'today': true, 'meal': 'Desayuno', 'at': '7:05 am'},
          ],
          'receivedToday': [],
          'candidates': {'almuerzo': 'Almuerzo', 'cena': 'Cena'},
        })
        ..on('POST', '/diets/1/finish', {'message': 'Dieta de la habitación 101 finalizada por alta.'});
      await h.start(tester);

      await tapVisible(tester, find.byTooltip('Dar de alta (finalizar dieta)'));
      await tester.pumpAndSettle();
      expect(find.text('Dar de alta · Hab. 101'), findsOneWidget);
      expect(find.text('Tiene 1 despacho sin confirmar la recepción:'), findsOneWidget);
      expect(find.text('• Desayuno — hoy, despachado 7:05 am'), findsOneWidget);

      await tester.tap(find.textContaining('Sí, enviar a otro lugar'));
      await tester.pumpAndSettle();
      await tester.tap(find.widgetWithText(FilledButton, 'Dar de alta'));
      await tester.pump();
      expect(find.text('Indica a dónde se debe enviar la comida.'), findsOneWidget);
      expect(h.count('POST', '/diets/1/finish'), 0);

      await tester.enterText(find.widgetWithText(TextField, 'Destino *'), 'IPS San José');
      // Casillas: «enviar a otro lugar», Almuerzo y Cena
      await tester.tap(find.byType(Checkbox).last);
      await tester.pump();
      await tester.tap(find.widgetWithText(FilledButton, 'Dar de alta'));
      await tester.pumpAndSettle();

      expect(h.body('POST', '/diets/1/finish'), {
        'transfer': {
          'destination': 'IPS San José',
          'meals': ['cena'],
        },
      });
      expect(find.text('Dieta de la habitación 101 finalizada por alta.'), findsOneWidget);
    });

    testWidgets('cancelar sin despachos lo indica y cancela', (tester) async {
      final h = nutrition();
      h.server
        ..on('GET', '/diets/1/closing-summary', {'pending': [], 'receivedToday': [], 'candidates': []})
        ..on('POST', '/diets/1/cancel', {'message': 'Dieta de la habitación 101 cancelada.'});
      await h.start(tester);

      await tapVisible(tester, find.byTooltip('Cancelar dieta'));
      await tester.pumpAndSettle();
      expect(find.text('No tiene despachos de hoy ni recepciones pendientes.'), findsOneWidget);
      expect(find.textContaining('enviar a otro lugar'), findsNothing);
      await tester.tap(find.widgetWithText(FilledButton, 'Cancelar dieta'));
      await tester.pumpAndSettle();

      expect(h.count('POST', '/diets/1/cancel'), 1);
      expect(find.text('Dieta de la habitación 101 cancelada.'), findsOneWidget);
    });

    testWidgets('sin permisos de registro ni edición queda de solo lectura', (tester) async {
      final h = nutrition(permissions: ['viewActiveDiets']);
      await h.start(tester);

      expect(find.text('Paciente 101'), findsOneWidget);
      expect(find.text('Registrar dieta'), findsNothing);
      expect(find.byTooltip('Editar dieta de Paciente 101'), findsNothing);
      expect(find.text('Continuar'), findsNothing);
      expect(find.text('Mañana —'), findsOneWidget);
    });

    testWidgets('las pestañas de cerradas piden la pestaña al servidor', (tester) async {
      final h = nutrition();
      await h.start(tester);

      h.server.on(
        'GET',
        '/diets',
        dietsJson(
          tab: 'cancelled',
          diets: [
            {...activeDiet(5, '305'), 'status': 0, 'is_active': false, 'day': null},
          ],
        ),
      );
      await tester.tap(find.text('Canceladas (0)'));
      await tester.pumpAndSettle();

      expect(h.server.requests.last.queryParameters['tab'], 'cancelled');
      expect(find.text('Paciente 305'), findsOneWidget);
      expect(find.text('Cancelada'), findsWidgets);
      expect(find.byTooltip('Cancelar dieta'), findsNothing);
    });
  });

  group('Despachos del día', () {
    testWidgets('muestra el resumen por tiempo, los despachos y los anulados', (tester) async {
      final h = Harness(permissions: ['viewDispatches'], premium: ['dispatch_log']);
      h.server.on('GET', '/dispatches/day', {
        'date': '2026-10-24',
        'data': [
          {
            'id': 1,
            'diet_id': 1,
            'habitation': '101',
            'name_patient': 'Paciente 101',
            'meal': 'desayuno',
            'dispatched_at': '2026-10-24T07:05:00-05:00',
            'dispatched_by': 'Cocina',
            'received_at': '2026-10-24T07:20:00-05:00',
            'received_by': 'Nutrición',
          },
          {
            'id': 2,
            'diet_id': 2,
            'habitation': '102',
            'name_patient': 'Paciente 102',
            'meal': 'desayuno',
            'dispatched_at': '2026-10-24T07:06:00-05:00',
            'dispatched_by': 'Cocina',
            'received_at': null,
            'received_by': null,
          },
        ],
        'meta': {'current_page': 1, 'last_page': 1, 'per_page': 15, 'total': 2},
        'summary': {
          'desayuno': {'label': 'Desayuno', 'dispatched': 2, 'received': 1, 'planned': 3},
          'cena': {'label': 'Cena', 'dispatched': 0, 'received': 0, 'planned': 0},
        },
        'voids': [
          {
            'id': 4,
            'habitation': '103',
            'meal': 'desayuno',
            'kind': 'not_arrived',
            'label': 'No llegó',
            'reason': 'Bandeja equivocada',
            'voided_at': '2026-10-24T07:30:00-05:00',
            'voided_by': 'Nutrición',
          },
        ],
      });
      await h.start(tester);

      expect(find.text('Despachos del día'), findsWidgets);
      expect(find.text('1 de 2 recibidos'), findsOneWidget);
      expect(find.text('2 de 3 despachados'), findsOneWidget);
      expect(find.textContaining('Recibido'), findsWidgets);
      expect(find.text('Por recibir'), findsWidgets);
      expect(find.text('Anulados y que no llegaron (1)'), findsOneWidget);
      expect(find.text('«Bandeja equivocada»'), findsOneWidget);

      await tester.tap(find.descendant(of: find.byType(SegmentedButton<String>), matching: find.text('Por recibir')));
      await tester.pumpAndSettle();
      expect(h.server.requests.last.queryParameters['estado'], 'pending');
    });
  });
}
