import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../auth/session.dart';
import '../core/api_client.dart';
import '../core/api_exception.dart';
import '../live/alerts.dart';
import '../live/live_feed.dart';

/// Despacho que espera la confirmación de recepción (GET /api/v1/receipts/pending)
class PendingReceipt {
  PendingReceipt({required this.id, required this.dietId, required this.habitation, required this.patient, required this.meal, required this.mealLabel, this.dispatchedAt, this.dispatchedBy});

  final int id;
  final int dietId;
  final String habitation;
  final String patient;
  final String meal;
  final String mealLabel;
  final DateTime? dispatchedAt;
  final String? dispatchedBy;

  factory PendingReceipt.fromJson(Map<String, dynamic> json) => PendingReceipt(
        id: json['id'] as int,
        dietId: json['diet_id'] as int,
        habitation: '${json['habitation']}',
        patient: '${json['name_patient'] ?? ''}',
        meal: '${json['meal']}',
        mealLabel: '${json['meal_label'] ?? json['meal']}',
        dispatchedAt: json['dispatched_at'] == null ? null : DateTime.tryParse('${json['dispatched_at']}'),
        dispatchedBy: json['dispatched_by']?.toString(),
      );
}

class ReceiptsState {
  const ReceiptsState({this.items, this.error, this.selected = const {}});

  /// null mientras carga la primera vez
  final List<PendingReceipt>? items;
  final String? error;
  final Set<int> selected;

  bool get allSelected => items != null && items!.isNotEmpty && selected.length == items!.length;
}

/// Recepción del nutricionista (App\Livewire\DispatchReceipts): se refresca con los avisos de
/// despachos (sin WebSocket, cada 5 s) y alerta cuando cocina despacha algo nuevo. Vive
/// mientras dura la sesión, aunque la pantalla no esté abierta.
final receiptsProvider = NotifierProvider.autoDispose<ReceiptsController, ReceiptsState>(ReceiptsController.new);

class ReceiptsController extends Notifier<ReceiptsState> {
  static const fallbackSeconds = 5;

  /// Despachos ya vistos: solo alertan los nuevos (null antes de la primera carga)
  Set<int>? _seen;
  bool _loading = false;
  bool _again = false;

  ApiClient get _api => ref.read(sessionProvider.notifier).client;

  @override
  ReceiptsState build() {
    final feed = ref.watch(liveFeedProvider);
    final ticker = LiveTicker(feed, kinds: const {'dispatch'}, fallback: const Duration(seconds: fallbackSeconds), onTick: refresh, safety: const Duration(seconds: 10));
    ref.onDispose(ticker.dispose);
    _seen = null;
    Future.microtask(refresh);
    return const ReceiptsState();
  }

  Future<void> refresh() async {
    if (_loading) {
      _again = true;
      return;
    }
    _loading = true;
    try {
      do {
        _again = false;
        final data = await _api.get('/receipts/pending');
        if (!ref.mounted) return;
        final items = (data['data'] as List? ?? const []).map((r) => PendingReceipt.fromJson(Map<String, dynamic>.from(r as Map))).toList();
        _alertNew(items);
        final ids = items.map((r) => r.id).toSet();
        state = ReceiptsState(items: items, selected: state.selected.intersection(ids));
      } while (_again);
    } on ApiException catch (e) {
      if (!ref.mounted) return;
      if (e.isUnauthorized) {
        await ref.read(sessionProvider.notifier).expired();
        return;
      }
      state = ReceiptsState(items: state.items, error: e.message, selected: state.selected);
    } finally {
      _loading = false;
    }
  }

  void _alertNew(List<PendingReceipt> items) {
    final fresh = _seen == null ? <PendingReceipt>[] : items.where((r) => !_seen!.contains(r.id)).toList();
    _seen = items.map((r) => r.id).toSet();
    if (fresh.isEmpty) return;

    final rooms = fresh.map((r) => r.habitation).toSet().join(', ');
    alert(ref, 'Cocina despachó', 'Hab. $rooms. Confirma la recepción.');
  }

  void toggle(int id) {
    final selected = {...state.selected};
    selected.contains(id) ? selected.remove(id) : selected.add(id);
    state = ReceiptsState(items: state.items, error: state.error, selected: selected);
  }

  void toggleAll(bool select) => state = ReceiptsState(
        items: state.items,
        error: state.error,
        selected: select ? (state.items ?? const []).map((r) => r.id).toSet() : const {},
      );

  /// Confirma la recepción de los seleccionados. Devuelve el resumen para el aviso.
  Future<String> confirmSelected() async {
    final data = await _api.post('/receipts', {'dispatch_ids': state.selected.toList()});
    final received = data['received'] as int? ?? 0;
    final completed = (data['completed'] as List? ?? const []).map((h) => '$h').toList();
    state = ReceiptsState(items: state.items, selected: const {});
    await refresh();

    return [
      received == 1 ? 'Recepción confirmada: 1 despacho.' : 'Recepción confirmada: $received despachos.',
      if (completed.isNotEmpty) 'Día completado: hab. ${completed.join(', ')}.',
    ].join(' ');
  }

  /// «No llegó»: sale de los pendientes, no se factura y cocina lo vuelve a despachar
  Future<String> notArrived(PendingReceipt receipt, String reason) async {
    await _api.post('/dispatches/${receipt.id}/void', {'kind': 'not_arrived', if (reason.isNotEmpty) 'reason': reason});
    await refresh();
    return 'Reportado: ${receipt.mealLabel.toLowerCase()} de la hab. ${receipt.habitation} no llegó. Cocina lo verá para despacharlo de nuevo.';
  }
}
