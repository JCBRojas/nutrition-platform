import 'package:clock/clock.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nutritrack_desktop/common/widgets.dart';

import 'diets_test.dart' show activeDiet, dietsJson, metaWith, nutritionist;
import 'harness.dart';

const nutritionPermissions = [...nutritionist, 'viewReports'];

Map<String, dynamic> historyList() => {
      'data': [
        {'id': 1, 'habitation': '101', 'name_patient': 'Paciente 101', 'status': 1, 'is_active': true, 'versions': 2, 'updated_at': '2026-10-24T10:00:00-05:00'},
        {'id': 5, 'habitation': '305', 'name_patient': 'Paciente 305', 'status': 0, 'is_active': false, 'versions': 3, 'updated_at': '2026-10-20T09:00:00-05:00'},
      ],
      'meta': {'current_page': 1, 'last_page': 1, 'per_page': 15, 'total': 2},
    };

Map<String, dynamic> timeline() => {
      'diet': {'id': 1, 'habitation': '101', 'name_patient': 'Paciente 101', 'status': 1, 'is_active': true},
      'versions': [
        {
          'version': 2,
          'type': 'edicion',
          'created_at': '2026-10-24T10:00:00-05:00',
          'updated_by': 'Luisa Gomez',
          'fields': {
            'timeFood': {'items': [{'label': 'Almuerzo', 'state': 'kept'}, {'label': 'Cena', 'state': 'added'}, {'label': 'Desayuno', 'state': 'removed'}], 'changed': true},
            'consistency': {'items': [{'label': 'Normal', 'state': 'kept'}], 'changed': false},
            'specifications': {'items': [{'label': 'Hiposódica', 'state': 'kept'}], 'changed': false},
            'billing_type': {'value': 'Normal', 'old': 'Normal', 'changed': false},
            'observations': {'value': 'Sin sal', 'old': null, 'changed': true},
            'isolation': {'value': 'Sí', 'old': 'No', 'changed': true},
          },
        },
        {
          'version': 1,
          'type': 'creacion',
          'created_at': '2026-10-23T08:00:00-05:00',
          'updated_by': 'Luisa Gomez',
          'fields': {
            'timeFood': {'items': [{'label': 'Desayuno', 'state': 'kept'}, {'label': 'Almuerzo', 'state': 'kept'}], 'changed': false},
            'consistency': {'items': [{'label': 'Normal', 'state': 'kept'}], 'changed': false},
            'specifications': {'items': [{'label': 'Hiposódica', 'state': 'kept'}], 'changed': false},
            'billing_type': {'value': 'Normal', 'old': null, 'changed': false},
            'observations': {'value': null, 'old': null, 'changed': false},
            'isolation': {'value': 'No', 'old': null, 'changed': false},
          },
        },
      ],
    };

Map<String, dynamic> report({String month = '2026-10', String label = 'Octubre 2026'}) => {
      'month': month,
      'label': label,
      'days_counted': 24,
      'kpis': {'scheduled_trays': 1234, 'received_trays': 980, 'avg_daily_diets': 12.5, 'days_with_diets': 24, 'patients': 31, 'active_now': 5, 'isolated_now': 1},
      'meals': [
        {'key': 'desayuno', 'label': 'Desayuno', 'scheduled': 600, 'received': 500},
        {'key': 'almuerzo', 'label': 'Almuerzo', 'scheduled': 634, 'received': 480},
      ],
    };

Harness nutritionHarness({bool nutritionTools = true, List<String> permissions = nutritionPermissions}) {
  final h = Harness(permissions: permissions, home: 'diets', nutritionTools: nutritionTools);
  h.server
    ..on('GET', '/meta', metaWith())
    ..on('GET', '/diets', dietsJson(diets: [activeDiet(1, '101')]))
    ..on('GET', '/history/diets', historyList())
    ..on('GET', '/diets/1/history', timeline())
    ..on('GET', '/reports/monthly', report());
  return h;
}

Future<void> openSection(WidgetTester tester, String label) async {
  await tester.tap(find.descendant(of: find.byType(NavigationRail), matching: find.text(label)));
  await tester.pumpAndSettle();
}

