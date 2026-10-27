import 'package:clock/clock.dart';
import 'package:file_selector/file_selector.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../auth/session.dart';
import '../core/api_client.dart';
import '../core/api_exception.dart';

/// Tiempo de comida del reporte: bandejas programadas y recibidas en el mes
class MealTotal {
  MealTotal(this.key, this.label, this.scheduled, this.received);

  final String key;
  final String label;
  final int scheduled;
  final int received;
}

/// Reporte nutricional del mes (GET /api/v1/reports/monthly)
class MonthlyReport {
  MonthlyReport({
    required this.month,
    required this.label,
    required this.daysCounted,
    required this.scheduledTrays,
    required this.receivedTrays,
    required this.avgDailyDiets,
    required this.daysWithDiets,
    required this.patients,
    required this.activeNow,
    required this.isolatedNow,
    required this.meals,
  });

  final String month;
  final String label;
  final int daysCounted;
  final int scheduledTrays;
  final int receivedTrays;
  final double avgDailyDiets;
  final int daysWithDiets;
  final int patients;
  final int activeNow;
  final int isolatedNow;
  final List<MealTotal> meals;

  factory MonthlyReport.fromJson(Map<String, dynamic> json) {
    final k = json['kpis'] as Map? ?? const {};
    int n(String key) => (k[key] as num?)?.toInt() ?? 0;
    return MonthlyReport(
      month: '${json['month']}',
      label: '${json['label'] ?? json['month']}',
      daysCounted: (json['days_counted'] as num?)?.toInt() ?? 0,
      scheduledTrays: n('scheduled_trays'),
      receivedTrays: n('received_trays'),
      avgDailyDiets: (k['avg_daily_diets'] as num?)?.toDouble() ?? 0,
      daysWithDiets: n('days_with_diets'),
      patients: n('patients'),
      activeNow: n('active_now'),
      isolatedNow: n('isolated_now'),
      meals: [
        for (final m in (json['meals'] as List? ?? const []).whereType<Map>())
          MealTotal('${m['key']}', '${m['label']}', (m['scheduled'] as num?)?.toInt() ?? 0, (m['received'] as num?)?.toInt() ?? 0),
      ],
    );
  }
}

class ReportsState {
  const ReportsState({required this.month, this.report, this.loading = true, this.error});

  /// Primer día del mes elegido
  final DateTime month;
  final MonthlyReport? report;
  final bool loading;
  final String? error;

  String get monthParam => '${month.year}-${month.month.toString().padLeft(2, '0')}';

  bool get isCurrentMonth {
    final now = clock.now();
    return month.year == now.year && month.month == now.month;
  }
}

/// Guarda un archivo donde el usuario elija. Devuelve la ruta o null si canceló.
typedef FileSaver = Future<String?> Function(String suggestedName, Uint8List bytes);

/// Diálogo «Guardar» del sistema (file_selector); en macOS requiere el permiso
/// com.apple.security.files.user-selected.read-write
final fileSaverProvider = Provider<FileSaver>((ref) => (name, bytes) async {
      final location = await getSaveLocation(
        suggestedName: name,
        acceptedTypeGroups: const [XTypeGroup(label: 'Excel', extensions: ['xlsx'])],
      );
      if (location == null) return null;
      await XFile.fromData(bytes, name: name).saveTo(location.path);
      return location.path;
    });

/// «Reporte nutricional» (solo nutrición): indicadores y totales por tiempo del mes, y el
/// Excel del mes (el mismo de la web, que cuenta lo despachado y recibido).
final reportsProvider = NotifierProvider.autoDispose<ReportsController, ReportsState>(ReportsController.new);

class ReportsController extends Notifier<ReportsState> {
  int _request = 0;

  ApiClient get _api => ref.read(sessionProvider.notifier).client;

  @override
  ReportsState build() {
    final now = clock.now();
    Future.microtask(refresh);
    return ReportsState(month: DateTime(now.year, now.month));
  }

  Future<void> refresh() async {
    final request = ++_request;
    final month = state.monthParam;
    try {
      final data = await _api.get('/reports/monthly', query: {'mes': month});
      if (!ref.mounted || request != _request) return;
      state = ReportsState(month: state.month, report: MonthlyReport.fromJson(data), loading: false);
    } on ApiException catch (e) {
      if (!ref.mounted || request != _request) return;
      if (e.isUnauthorized) {
        await ref.read(sessionProvider.notifier).expired();
        return;
      }
      state = ReportsState(month: state.month, report: state.report, loading: false, error: e.message);
    }
  }

  /// Mes anterior (-1) o siguiente (+1); no pasa del mes actual
  void moveMonth(int delta) {
    final next = DateTime(state.month.year, state.month.month + delta);
    final now = clock.now();
    if (next.isAfter(DateTime(now.year, now.month))) return;
    state = ReportsState(month: next, report: state.report, loading: true);
    refresh();
  }

  /// Descarga el Excel del mes y lo guarda donde elija el usuario. Devuelve la ruta o null.
  Future<String?> downloadExcel() async {
    final file = await _api.download('/reports/monthly/excel', query: {'mes': state.monthParam});
    final name = file.filename ?? 'reporte-nutricional_${state.monthParam}.xlsx';
    return ref.read(fileSaverProvider)(name, Uint8List.fromList(file.bytes));
  }
}
