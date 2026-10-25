import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../auth/session.dart';
import '../common/widgets.dart';
import '../core/api_exception.dart';
import '../core/catalogs.dart';
import '../core/theme.dart';
import 'receipts_controller.dart';

/// Recepción del nutricionista: confirmar lo que llegó de cocina (solo lo recibido se factura)
/// o reportar «No llegó».
class ReceiptsScreen extends ConsumerStatefulWidget {
  const ReceiptsScreen({super.key});

  @override
  ConsumerState<ReceiptsScreen> createState() => _ReceiptsScreenState();
}

class _ReceiptsScreenState extends ConsumerState<ReceiptsScreen> {
  bool _busy = false;

  ReceiptsController get _controller => ref.read(receiptsProvider.notifier);

  Future<void> _run(Future<String> Function() action) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      final message = await action();
      if (mounted) showNotice(context, message);
    } on ApiException catch (e) {
      if (e.isUnauthorized) {
        await ref.read(sessionProvider.notifier).expired();
      } else if (mounted) {
        showNotice(context, e.message, error: true);
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _confirm(int count) async {
    final ok = await confirmDialog(
      context,
      title: 'Confirmar recepción',
      message: '¿Confirmas que se recibieron $count ${count == 1 ? 'despacho' : 'despachos'}? Se contarán en la facturación.',
      confirm: 'Confirmar recepción',
    );
    if (ok) await _run(_controller.confirmSelected);
  }

  Future<void> _notArrived(PendingReceipt r) async {
    final reason = await voidDialog(
      context,
      title: 'No llegó',
      message: 'Reporta que ${r.mealLabel.toLowerCase()} de la hab. ${r.habitation} no llegó. No se factura y cocina lo verá para despacharlo de nuevo.',
      confirm: 'Reportar que no llegó',
    );
    if (reason != null) await _run(() => _controller.notArrived(r, reason));
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(receiptsProvider);
    final items = state.items;
    final count = state.selected.length;

    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 16, 20, 12),
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Wrap(spacing: 16, runSpacing: 10, crossAxisAlignment: WrapCrossAlignment.center, children: [
          Text('Recepción', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700)),
          if (items != null)
            Text(
              items.isEmpty ? 'Nada por confirmar' : (items.length == 1 ? '1 despacho por confirmar' : '${items.length} despachos por confirmar'),
              style: const TextStyle(fontWeight: FontWeight.w600, color: NtColors.primarioSuaveTexto),
            ),
          const Tooltip(
            message: 'Solo los despachos con recepción confirmada se cuentan en la facturación.',
            child: Text('Solo lo recibido se factura', style: TextStyle(color: NtColors.textoSuave, fontSize: 13)),
          ),
          Row(mainAxisSize: MainAxisSize.min, children: [
            const LiveStatus(fallbackSeconds: ReceiptsController.fallbackSeconds),
            const AlertsToggle(),
            IconButton(tooltip: 'Actualizar', onPressed: _controller.refresh, icon: const Icon(Icons.refresh)),
          ]),
        ]),
        const SizedBox(height: 12),
        if (state.error != null) ...[LoadError(message: state.error!, onRetry: _controller.refresh), const SizedBox(height: 12)],
        Expanded(
          child: items == null
              ? (state.error != null ? const SizedBox.shrink() : const Center(child: CircularProgressIndicator()))
              : items.isEmpty
                  ? const Center(
                      child: Column(mainAxisSize: MainAxisSize.min, children: [
                        Icon(Icons.inbox_outlined, size: 40, color: NtColors.textoSuave),
                        SizedBox(height: 8),
                        Text('No hay despachos por confirmar.', style: TextStyle(color: NtColors.textoSuave)),
                        Text('Cuando cocina despache, aparecerán aquí con una alerta.', style: TextStyle(color: NtColors.textoSuave, fontSize: 13)),
                      ]),
                    )
                  : Container(
                      decoration: BoxDecoration(color: NtColors.superficie, borderRadius: BorderRadius.circular(10), border: Border.all(color: NtColors.borde)),
                      child: SingleChildScrollView(
                        child: SingleChildScrollView(
                          scrollDirection: Axis.horizontal,
                          child: DataTable(
                            onSelectAll: (v) => _controller.toggleAll(v ?? false),
                            headingRowHeight: 40,
                            columnSpacing: 24,
                            headingTextStyle: const TextStyle(fontWeight: FontWeight.w600, color: NtColors.textoSuave, fontSize: 12.5),
                            columns: const [
                              DataColumn(label: Text('Hab.')),
                              DataColumn(label: Text('Paciente')),
                              DataColumn(label: Text('Servicio')),
                              DataColumn(label: Text('Despachado')),
                              DataColumn(label: Text('Despachó')),
                              DataColumn(label: Text('')),
                            ],
                            rows: [
                              for (final r in items)
                                DataRow(
                                  key: ValueKey('receipt-${r.id}'),
                                  selected: state.selected.contains(r.id),
                                  onSelectChanged: (_) => _controller.toggle(r.id),
                                  cells: [
                                    DataCell(RoomBadge(r.habitation)),
                                    DataCell(ConstrainedBox(constraints: const BoxConstraints(maxWidth: 240), child: Text(r.patient, overflow: TextOverflow.ellipsis))),
                                    DataCell(NtTag(r.mealLabel, kind: TagKind.meal)),
                                    DataCell(Text(formatWhen(r.dispatchedAt), style: const TextStyle(fontSize: 13))),
                                    DataCell(Text(r.dispatchedBy ?? '—', style: const TextStyle(fontSize: 13))),
                                    DataCell(OutlinedButton(
                                      style: OutlinedButton.styleFrom(minimumSize: const Size(0, 32), padding: const EdgeInsets.symmetric(horizontal: 12)),
                                      onPressed: _busy ? null : () => _notArrived(r),
                                      child: Text('No llegó', semanticsLabel: 'No llegó: habitación ${r.habitation}, ${r.mealLabel}'),
                                    )),
                                  ],
                                ),
                            ],
                          ),
                        ),
                      ),
                    ),
        ),
        if (items != null && items.isNotEmpty) ...[
          const SizedBox(height: 10),
          Align(
            alignment: Alignment.centerRight,
            child: FilledButton.icon(
              onPressed: count == 0 || _busy ? null : () => _confirm(count),
              icon: const Icon(Icons.check),
              label: Text('Confirmar recepción ($count)'),
            ),
          ),
        ],
      ]),
    );
  }
}
