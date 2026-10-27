import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';

import '../auth/session.dart';
import '../common/widgets.dart';
import '../core/api_exception.dart';
import '../core/catalogs.dart';
import '../core/theme.dart';
import 'reports_controller.dart';

/// «Reporte nutricional»: indicadores del mes, bandejas por tiempo de comida (programadas y
/// recibidas) y descarga del Excel del mes.
class ReportsScreen extends ConsumerStatefulWidget {
  const ReportsScreen({super.key});

  @override
  ConsumerState<ReportsScreen> createState() => _ReportsScreenState();
}

class _ReportsScreenState extends ConsumerState<ReportsScreen> {
  bool _downloading = false;

  ReportsController get _controller => ref.read(reportsProvider.notifier);

  Future<void> _download() async {
    if (_downloading) return;
    setState(() => _downloading = true);
    try {
      final path = await _controller.downloadExcel();
      if (path != null && mounted) {
        showNotice(context, 'Excel guardado en $path',
            action: SnackBarAction(label: 'Abrir', onPressed: () => launchUrl(Uri.file(path))));
      }
    } on ApiException catch (e) {
      if (e.isUnauthorized) {
        await ref.read(sessionProvider.notifier).expired();
      } else if (mounted) {
        showNotice(context, e.message, error: true);
      }
    } finally {
      if (mounted) setState(() => _downloading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(reportsProvider);
    final report = state.report;
    final shownMonth = report?.label ?? state.monthParam;

    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 16, 20, 12),
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Wrap(spacing: 16, runSpacing: 10, crossAxisAlignment: WrapCrossAlignment.center, children: [
          Text('Reporte nutricional', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700)),
          Row(mainAxisSize: MainAxisSize.min, children: [
            IconButton(tooltip: 'Mes anterior', onPressed: () => _controller.moveMonth(-1), icon: const Icon(Icons.chevron_left)),
            SizedBox(
              width: 150,
              child: Text(state.loading ? '…' : shownMonth, textAlign: TextAlign.center, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600)),
            ),
            IconButton(tooltip: 'Mes siguiente', onPressed: state.isCurrentMonth ? null : () => _controller.moveMonth(1), icon: const Icon(Icons.chevron_right)),
          ]),
          IconButton(tooltip: 'Actualizar', onPressed: _controller.refresh, icon: const Icon(Icons.refresh)),
          FilledButton.icon(
            style: FilledButton.styleFrom(backgroundColor: NtColors.btnSuccess),
            onPressed: _downloading || state.loading ? null : _download,
            icon: _downloading ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) : const Icon(Icons.download),
            label: const Text('Descargar Excel'),
          ),
        ]),
        const Padding(
          padding: EdgeInsets.only(top: 4),
          child: Text('El Excel cuenta cada tiempo despachado y con recepción confirmada, igual que la facturación.',
              style: TextStyle(color: NtColors.textoSuave, fontSize: 13)),
        ),
        const SizedBox(height: 12),
        if (state.error != null) ...[LoadError(message: state.error!, onRetry: _controller.refresh), const SizedBox(height: 12)],
        Expanded(
          child: report == null
              ? (state.error != null ? const SizedBox.shrink() : const Center(child: CircularProgressIndicator()))
              : ListView(children: [
                  Wrap(spacing: 12, runSpacing: 12, children: [
                    StatCard(label: 'Bandejas programadas', value: formatNumber(report.scheduledTrays), sub: 'Tiempos de las dietas vigentes', accent: NtColors.btnPrimary),
                    StatCard(label: 'Bandejas recibidas', value: formatNumber(report.receivedTrays), sub: 'Las que cuentan en el Excel', accent: NtColors.exito),
                    StatCard(label: 'Promedio diario', value: formatNumber(report.avgDailyDiets, decimals: 1), sub: 'Dietas por día (${report.daysWithDiets} días)', accent: NtColors.dietaTiempo),
                    StatCard(label: 'Pacientes en el mes', value: formatNumber(report.patients), sub: 'Con al menos un día de dieta', accent: NtColors.dietaEspecificacion),
                    StatCard(label: 'Dietas activas hoy', value: formatNumber(report.activeNow), sub: '${report.isolatedNow} en aislamiento', accent: NtColors.advertencia),
                  ]),
                  const SizedBox(height: 16),
                  _MealTable(report: report),
                ]),
        ),
      ]),
    );
  }
}

/// Bandejas por tiempo de comida: programadas, porcentaje y recibidas
class _MealTable extends StatelessWidget {
  const _MealTable({required this.report});

  final MonthlyReport report;

  @override
  Widget build(BuildContext context) {
    final total = report.scheduledTrays;
    const bold = TextStyle(fontWeight: FontWeight.w700);
    String percent(int n) => total == 0 ? '0 %' : '${(n * 100 / total).round()} %';

    return Align(
      alignment: Alignment.topLeft,
      child: Container(
        decoration: BoxDecoration(color: NtColors.superficie, borderRadius: BorderRadius.circular(10), border: Border.all(color: NtColors.borde)),
        child: DataTable(
          headingRowHeight: 40,
          columnSpacing: 36,
          headingTextStyle: const TextStyle(fontWeight: FontWeight.w600, color: NtColors.textoSuave, fontSize: 12.5),
          columns: const [
            DataColumn(label: Text('Tiempo de comida')),
            DataColumn(label: Text('Programadas'), numeric: true),
            DataColumn(label: Text('% del total'), numeric: true),
            DataColumn(label: Text('Recibidas'), numeric: true),
          ],
          rows: [
            for (final m in report.meals)
              DataRow(key: ValueKey('meal-${m.key}'), cells: [
                DataCell(NtTag(m.label, kind: TagKind.meal)),
                DataCell(Text(formatNumber(m.scheduled))),
                DataCell(Text(percent(m.scheduled), style: const TextStyle(color: NtColors.textoSuave))),
                DataCell(Text(formatNumber(m.received))),
              ]),
            DataRow(cells: [
              const DataCell(Text('Total', style: bold)),
              DataCell(Text(formatNumber(total), style: bold)),
              DataCell(Text(total == 0 ? '0 %' : '100 %', style: bold)),
              DataCell(Text(formatNumber(report.receivedTrays), style: bold)),
            ]),
          ],
        ),
      ),
    );
  }
}
