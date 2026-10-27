import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nutritrack_desktop/core/window.dart';

import 'diets_test.dart' show activeDiet, dietsJson, metaWith;
import 'fake_server.dart';
import 'harness.dart';
import 'nutrition_tools_test.dart' show historyList, report, timeline;
import 'operation_test.dart' show diet, pendingChange;

/// Las pantallas caben en la ventana mínima (macOS y Windows no dejan achicarla más):
/// un desborde de diseño hace fallar la prueba.
void main() {
  testWidgets('todas las secciones y el formulario de dieta caben en el tamaño mínimo de la ventana', (tester) async {
    final h = Harness(
      permissions: ['confirmDelivery', 'confirmReception', 'viewActiveDiets', 'createDiets', 'editDiets', 'continueDiets', 'viewDiets', 'viewDispatches', 'viewReports'],
      premium: ['dispatch_log', 'history'],
      home: 'dispatch',
      nutritionTools: true,
    );
    h.server
      ..on('GET', '/meta', metaWith(billingRequired: true))
      ..on('GET', '/dispatch/board', boardJson(
        diets: [diet(2, '102', pending: true, block: 'Tiene cambios sin confirmar.'), diet(1, '1001')],
        dispatchable: [1],
        pending: [pendingChange(2, '102', 2)],
      ))
      ..on('GET', '/receipts/pending', {
        'data': [
          {'id': 1, 'diet_id': 1, 'habitation': '1001', 'name_patient': 'Paciente con un nombre bastante largo', 'meal': 'almuerzo', 'meal_label': 'Almuerzo', 'dispatched_at': '2026-10-24T12:05:00-05:00', 'dispatched_by': 'Cocina'},
        ],
      })
      ..on('GET', '/diets', dietsJson(diets: [activeDiet(1, '1001', delivery: {'desayuno': 'received'})]))
      ..on('GET', '/history/diets', historyList())
      ..on('GET', '/diets/1/history', timeline())
      ..on('GET', '/reports/monthly', report())
      ..on('GET', '/dispatches/day', {
        'date': '2026-10-24',
        'data': [],
        'meta': {'current_page': 1, 'last_page': 1, 'per_page': 15, 'total': 0},
        'summary': {
          'desayuno': {'label': 'Desayuno', 'dispatched': 3, 'received': 2, 'planned': 4},
          'almuerzo': {'label': 'Almuerzo', 'dispatched': 1, 'received': 0, 'planned': 4},
          'cena': {'label': 'Cena', 'dispatched': 0, 'received': 0, 'planned': 4},
        },
        'voids': [],
      });
    await h.start(tester, size: minWindowSize);

    expect(find.text('Despacho'), findsWidgets);
    for (final section in ['Recepción', 'Dietas activas', 'Historial', 'Reportes', 'Despachos del día']) {
      await tester.tap(find.descendant(of: find.byType(NavigationRail), matching: find.text(section)));
      await tester.pumpAndSettle();
      expect(find.text(section), findsWidgets);
    }

    // Línea de tiempo con versiones
    await tester.tap(find.descendant(of: find.byType(NavigationRail), matching: find.text('Historial')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Paciente 101').first);
    await tester.pumpAndSettle();
    expect(find.text('Versión 2'), findsOneWidget);

    await tester.tap(find.descendant(of: find.byType(NavigationRail), matching: find.text('Dietas activas')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Registrar dieta'));
    await tester.pumpAndSettle();
    expect(find.text('Tipo de dieta (facturación) *'), findsOneWidget);
  });
}
