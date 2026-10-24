import 'dart:async';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../auth/session.dart';
import '../common/widgets.dart';
import '../core/api_exception.dart';
import '../core/catalogs.dart';
import '../core/theme.dart';
import 'dispatch_controller.dart';
import 'models.dart';

/// Panel del despachador (cocina), como la pantalla web de despacho: servicio, cambios sin
/// confirmar arriba, selección y «Despachar», «Anular despacho» y aviso de «No llegó».
class DispatchScreen extends ConsumerStatefulWidget {
  const DispatchScreen({super.key});

  @override
  ConsumerState<DispatchScreen> createState() => _DispatchScreenState();
}

class _DispatchScreenState extends ConsumerState<DispatchScreen> {
  final _search = TextEditingController();
  final _searchFocus = FocusNode();
  Timer? _debounce;
  bool _busy = false;

  DispatchBoardController get _controller => ref.read(dispatchBoardProvider.notifier);

  @override
  void dispose() {
    _debounce?.cancel();
    _search.dispose();
    _searchFocus.dispose();
    super.dispose();
  }

  Future<void> _run(Future<String> Function() action) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      final message = await action();
      if (mounted && message.isNotEmpty) showNotice(context, message);
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

  Future<void> _dispatch(DispatchState state, Catalogs catalogs) async {
    final count = state.selected.length;
    if (count == 0 || state.meal == null) return;
    final ok = await confirmDialog(
      context,
      title: 'Despachar',
      message: '¿Despachar $count ${count == 1 ? 'dieta' : 'dietas'} de ${catalogs.mealLabel(state.meal!).toLowerCase()}? '
          'Se notificará al nutricionista para que confirme la recepción.',
      confirm: 'Despachar',
    );
    if (ok) await _run(_controller.dispatchSelected);
  }

  Future<void> _cancelDispatch(BoardDiet diet, Catalogs catalogs, String meal) async {
    final reason = await voidDialog(
      context,
      title: 'Anular despacho',
      message: 'Anula el despacho de ${catalogs.mealLabel(meal).toLowerCase()} de la hab. ${diet.habitation} si se registró por error. '
          'No se factura y se puede despachar de nuevo.',
      confirm: 'Anular despacho',
    );
    if (reason != null) await _run(() => _controller.cancelDispatch(diet.dispatch!.id, reason, diet.habitation));
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(dispatchBoardProvider);
    final catalogs = Catalogs.fromMeta(ref.read(sessionProvider.notifier).meta);
    final board = state.board;
    final canDispatch = state.tab == 'active' && state.selected.isNotEmpty && !_busy;
    final mac = Platform.isMacOS;

    return CallbackShortcuts(
      bindings: {
        SingleActivator(LogicalKeyboardKey.keyF, meta: mac, control: !mac): () => _searchFocus.requestFocus(),
        SingleActivator(LogicalKeyboardKey.keyR, meta: mac, control: !mac): _controller.refresh,
        SingleActivator(LogicalKeyboardKey.enter, meta: mac, control: !mac): () {
          if (canDispatch) _dispatch(state, catalogs);
        },
      },
      child: Focus(
        autofocus: true,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 16, 20, 12),
          child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            _Toolbar(state: state, catalogs: catalogs, search: _search, searchFocus: _searchFocus, onSearch: (value) {
              _debounce?.cancel();
              _debounce = Timer(const Duration(milliseconds: 350), () => _controller.setQuery(value));
            }),
            const SizedBox(height: 12),
            if (state.error != null) ...[LoadError(message: state.error!, onRetry: _controller.refresh), const SizedBox(height: 12)],
            if (board != null && board.pending.isNotEmpty) ...[
              _PendingBanner(pending: board.pending, busy: _busy, onConfirm: (p) => _run(() => _controller.confirmChanges(p.dietId))),
              const SizedBox(height: 12),
            ],
            if (state.tab == 'active')
              const Padding(
                padding: EdgeInsets.only(bottom: 8),
                child: Text('Marca las dietas listas y pulsa «Despachar». El nutricionista confirma la recepción; solo lo recibido se factura.',
                    style: TextStyle(color: NtColors.textoSuave, fontSize: 13)),
              ),
            Expanded(
              child: board == null
                  ? (state.error != null ? const SizedBox.shrink() : const Center(child: CircularProgressIndicator()))
                  : _DietTable(
                      state: state,
                      board: board,
                      catalogs: catalogs,
                      busy: _busy,
                      onConfirm: (d) => _run(() => _controller.confirmChanges(d.id)),
                      onCancelDispatch: (d) => _cancelDispatch(d, catalogs, board.meal),
                    ),
            ),
            if (board != null) ...[
              const SizedBox(height: 8),
              Wrap(alignment: WrapAlignment.spaceBetween, crossAxisAlignment: WrapCrossAlignment.center, spacing: 16, runSpacing: 8, children: [
                PaginationBar(
                  page: board.page,
                  lastPage: board.lastPage,
                  perPage: board.perPage,
                  total: board.total,
                  onPage: _controller.setPage,
                  onPerPage: _controller.setPerPage,
                ),
                if (state.tab == 'active')
                  FilledButton.icon(
                    onPressed: canDispatch ? () => _dispatch(state, catalogs) : null,
                    icon: const Icon(Icons.local_shipping_outlined),
                    label: Text('Despachar seleccionadas (${state.selected.length})'),
                  ),
              ]),
            ],
          ]),
        ),
      ),
    );
  }
}