void main() {
  testWidgets('sin rol de nutrición no aparecen Historial ni Reporte, aunque tenga los permisos', (tester) async {
    // P. ej. el jefe de despacho tiene viewReports y el despachador viewDiets
    final other = nutritionHarness(nutritionTools: false);
    await other.start(tester);
    expect(find.text('Historial'), findsNothing);
    expect(find.text('Reportes'), findsNothing);
  });

  testWidgets('nutrición ve Historial de cambios y Reporte nutricional en el menú', (tester) async {
    final h = nutritionHarness();
    await h.start(tester);
    expect(find.descendant(of: find.byType(NavigationRail), matching: find.text('Historial')), findsOneWidget);
    expect(find.descendant(of: find.byType(NavigationRail), matching: find.text('Reportes')), findsOneWidget);
  });

  testWidgets('el historial muestra las versiones con lo agregado, lo quitado y los valores anteriores', (tester) async {
    final h = nutritionHarness();
    await h.start(tester);
    await openSection(tester, 'Historial');

    expect(find.text('Elige una dieta para ver sus versiones.'), findsOneWidget);
    expect(find.text('3 versiones · Cancelada'), findsOneWidget);
    await tester.enterText(find.widgetWithText(TextField, 'Buscar habitación o paciente'), '101');
    await tester.pump(const Duration(milliseconds: 400));
    expect(h.server.requests.last.queryParameters['q'], '101');

    await tester.tap(find.text('Paciente 101').first);
    await tester.pumpAndSettle();
    expect(h.count('GET', '/diets/1/history'), 1);
    expect(find.text('Versión 2'), findsOneWidget);
    expect(find.text('Edición'), findsOneWidget);
    expect(find.text('Creación'), findsOneWidget);
    NtTag tag(String label) => tester.widget<NtTag>(find.widgetWithText(NtTag, label).first);
    expect(tag('Cena').change, TagChange.added);
    expect(tag('Desayuno').change, TagChange.removed);
    // Aislamiento: «No → Sí»
    expect(tester.widget<Text>(find.text('No').first).style?.decoration, TextDecoration.lineThrough);
    expect(find.text('Sin sal'), findsOneWidget);
  });

  testWidgets('«Ver historial» en Dietas activas abre el historial de esa dieta en la app', (tester) async {
    final h = nutritionHarness();
    await h.start(tester);

    final button = find.byTooltip('Ver historial de Paciente 101');
    await tester.ensureVisible(button);
    await tester.pumpAndSettle();
    await tester.tap(button);
    await tester.pumpAndSettle();

    expect(find.text('Versión 2'), findsOneWidget);
    expect(h.count('GET', '/diets/1/history'), 1);
  });

  testWidgets('el reporte muestra los indicadores del mes, cambia de mes y descarga el Excel', (tester) async {
    await withClock(Clock.fixed(DateTime(2026, 10, 24, 12)), () async {
      final h = nutritionHarness();
      h.server.onFile('GET', '/reports/monthly/excel', [1, 2, 3], 'reporte-nutricional_serviucis_2026-10_20261024_1200.xlsx');
      await h.start(tester);
      await openSection(tester, 'Reportes');

      expect(find.text('Octubre 2026'), findsOneWidget);
      expect(find.text('1.234'), findsWidgets);
      expect(find.text('980'), findsWidgets);
      expect(find.text('12,5'), findsOneWidget);
      expect(find.text('49 %'), findsOneWidget); // 600 de 1.234
      expect(h.server.requests.last.queryParameters['mes'], '2026-10');
      // El mes actual no tiene siguiente
      expect(tester.widget<IconButton>(find.widgetWithIcon(IconButton, Icons.chevron_right)).onPressed, isNull);

      await tester.tap(find.text('Descargar Excel'));
      await tester.pumpAndSettle();
      expect(h.saved['reporte-nutricional_serviucis_2026-10_20261024_1200.xlsx'], [1, 2, 3]);
      expect(find.textContaining('Excel guardado en /Descargas/'), findsOneWidget);
      expect(find.widgetWithText(SnackBarAction, 'Abrir'), findsOneWidget);

      h.server.on('GET', '/reports/monthly', report(month: '2026-09', label: 'Septiembre 2026'));
      await tester.tap(find.widgetWithIcon(IconButton, Icons.chevron_left));
      await tester.pumpAndSettle();
      expect(h.server.requests.last.queryParameters['mes'], '2026-09');
      expect(find.text('Septiembre 2026'), findsOneWidget);
    });
  });
}
