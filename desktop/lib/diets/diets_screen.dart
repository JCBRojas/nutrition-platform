import 'dart:async';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';

import '../auth/models.dart';
import '../auth/session.dart';
import '../common/widgets.dart';
import '../core/api_exception.dart';
import '../core/catalogs.dart';
import '../core/theme.dart';
import 'close_dialog.dart';
import 'diet_form.dart';
import 'diets_controller.dart';
import 'models.dart';

/// Panel de dietas (App\Livewire\NutritionDiets): indicadores, pestañas, búsqueda, estado del
/// día, «Continuar», registrar, editar, dar de alta y cancelar. Sin createDiets ni editDiets
/// queda de solo lectura.
class DietsScreen extends ConsumerStatefulWidget {
  const DietsScreen({super.key});

  @override
  ConsumerState<DietsScreen> createState() => _DietsScreenState();
}

class _DietsScreenState extends ConsumerState<DietsScreen> {
  final _search = TextEditingController();
  final _searchFocus = FocusNode();
  Timer? _debounce;
  bool _busy = false;

  DietsController get _controller => ref.read(dietsProvider.notifier);

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

  Future<void> _register(Catalogs catalogs) async {
    final message = await showDietForm(context, catalogs: catalogs, activeRooms: ref.read(dietsProvider).data?.activeRooms ?? const {}, onSubmit: _controller.create);
    if (message != null && mounted) showNotice(context, message);
  }

  Future<void> _edit(Diet diet, Catalogs catalogs) async {
    final message = await showDietForm(context, catalogs: catalogs, diet: diet, onSubmit: (input) => _controller.update(diet, input));
    if (message != null && mounted) showNotice(context, message);
  }

  Future<void> _continue(Diet diet) async {
    final ok = await confirmDialog(
      context,
      title: 'Continuar dieta',
      message: '¿Continuar la dieta de ${diet.patient} (hab. ${diet.habitation}) mañana? Cocina podrá despacharla el día siguiente.',
      confirm: 'Continuar',
    );
    if (ok) await _run(() => _controller.continueDiet(diet));
  }

  /// Consulta el resumen de cierre y abre el modal de alta o cancelación
  Future<void> _close(Diet diet, {required bool finish}) async {
    ClosingSummary? summary;
    await _run(() async {
      summary = await _controller.closingSummary(diet);
      return '';
    });
    if (summary == null || !mounted) return;

    final message = await showCloseDialog(
      context,
      diet: diet,
      finish: finish,
      summary: summary!,
      onConfirm: (transfer) => finish ? _controller.finish(diet, destination: transfer?.destination, meals: transfer?.meals ?? const []) : _controller.cancel(diet),
    );
    if (message != null && mounted) showNotice(context, message);
  }