class _Toolbar extends ConsumerWidget {
  const _Toolbar({required this.state, required this.catalogs, required this.search, required this.searchFocus, required this.onSearch});

  final DispatchState state;
  final Catalogs catalogs;
  final TextEditingController search;
  final FocusNode searchFocus;
  final ValueChanged<String> onSearch;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final controller = ref.read(dispatchBoardProvider.notifier);
    final counts = state.board?.counts ?? const {};
    final meals = catalogs.meals.where((m) => m.time != null).toList();
    String count(String tab, String pendingKey) {
      final pending = counts[pendingKey] ?? 0;
      return '${counts[tab] ?? 0}${pending > 0 ? ' · $pending con cambios' : ''}';
    }

    return Wrap(spacing: 16, runSpacing: 10, crossAxisAlignment: WrapCrossAlignment.center, children: [
      Text('Despacho', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700)),
      SegmentedButton<String>(
        showSelectedIcon: false,
        segments: [
          ButtonSegment(value: 'active', label: Text('Activas (${count('active', 'pendingActive')})')),
          ButtonSegment(value: 'closed', label: Text('Canceladas y finalizadas (${count('closed', 'pendingClosed')})')),
        ],
        selected: {state.tab},
        onSelectionChanged: (s) => controller.setTab(s.first),
      ),
      if (state.tab == 'active' && meals.isNotEmpty)
        Row(mainAxisSize: MainAxisSize.min, children: [
          const Text('Servicio: '),
          DropdownButton<String>(
            value: meals.any((m) => m.key == state.meal) ? state.meal : null,
            hint: const Text('Servicio'),
            items: [for (final m in meals) DropdownMenuItem(value: m.key, child: Text('${m.label} (${m.time})'))],
            onChanged: (value) => value == null ? null : controller.setMeal(value),
          ),
        ]),
      if (state.tab == 'active')
        Row(mainAxisSize: MainAxisSize.min, children: [
          Checkbox(value: state.showAll, onChanged: (v) => controller.setShowAll(v ?? true)),
          GestureDetector(onTap: () => controller.setShowAll(!state.showAll), child: const Text('Mostrar todas las activas')),
        ]),
      SizedBox(
        width: 260,
        child: TextField(
          controller: search,
          focusNode: searchFocus,
          onChanged: onSearch,
          decoration: const InputDecoration(isDense: true, prefixIcon: Icon(Icons.search), hintText: 'Buscar habitación o paciente'),
        ),
      ),
      Row(mainAxisSize: MainAxisSize.min, children: [
        const LiveStatus(fallbackSeconds: DispatchBoardController.fallbackSeconds),
        const AlertsToggle(),
        IconButton(tooltip: 'Actualizar', onPressed: controller.refresh, icon: const Icon(Icons.refresh)),
      ]),
    ]);
  }
}

