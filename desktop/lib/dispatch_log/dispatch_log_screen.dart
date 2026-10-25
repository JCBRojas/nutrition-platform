import 'dart:async';

import 'package:clock/clock.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../auth/session.dart';
import '../common/widgets.dart';
import '../core/catalogs.dart';
import '../core/theme.dart';
import 'dispatch_log_controller.dart';

const _months = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

/// «Despachos del día» (Premium): lo despachado y recibido por fecha, servicio y estado, el
/// resumen por tiempo y los anulados o que no llegaron. Solo consulta.
class DispatchLogScreen extends ConsumerStatefulWidget {
  const DispatchLogScreen({super.key});

  @override
  ConsumerState<DispatchLogScreen> createState() => _DispatchLogScreenState();
}

class _DispatchLogScreenState extends ConsumerState<DispatchLogScreen> {
  final _search = TextEditingController();
  Timer? _debounce;

  DispatchLogController get _controller => ref.read(dispatchLogProvider.notifier);

  @override
  void dispose() {
    _debounce?.cancel();
    _search.dispose();
    super.dispose();
  }

  Future<void> _pickDate(DateTime current) async {
    final now = clock.now();
    final picked = await showDatePicker(context: context, initialDate: current, firstDate: DateTime(now.year - 2), lastDate: now, helpText: 'Fecha de los despachos');
    if (picked != null) _controller.setDate(picked);
  }

  static String _dayLabel(DateTime date) {
    final now = clock.now();
    final today = date.year == now.year && date.month == now.month && date.day == now.day;
    return '${today ? 'Hoy, ' : ''}${date.day} de ${_months[date.month - 1]} de ${date.year}';
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(dispatchLogProvider);
    final catalogs = Catalogs.fromMeta(ref.read(sessionProvider.notifier).meta);
    final log = state.log;
    final meals = catalogs.meals.where((m) => m.time != null || log?.summary.any((s) => s.meal == m.key) == true).toList();

    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 16, 20, 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Wrap(
            spacing: 16,
            runSpacing: 10,
            crossAxisAlignment: WrapCrossAlignment.center,
            children: [
              Text('Despachos del día', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700)),
              OutlinedButton.icon(onPressed: () => _pickDate(state.date), icon: const Icon(Icons.calendar_today_outlined, size: 18), label: Text(_dayLabel(state.date))),
              DropdownButton<String>(
                value: state.meal,
                underline: const SizedBox.shrink(),
                items: [
                  const DropdownMenuItem(value: '', child: Text('Todos los servicios')),
                  for (final m in meals) DropdownMenuItem(value: m.key, child: Text(m.label)),
                ],
                onChanged: (v) => _controller.setMeal(v ?? ''),
              ),
              SegmentedButton<String>(
                showSelectedIcon: false,
                segments: const [
                  ButtonSegment(value: '', label: Text('Todos')),
                  ButtonSegment(value: 'pending', label: Text('Por recibir')),
                  ButtonSegment(value: 'received', label: Text('Recibidos')),
                ],
                selected: {state.status},
                onSelectionChanged: (s) => _controller.setStatus(s.first),
              ),
              SizedBox(
                width: 240,
                child: TextField(
                  controller: _search,
                  onChanged: (value) {
                    _debounce?.cancel();
                    _debounce = Timer(const Duration(milliseconds: 300), () => _controller.setQuery(value));
                  },
                  decoration: const InputDecoration(isDense: true, prefixIcon: Icon(Icons.search), hintText: 'Buscar habitación o paciente'),
                ),
              ),
              Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const LiveStatus(fallbackSeconds: DispatchLogController.fallbackSeconds),
                  IconButton(tooltip: 'Actualizar', onPressed: _controller.refresh, icon: const Icon(Icons.refresh)),
                ],
              ),
            ],
          ),
          const SizedBox(height: 12),
          if (state.error != null) ...[LoadError(message: state.error!, onRetry: _controller.refresh), const SizedBox(height: 12)],
          if (log == null)
            Expanded(child: state.error != null ? const SizedBox.shrink() : const Center(child: CircularProgressIndicator()))
          else ...[
            _Summary(summary: log.summary),
            const SizedBox(height: 12),
            Expanded(
              child: ListView(
                children: [
                  _Entries(log: log, catalogs: catalogs, filtered: state.status.isNotEmpty || state.meal.isNotEmpty || state.query.isNotEmpty),
                  if (log.voids.isNotEmpty) ...[const SizedBox(height: 16), _Voids(voids: log.voids, catalogs: catalogs)],
                ],
              ),
            ),
            const SizedBox(height: 8),
            PaginationBar(page: log.page, lastPage: log.lastPage, perPage: log.perPage, total: log.total, onPage: _controller.setPage, onPerPage: _controller.setPerPage),
          ],
        ],
      ),
    );
  }
}

/// Resumen por tiempo: recibidos / despachados (y programados, solo hoy)
class _Summary extends StatelessWidget {
  const _Summary({required this.summary});

  final List<MealSummary> summary;