  Future<void> _history(Diet diet) async {
    final base = ref.read(sessionProvider.notifier).meta?.webUrl ?? '';
    await launchUrl(Uri.parse('$base/historial?dieta=${diet.id}'));
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(dietsProvider);
    final me = switch (ref.watch(sessionProvider)) {
      LoggedIn(:final me) => me,
      _ => null,
    };
    final catalogs = Catalogs.fromMeta(ref.read(sessionProvider.notifier).meta);
    final data = state.data;
    final canCreate = me?.can('createDiets') ?? false;
    final mac = Platform.isMacOS;

    return CallbackShortcuts(
      bindings: {
        SingleActivator(LogicalKeyboardKey.keyF, meta: mac, control: !mac): () => _searchFocus.requestFocus(),
        SingleActivator(LogicalKeyboardKey.keyR, meta: mac, control: !mac): _controller.refresh,
        SingleActivator(LogicalKeyboardKey.keyN, meta: mac, control: !mac): () {
          if (canCreate) _register(catalogs);
        },
      },
      child: Focus(
        autofocus: true,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 16, 20, 12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Wrap(
                spacing: 16,
                runSpacing: 10,
                crossAxisAlignment: WrapCrossAlignment.center,
                children: [
                  Text('Dietas activas', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700)),
                  SegmentedButton<String>(
                    showSelectedIcon: false,
                    segments: [
                      for (final (key, name) in const [('active', 'Activas'), ('cancelled', 'Canceladas'), ('finished', 'Finalizadas')])
                        ButtonSegment(value: key, label: Text('$name (${data?.counts[key] ?? 0})')),
                    ],
                    selected: {state.tab},
                    onSelectionChanged: (s) => _controller.setTab(s.first),
                  ),
                  SizedBox(
                    width: 260,
                    child: TextField(
                      controller: _search,
                      focusNode: _searchFocus,
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
                      const LiveStatus(fallbackSeconds: DietsController.fallbackSeconds),
                      IconButton(tooltip: 'Actualizar', onPressed: _controller.refresh, icon: const Icon(Icons.refresh)),
                    ],
                  ),
                  if (canCreate)
                    FilledButton.icon(
                      style: FilledButton.styleFrom(backgroundColor: NtColors.btnPrimary),
                      onPressed: () => _register(catalogs),
                      icon: const Icon(Icons.add),
                      label: const Text('Registrar dieta'),
                    ),
                ],
              ),
              const SizedBox(height: 12),
              if (data != null) ...[_Stats(data: data), const SizedBox(height: 12)],
              if (state.error != null) ...[LoadError(message: state.error!, onRetry: _controller.refresh), const SizedBox(height: 12)],
              if (state.tab == 'active') const Padding(padding: EdgeInsets.only(bottom: 8), child: _Legend()),
              Expanded(
                child: data == null
                    ? (state.error != null ? const SizedBox.shrink() : const Center(child: CircularProgressIndicator()))
                    : data.diets.isEmpty
                    ? Center(
                        child: Text(
                          _emptyText(state),
                          style: const TextStyle(color: NtColors.textoSuave),
                          textAlign: TextAlign.center,
                        ),
                      )
                    : _DietTable(
                        data: data,
                        me: me,
                        catalogs: catalogs,
                        busy: _busy,
                        onContinue: _continue,
                        onEdit: (d) => _edit(d, catalogs),
                        onFinish: (d) => _close(d, finish: true),
                        onCancel: (d) => _close(d, finish: false),
                        onHistory: _history,
                      ),
              ),
              if (data != null) ...[
                const SizedBox(height: 8),
                PaginationBar(page: data.page, lastPage: data.lastPage, perPage: data.perPage, total: data.total, onPage: _controller.setPage, onPerPage: _controller.setPerPage),
              ],
            ],
          ),
        ),
      ),
    );
  }

  static String _emptyText(DietsState state) {
    if (state.query.isNotEmpty) return 'No hay dietas que coincidan con «${state.query}».';
    return switch (state.tab) {
      'cancelled' => 'No hay dietas canceladas.',
      'finished' => 'No hay dietas finalizadas. Usa el botón de alta en una dieta activa cuando el paciente salga de la institución.',
      _ => 'No hay dietas activas.',
    };
  }
}

/// Indicadores de arriba (stats-grid de la web)
class _Stats extends StatelessWidget {
  const _Stats({required this.data});

  final DietsPage data;

  @override
  Widget build(BuildContext context) {
    Widget card(String label, int value, String sub, Color accent) => Container(
      width: 230,
      padding: const EdgeInsets.fromLTRB(14, 10, 14, 10),
      // Bordes de distinto color no admiten borderRadius: franja de color como sombra interior
      decoration: BoxDecoration(
        color: NtColors.superficie,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: NtColors.borde),
        boxShadow: [BoxShadow(color: accent, offset: const Offset(-4, 0), spreadRadius: -1)],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: const TextStyle(color: NtColors.textoSuave, fontSize: 12.5, fontWeight: FontWeight.w600),
          ),
          Text('$value', style: const TextStyle(fontSize: 24, fontWeight: FontWeight.w700)),
          Text(sub, style: const TextStyle(color: NtColors.textoSuave, fontSize: 12)),
        ],
      ),
    );

    return Wrap(
      spacing: 12,
      runSpacing: 12,
      children: [
        card('Dietas activas', data.counts['active'] ?? 0, 'Pacientes en seguimiento', NtColors.exito),
        card('Canceladas', data.counts['cancelled'] ?? 0, '${data.counts['finished'] ?? 0} finalizadas por alta', NtColors.error),
        card('En aislamiento', data.isolated, 'Activas con protocolo especial', NtColors.advertencia),
        card('Con cambios', data.withChanges, 'Última versión con cambios', NtColors.btnPrimary),
      ],
    );
  }
}

/// Leyenda de la columna Tiempo (x-meal-legend)
class _Legend extends StatelessWidget {
  const _Legend();