/// Cambios sin confirmar: se revisan y confirman antes de entregar
class _PendingBanner extends StatelessWidget {
  const _PendingBanner({required this.pending, required this.busy, required this.onConfirm});

  final List<PendingChange> pending;
  final bool busy;
  final ValueChanged<PendingChange> onConfirm;

  @override
  Widget build(BuildContext context) {
    final n = pending.length;
    return Semantics(
      liveRegion: true,
      container: true,
      child: Container(
        padding: const EdgeInsets.fromLTRB(14, 10, 14, 10),
        decoration: BoxDecoration(
          color: NtColors.primarioSuave,
          borderRadius: BorderRadius.circular(10),
          border: const Border(left: BorderSide(color: NtColors.btnPrimary, width: 5)),
        ),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            const Icon(Icons.notifications_active_outlined, size: 18, color: NtColors.primarioSuaveTexto),
            const SizedBox(width: 6),
            Text(n == 1 ? '1 cambio sin confirmar' : '$n cambios sin confirmar',
                style: const TextStyle(fontWeight: FontWeight.w700, color: NtColors.primarioSuaveTexto)),
            const SizedBox(width: 8),
            const Text('Revisa y confirma antes de entregar.', style: TextStyle(color: NtColors.textoSuave, fontSize: 13)),
          ]),
          const SizedBox(height: 8),
          ConstrainedBox(
            constraints: const BoxConstraints(maxHeight: 112),
            child: SingleChildScrollView(
              child: Wrap(spacing: 6, runSpacing: 6, children: [
                for (final p in pending)
                  Container(
                    padding: const EdgeInsets.fromLTRB(6, 3, 4, 3),
                    decoration: BoxDecoration(color: NtColors.superficie, borderRadius: BorderRadius.circular(6)),
                    child: Row(mainAxisSize: MainAxisSize.min, children: [
                      RoomBadge(p.habitation),
                      const SizedBox(width: 6),
                      ConstrainedBox(
                        constraints: const BoxConstraints(maxWidth: 200),
                        child: Text(p.patient, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w600)),
                      ),
                      if (p.lastChange != null) ...[
                        const SizedBox(width: 6),
                        Text(p.lastChange!, style: const TextStyle(color: NtColors.textoSuave, fontSize: 12.5)),
                      ],
                      const SizedBox(width: 6),
                      FilledButton(
                        style: FilledButton.styleFrom(minimumSize: const Size(0, 30), padding: const EdgeInsets.symmetric(horizontal: 10)),
                        onPressed: busy ? null : () => onConfirm(p),
                        child: Text('Confirmar', semanticsLabel: 'Confirmar cambio de la habitación ${p.habitation}'),
                      ),
                    ]),
                  ),
              ]),
            ),
          ),
        ]),
      ),
    );
  }
}

class _DietTable extends ConsumerWidget {
  const _DietTable({
    required this.state,
    required this.board,
    required this.catalogs,
    required this.busy,
    required this.onConfirm,
    required this.onCancelDispatch,
  });

  final DispatchState state;
  final Board board;
  final Catalogs catalogs;
  final bool busy;
  final ValueChanged<BoardDiet> onConfirm;
  final ValueChanged<BoardDiet> onCancelDispatch;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final controller = ref.read(dispatchBoardProvider.notifier);
    final active = state.tab == 'active';
    if (board.diets.isEmpty) {
      return Center(
        child: Text(
          state.query.isNotEmpty ? 'Ninguna dieta coincide con «${state.query}».' : (active ? 'No hay dietas activas para este servicio.' : 'No hay dietas canceladas ni finalizadas.'),
          style: const TextStyle(color: NtColors.textoSuave),
        ),
      );
    }