  @override
  Widget build(BuildContext context) => Wrap(
    spacing: 10,
    runSpacing: 10,
    children: [
      for (final s in summary.where((s) => s.dispatched > 0 || (s.planned ?? 0) > 0))
        Container(
          width: 170,
          padding: const EdgeInsets.all(10),
          decoration: BoxDecoration(
            color: NtColors.superficie,
            borderRadius: BorderRadius.circular(10),
            border: Border.all(color: NtColors.borde),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              NtTag(s.label, kind: TagKind.meal),
              const SizedBox(height: 6),
              Text('${s.received} de ${s.dispatched} recibidos', style: const TextStyle(fontWeight: FontWeight.w700)),
              Text(s.planned == null ? '${s.dispatched} despachados' : '${s.dispatched} de ${s.planned} despachados', style: const TextStyle(color: NtColors.textoSuave, fontSize: 12.5)),
            ],
          ),
        ),
      if (summary.every((s) => s.dispatched == 0 && (s.planned ?? 0) == 0)) const Text('Sin despachos ni dietas programadas para este día.', style: TextStyle(color: NtColors.textoSuave)),
    ],
  );
}

class _Entries extends StatelessWidget {
  const _Entries({required this.log, required this.catalogs, required this.filtered});

  final DispatchLog log;
  final Catalogs catalogs;
  final bool filtered;

  @override
  Widget build(BuildContext context) {
    if (log.entries.isEmpty) {
      return Padding(
        padding: const EdgeInsets.symmetric(vertical: 24),
        child: Center(
          child: Text(filtered ? 'No hay despachos que coincidan con los filtros.' : 'No hay despachos en este día.', style: const TextStyle(color: NtColors.textoSuave)),
        ),
      );
    }

    const small = TextStyle(fontSize: 13);
    return Container(
      decoration: BoxDecoration(
        color: NtColors.superficie,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: NtColors.borde),
      ),
      child: SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        child: DataTable(
          headingRowHeight: 40,
          columnSpacing: 24,
          headingTextStyle: const TextStyle(fontWeight: FontWeight.w600, color: NtColors.textoSuave, fontSize: 12.5),
          columns: const [
            DataColumn(label: Text('Hab.')),
            DataColumn(label: Text('Paciente')),
            DataColumn(label: Text('Servicio')),
            DataColumn(label: Text('Despachado')),
            DataColumn(label: Text('Despachó')),
            DataColumn(label: Text('Recepción')),
          ],
          rows: [
            for (final e in log.entries)
              DataRow(
                key: ValueKey('log-${e.id}'),
                cells: [
                  DataCell(RoomBadge(e.habitation)),
                  DataCell(
                    ConstrainedBox(
                      constraints: const BoxConstraints(maxWidth: 220),
                      child: Text(e.patient, overflow: TextOverflow.ellipsis),
                    ),
                  ),
                  DataCell(NtTag(catalogs.mealLabel(e.meal), kind: TagKind.meal, delivery: e.receivedAt != null ? Delivery.received : Delivery.dispatched)),
                  DataCell(Text(formatTime(e.dispatchedAt), style: small)),
                  DataCell(Text(e.dispatchedBy ?? '—', style: small)),
                  DataCell(
                    e.receivedAt != null
                        ? Text(
                            'Recibido ${formatTime(e.receivedAt)}${e.receivedBy != null ? ' · ${e.receivedBy}' : ''}',
                            style: small.copyWith(color: NtColors.exito, fontWeight: FontWeight.w600),
                          )
                        : Text(
                            'Por recibir',
                            style: small.copyWith(color: NtColors.advertencia, fontWeight: FontWeight.w600),
                          ),
                  ),
                ],
              ),
          ],
        ),
      ),
    );
  }
}

/// Anulados y que no llegaron: no se facturan y se pudieron despachar de nuevo
class _Voids extends StatelessWidget {
  const _Voids({required this.voids, required this.catalogs});

  final List<VoidEntry> voids;
  final Catalogs catalogs;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text('Anulados y que no llegaron (${voids.length})', style: const TextStyle(fontWeight: FontWeight.w700)),
      const Text('No se facturan; ese tiempo se pudo despachar de nuevo.', style: TextStyle(color: NtColors.textoSuave, fontSize: 12.5)),
      const SizedBox(height: 8),
      for (final v in voids)
        Padding(
          padding: const EdgeInsets.only(bottom: 6),
          child: Wrap(
            spacing: 8,
            crossAxisAlignment: WrapCrossAlignment.center,
            children: [
              RoomBadge(v.habitation),
              NtTag(catalogs.mealLabel(v.meal), kind: TagKind.meal),
              Text(v.label, style: const TextStyle(fontWeight: FontWeight.w600)),
              Text('${formatTime(v.voidedAt)}${v.voidedBy != null ? ' · ${v.voidedBy}' : ''}', style: const TextStyle(color: NtColors.textoSuave, fontSize: 13)),
              if (v.reason != null && v.reason!.isNotEmpty) Text('«${v.reason}»', style: const TextStyle(fontStyle: FontStyle.italic, fontSize: 13)),
            ],
          ),
        ),
    ],
  );
}