  @override
  Widget build(BuildContext context) => const Wrap(
    spacing: 10,
    crossAxisAlignment: WrapCrossAlignment.center,
    children: [
      NtTag('Despachado', kind: TagKind.meal, delivery: Delivery.dispatched),
      NtTag('Entregado', kind: TagKind.meal, delivery: Delivery.received),
      Text('Estado de hoy en la columna Tiempo', style: TextStyle(color: NtColors.textoSuave, fontSize: 12.5)),
    ],
  );
}

class _DietTable extends StatelessWidget {
  const _DietTable({
    required this.data,
    required this.me,
    required this.catalogs,
    required this.busy,
    required this.onContinue,
    required this.onEdit,
    required this.onFinish,
    required this.onCancel,
    required this.onHistory,
  });

  final DietsPage data;
  final Me? me;
  final Catalogs catalogs;
  final bool busy;
  final ValueChanged<Diet> onContinue;
  final ValueChanged<Diet> onEdit;
  final ValueChanged<Diet> onFinish;
  final ValueChanged<Diet> onCancel;
  final ValueChanged<Diet> onHistory;

  bool get _active => data.tab == 'active';
  bool get _canEdit => me?.can('editDiets') ?? false;
  bool get _canHistory => (me?.can('viewDiets') ?? false) && (me?.hasPremium('history') ?? false);

  Widget _tags(List<String> keys, TagKind kind, String Function(String) label, {Map<String, String> delivery = const {}}) {
    if (keys.isEmpty) return const Text('—');
    return Wrap(
      spacing: 4,
      runSpacing: 4,
      children: [
        for (final k in keys)
          NtTag(
            label(k),
            kind: kind,
            delivery: switch (delivery[Catalogs.normalizeMeal(k)]) {
              'received' => Delivery.received,
              'dispatched' => Delivery.dispatched,
              _ => Delivery.none,
            },
          ),
      ],
    );
  }

  Widget _text(String? value, {double width = 200}) => ConstrainedBox(
    constraints: BoxConstraints(maxWidth: width),
    child: Text(value == null || value.isEmpty ? '—' : value, overflow: TextOverflow.ellipsis, maxLines: 2, style: const TextStyle(fontSize: 13)),
  );