    final columns = active
        ? const ['Estado', 'Hab.', 'Paciente', 'Tiempo', 'Consistencia', 'Especificaciones', 'Observaciones', 'Aislam.', 'Último cambio', '']
        : const ['Estado', 'Hab.', 'Paciente', 'Tiempo', 'Observaciones', 'Último cambio', ''];

    return Container(
      decoration: BoxDecoration(color: NtColors.superficie, borderRadius: BorderRadius.circular(10), border: Border.all(color: NtColors.borde)),
      child: SingleChildScrollView(
        child: SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          child: DataTable(
            showCheckboxColumn: active,
            onSelectAll: active && board.dispatchable.isNotEmpty ? (v) => controller.toggleAll(v ?? false) : null,
            headingRowHeight: 40,
            dataRowMinHeight: 48,
            dataRowMaxHeight: double.infinity,
            columnSpacing: 18,
            headingTextStyle: const TextStyle(fontWeight: FontWeight.w600, color: NtColors.textoSuave, fontSize: 12.5),
            columns: [for (final c in columns) DataColumn(label: Text(c))],
            rows: [
              for (final d in board.diets)
                DataRow(
                  key: ValueKey('diet-${d.id}'),
                  color: d.hasPending ? WidgetStateProperty.all(NtColors.advertenciaSuave) : null,
                  selected: state.selected.contains(d.id),
                  onSelectChanged: active && board.dispatchable.contains(d.id) ? (_) => controller.toggle(d.id) : null,
                  cells: active ? _activeCells(d) : _closedCells(d),
                ),
            ],
          ),
        ),
      ),
    );
  }

  List<DataCell> _activeCells(BoardDiet d) => [
        DataCell(_StatusCell(diet: d, catalogs: catalogs, busy: busy, onCancelDispatch: () => onCancelDispatch(d))),
        DataCell(RoomBadge(d.habitation)),
        DataCell(ConstrainedBox(constraints: const BoxConstraints(maxWidth: 200), child: Text(d.patient))),
        DataCell(_tags(d, 'timeFood', d.timeFood, TagKind.meal, catalogs.mealLabel)),
        DataCell(_tags(d, 'consistency', d.consistency, TagKind.consistency, catalogs.consistencyLabel)),
        DataCell(_tags(d, 'specifications', d.specifications, TagKind.specification, catalogs.specificationLabel)),
        DataCell(_simple(d, 'observations', d.observations, maxWidth: 220)),
        DataCell(_simple(d, 'isolation', d.isolation)),
        DataCell(Text(d.lastChange ?? '—', style: const TextStyle(fontSize: 13))),
        DataCell(_confirmButton(d)),
      ];

  List<DataCell> _closedCells(BoardDiet d) => [
        DataCell(Text(d.closedLabel, style: const TextStyle(fontWeight: FontWeight.w600))),
        DataCell(RoomBadge(d.habitation)),
        DataCell(ConstrainedBox(constraints: const BoxConstraints(maxWidth: 200), child: Text(d.patient))),
        DataCell(_tags(d, 'timeFood', d.timeFood, TagKind.meal, catalogs.mealLabel)),
        DataCell(_simple(d, 'observations', d.observations, maxWidth: 260)),
        DataCell(Text(d.lastChange ?? '—', style: const TextStyle(fontSize: 13))),
        DataCell(_confirmButton(d)),
      ];

  Widget _confirmButton(BoardDiet d) => d.hasPending
      ? FilledButton(
          style: FilledButton.styleFrom(minimumSize: const Size(0, 32), padding: const EdgeInsets.symmetric(horizontal: 12)),
          onPressed: busy ? null : () => onConfirm(d),
          child: Text('Confirmar', semanticsLabel: 'Confirmar cambio de la habitación ${d.habitation}'),
        )
      : const SizedBox.shrink();

  /// Etiquetas actuales, con lo agregado marcado y lo quitado tachado (cambios sin confirmar)
  Widget _tags(BoardDiet d, String field, List<String> values, TagKind kind, String Function(String) label) {
    final change = d.changes[field];
    final isMeal = kind == TagKind.meal;
    Delivery delivery(String key) => switch (isMeal ? d.delivery[Catalogs.normalizeMeal(key)] ?? d.delivery[key] : null) {
          'received' => Delivery.received,
          'dispatched' => Delivery.dispatched,
          _ => Delivery.none,
        };

    return ConstrainedBox(
      constraints: const BoxConstraints(maxWidth: 240),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 6),
        child: Wrap(spacing: 4, runSpacing: 4, children: [
          for (final v in values)
            NtTag(label(v), kind: kind, change: change?.added.contains(v) == true ? TagChange.added : TagChange.none, delivery: delivery(v)),
          for (final v in change?.removed ?? const <String>[]) NtTag(label(v), kind: kind, change: TagChange.removed),
          if (values.isEmpty && (change?.removed.isEmpty ?? true)) const Text('—'),
        ]),
      ),
    );
  }

  Widget _simple(BoardDiet d, String field, String? value, {double maxWidth = 120}) {
    final change = d.changes[field];
    final text = (value == null || value.isEmpty) ? '—' : value;
    return ConstrainedBox(
      constraints: BoxConstraints(maxWidth: maxWidth),
      child: change == null
          ? Text(text, style: const TextStyle(fontSize: 13))
          : Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
              if ((change.oldValue ?? '').isNotEmpty)
                Text(change.oldValue!, style: const TextStyle(fontSize: 12, color: NtColors.textoSuave, decoration: TextDecoration.lineThrough)),
              Text(text, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700)),
            ]),
    );
  }
}

