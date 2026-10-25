import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../auth/session.dart';
import '../core/api_client.dart';
import '../core/api_exception.dart';
import '../live/alerts.dart';
import '../live/live_feed.dart';
import 'models.dart';

/// Filtros, datos y selección del tablero de despacho
class DispatchState {
  const DispatchState({
    this.board,
    this.loading = true,
    this.error,
    this.meal,
    this.tab = 'active',
    this.showAll = true,
    this.query = '',
    this.page = 1,
    this.perPage = 15,
    this.selected = const {},
  });

  final Board? board;
  final bool loading;
  final String? error;

  /// Servicio elegido; null = el que indica el servidor según la hora (DispatchService::currentMeal)
  final String? meal;
  final String tab;
  final bool showAll;
  final String query;
  final int page;
  final int perPage;
  final Set<int> selected;

  static const _keep = Object();

  DispatchState copyWith({
    Board? board,
    bool? loading,
    Object? error = _keep,
    String? meal,
    String? tab,
    bool? showAll,
    String? query,
    int? page,
    int? perPage,
    Set<int>? selected,
  }) =>
      DispatchState(
        board: board ?? this.board,
        loading: loading ?? this.loading,
        error: identical(error, _keep) ? this.error : error as String?,
        meal: meal ?? this.meal,
        tab: tab ?? this.tab,
        showAll: showAll ?? this.showAll,
        query: query ?? this.query,
        page: page ?? this.page,
        perPage: perPage ?? this.perPage,
        selected: selected ?? this.selected,
      );

  Map<String, dynamic> get params => {
        if (meal != null) 'servicio': meal,
        'tab': tab,
        'todas': showAll ? 1 : 0,
        if (query.isNotEmpty) 'q': query,
        'page': page,
        'per_page': perPage,
      };
}

/// Tablero del despachador (App\Livewire\DietsManager): se refresca con cada aviso de tiempo
/// real (sin WebSocket, cada 3 s) y alerta cuando llega un cambio de dieta sin confirmar.
/// Vive mientras dura la sesión, aunque la pantalla no esté abierta, para no perder alertas.
final dispatchBoardProvider = NotifierProvider.autoDispose<DispatchBoardController, DispatchState>(DispatchBoardController.new);

class DispatchBoardController extends Notifier<DispatchState> {
  static const fallbackSeconds = 3;

  /// Claves «dieta:versión» ya vistas: solo alertan las nuevas (null antes de la primera carga)
  Set<String>? _seen;
  bool _loading = false;
  bool _again = false;

  ApiClient get _api => ref.read(sessionProvider.notifier).client;

  @override
  DispatchState build() {
    final feed = ref.watch(liveFeedProvider);
    final ticker = LiveTicker(feed, kinds: const {'diet', 'dispatch', 'day'}, fallback: const Duration(seconds: fallbackSeconds), onTick: refresh);
    ref.onDispose(ticker.dispose);
    _seen = null;
    Future.microtask(refresh);
    return const DispatchState();
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
        final params = state.params;
        final data = await _api.get('/dispatch/board', query: params);
        if (!ref.mounted) return;
        // Los filtros cambiaron mientras se consultaba: se vuelve a pedir con los nuevos
        if (state.params.toString() != params.toString()) {
          _again = true;
          continue;
        }
        final board = Board.fromJson(data);
        _alertNewChanges(board.pending);
        state = state.copyWith(
          board: board,
          meal: board.meal,
          loading: false,
          error: null,
          selected: state.selected.intersection(board.dispatchable),
        );
      } while (_again);
    } on ApiException catch (e) {
      if (!ref.mounted) return;
      if (e.isUnauthorized) {
        await ref.read(sessionProvider.notifier).expired();
        return;
      }
      state = state.copyWith(loading: false, error: e.message);
    } finally {
      _loading = false;
    }
  }

  void _alertNewChanges(List<PendingChange> pending) {
    final keys = pending.map((p) => p.key).toSet();
    final fresh = _seen == null ? <PendingChange>[] : pending.where((p) => !_seen!.contains(p.key)).toList();
    _seen = keys;
    if (fresh.isEmpty) return;

    final body = fresh.length == 1
        ? 'Hab. ${fresh.first.habitation}${fresh.first.lastChange != null ? ' · ${fresh.first.lastChange}' : ''}. Revisa y confirma antes de entregar.'
        : 'Hab. ${fresh.map((p) => p.habitation).join(', ')}. Revisa y confirma antes de entregar.';
    alert(ref, fresh.length == 1 ? 'Cambio de dieta' : '${fresh.length} cambios de dieta', body);
  }

  void _filter(DispatchState next) {
    state = next.copyWith(loading: true, selected: const {});
    refresh();
  }

  void setMeal(String meal) => _filter(state.copyWith(meal: meal, page: 1));
  void setTab(String tab) => _filter(state.copyWith(tab: tab, page: 1));
  void setShowAll(bool value) => _filter(state.copyWith(showAll: value, page: 1));
  void setQuery(String query) => _filter(state.copyWith(query: query.trim(), page: 1));
  void setPage(int page) => _filter(state.copyWith(page: page));
  void setPerPage(int perPage) => _filter(state.copyWith(perPage: perPage, page: 1));

  void toggle(int dietId) {
    if (!(state.board?.dispatchable.contains(dietId) ?? false)) return;
    final selected = {...state.selected};
    selected.contains(dietId) ? selected.remove(dietId) : selected.add(dietId);
    state = state.copyWith(selected: selected);
  }

  /// Marca o desmarca todas las de la página que se pueden despachar
  void toggleAll(bool select) => state = state.copyWith(selected: select ? {...?state.board?.dispatchable} : const {});

  /// Despacha las seleccionadas en el servicio elegido. Devuelve el resumen para el aviso.
  Future<String> dispatchSelected() async {
    final ids = state.selected.toList();
    final data = await _api.post('/dispatch', {'diet_ids': ids, 'meal': state.meal});
    final dispatched = (data['dispatched'] as List? ?? const []).map((h) => '$h').toList();
    final skipped = data['skipped'] is Map ? Map<String, dynamic>.from(data['skipped'] as Map) : const <String, dynamic>{};
    state = state.copyWith(selected: const {});
    await refresh();

    return [
      if (dispatched.isNotEmpty) 'Despachadas: hab. ${dispatched.join(', ')}. Nutrición debe confirmar la recepción.',
      if (skipped.isNotEmpty) 'No se despacharon: ${skipped.entries.map((e) => 'hab. ${e.key} (${e.value})').join('; ')}',
    ].join(' ');
  }

  Future<String> confirmChanges(int dietId) async {
    final data = await _api.post('/diets/$dietId/confirm-changes');
    await refresh();
    return '${data['message'] ?? 'Cambios confirmados.'}';
  }

  /// «Anular despacho» (error de cocina): ese tiempo se puede despachar de nuevo y no se factura
  Future<String> cancelDispatch(int dispatchId, String reason, String habitation) async {
    await _api.post('/dispatches/$dispatchId/void', {'kind': 'cancelled', if (reason.isNotEmpty) 'reason': reason});
    await refresh();
    return 'Despacho anulado: hab. $habitation. Se puede despachar de nuevo.';
  }
}