  Widget _day(Diet d) {
    final day = d.day ?? const DayStatus();
    final (label, fg, bg, tip) = day.completed
        ? ('Completada', NtColors.exito, NtColors.exitoSuave, 'Se recibieron todos los tiempos de hoy')
        : day.authorizedToday
        ? ('En curso', NtColors.primarioSuaveTexto, NtColors.primarioSuave, 'Autorizada para hoy; faltan tiempos por recibir')
        : ('Sin autorizar hoy', NtColors.advertencia, NtColors.advertenciaSuave, 'No se puede despachar hoy: no se continuó ayer');

    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Tooltip(
          message: tip,
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
            decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(6)),
            child: Text(
              label,
              style: TextStyle(color: fg, fontSize: 12, fontWeight: FontWeight.w600),
            ),
          ),
        ),
        const SizedBox(width: 6),
        if (day.authorizedTomorrow)
          const Tooltip(
            message: 'Continúa mañana: cocina puede despacharla',
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(Icons.check, size: 14, color: NtColors.exito),
                Text(
                  'Mañana',
                  style: TextStyle(color: NtColors.exito, fontSize: 12, fontWeight: FontWeight.w600),
                ),
              ],
            ),
          )
        else if (me?.can('continueDiets') ?? false)
          TextButton.icon(
            style: TextButton.styleFrom(minimumSize: const Size(0, 30), padding: const EdgeInsets.symmetric(horizontal: 8), visualDensity: VisualDensity.compact),
            onPressed: busy ? null : () => onContinue(d),
            icon: const Icon(Icons.arrow_forward, size: 16),
            label: Text('Continuar', semanticsLabel: 'Continuar dieta de ${d.patient} mañana'),
          )
        else
          const Tooltip(
            message: 'Mañana sin autorizar',
            child: Text('Mañana —', style: TextStyle(color: NtColors.textoSuave, fontSize: 12)),
          ),
      ],
    );
  }

  Widget _iconButton(String tooltip, IconData icon, Color color, VoidCallback? onPressed) => IconButton(
    tooltip: tooltip,
    visualDensity: VisualDensity.compact,
    onPressed: busy ? null : onPressed,
    icon: Icon(icon, size: 20, color: busy ? null : color),
  );

  Widget _actions(Diet d) => Row(
    mainAxisSize: MainAxisSize.min,
    children: [
      if (!_active) _closedTag(),
      if (_canHistory) _iconButton('Ver historial (se abre en el navegador)', Icons.history, NtColors.textoSuave, () => onHistory(d)),
      if (_active && _canEdit) ...[
        _iconButton('Editar dieta de ${d.patient}', Icons.edit_outlined, NtColors.btnSuccess, () => onEdit(d)),
        _iconButton('Dar de alta (finalizar dieta)', Icons.logout, NtColors.primarioSuaveTexto, () => onFinish(d)),
        _iconButton('Cancelar dieta', Icons.cancel_outlined, NtColors.btnDanger, () => onCancel(d)),
      ],
    ],
  );

  Widget _closedTag() {
    final cancelled = data.tab == 'cancelled';
    return Container(
      margin: const EdgeInsets.only(right: 4),
      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
      decoration: BoxDecoration(color: cancelled ? NtColors.errorSuave : NtColors.primarioSuave, borderRadius: BorderRadius.circular(6)),
      child: Text(
        cancelled ? 'Cancelada' : 'Finalizada',
        style: TextStyle(color: cancelled ? NtColors.error : NtColors.primarioSuaveTexto, fontSize: 12, fontWeight: FontWeight.w600),
      ),
    );
  }

  static String _date(DateTime? value) {
    if (value == null) return '—';
    final l = value.toLocal();
    String two(int n) => n.toString().padLeft(2, '0');
    return '${two(l.day)}/${two(l.month)}/${l.year} ${formatTime(l)}';
  }

  @override
  Widget build(BuildContext context) {
    final columns = _active
        ? const ['Hab.', 'Paciente', 'Día', 'Tiempo', 'Consistencia', 'Especificaciones', 'Observaciones', 'Aislam.', 'Cambios', 'Acciones']
        : ['Hab.', 'Paciente', 'Tiempo', 'Consistencia', 'Observaciones', data.tab == 'cancelled' ? 'Cancelada' : 'Alta', 'Por', 'Estado'];

    return Container(
      decoration: BoxDecoration(
        color: NtColors.superficie,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: NtColors.borde),
      ),
      child: SingleChildScrollView(
        child: SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          child: DataTable(
            headingRowHeight: 40,
            dataRowMinHeight: 48,
            dataRowMaxHeight: 76,
            columnSpacing: 20,
            headingTextStyle: const TextStyle(fontWeight: FontWeight.w600, color: NtColors.textoSuave, fontSize: 12.5),
            columns: [for (final c in columns) DataColumn(label: Text(c))],
            rows: [
              for (final d in data.diets)
                DataRow(
                  key: ValueKey('diet-${d.id}-v${d.version}'),
                  cells: _active
                      ? [
                          DataCell(RoomBadge(d.habitation)),
                          DataCell(_text(d.patient, width: 200)),
                          DataCell(_day(d)),
                          DataCell(_tags(d.timeFood, TagKind.meal, catalogs.mealLabel, delivery: d.delivery)),
                          DataCell(_tags(d.consistency, TagKind.consistency, catalogs.consistencyLabel)),
                          DataCell(_tags(d.specifications, TagKind.specification, catalogs.specificationLabel)),
                          DataCell(_text(d.observations, width: 180)),
                          DataCell(Text(d.isolated ? 'Sí' : 'No')),
                          DataCell(Text(Catalogs.changeLabel(d.changes), style: const TextStyle(fontSize: 13))),
                          DataCell(_actions(d)),
                        ]
                      : [
                          DataCell(RoomBadge(d.habitation)),
                          DataCell(_text(d.patient, width: 200)),
                          DataCell(_tags(d.timeFood, TagKind.meal, catalogs.mealLabel)),
                          DataCell(_tags(d.consistency, TagKind.consistency, catalogs.consistencyLabel)),
                          DataCell(_text(d.observations, width: 180)),
                          DataCell(Text(_date(d.updatedAt), style: const TextStyle(fontSize: 13))),
                          DataCell(Text(d.updatedBy ?? '—', style: const TextStyle(fontSize: 13))),
                          DataCell(_actions(d)),
                        ],
                ),
            ],
          ),
        ),
      ),
    );
  }
}