/// Estado de la dieta en el servicio elegido
class _StatusCell extends StatelessWidget {
  const _StatusCell({required this.diet, required this.catalogs, required this.busy, required this.onCancelDispatch});

  final BoardDiet diet;
  final Catalogs catalogs;
  final bool busy;
  final VoidCallback onCancelDispatch;

  @override
  Widget build(BuildContext context) {
    final d = diet;
    final record = d.dispatch;
    final children = <Widget>[];

    if (record != null) {
      children.add(Text(
        '${record.received ? 'Recibida' : 'Despachada'} ${formatTime(record.received ? record.receivedAt : record.dispatchedAt)}',
        style: const TextStyle(fontWeight: FontWeight.w600, color: NtColors.dietaEntregado, fontSize: 13),
      ));
      if (record.dispatchedBy != null) children.add(Text('Por ${record.dispatchedBy}', style: const TextStyle(fontSize: 12, color: NtColors.textoSuave)));
      if (!record.received) {
        children.add(TextButton(
          style: TextButton.styleFrom(foregroundColor: NtColors.btnDanger, padding: EdgeInsets.zero, minimumSize: const Size(0, 28)),
          onPressed: busy ? null : onCancelDispatch,
          child: Text('Anular despacho', semanticsLabel: 'Anular despacho: habitación ${d.habitation}'),
        ));
      }
    } else if (d.missing) {
      children.add(Tooltip(
        message: d.missingReason?.isNotEmpty == true ? 'Motivo: ${d.missingReason}' : 'Nutrición reportó que no llegó.',
        child: const Text('No llegó: vuelve a despacharla', style: TextStyle(fontWeight: FontWeight.w700, color: NtColors.advertencia, fontSize: 13)),
      ));
    } else if (d.blockReason != null) {
      children.add(Text(d.blockReason!, style: const TextStyle(fontSize: 12.5, color: NtColors.textoSuave)));
    } else {
      children.add(const Text('Lista para despachar', style: TextStyle(fontSize: 13)));
    }

    if (d.transferMeals.isNotEmpty) {
      children.add(Text(
        'Alta: enviar ${d.transferMeals.map(catalogs.mealLabel).join(', ').toLowerCase()} a ${d.transferDestination ?? 'otro lugar'}',
        style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: NtColors.btnPrimary),
      ));
    }

    return ConstrainedBox(
      constraints: const BoxConstraints(maxWidth: 200),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 6),
        child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: children),
      ),
    );
  }
}
