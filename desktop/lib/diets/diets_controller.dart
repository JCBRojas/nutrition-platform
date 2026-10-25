import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../auth/session.dart';
import '../core/api_client.dart';
import '../core/api_exception.dart';
import '../live/live_feed.dart';
import 'models.dart';

class DietsState {
  const DietsState({this.data, this.loading = true, this.error, this.tab = 'active', this.query = '', this.page = 1, this.perPage = 15});

  final DietsPage? data;
  final bool loading;
  final String? error;
  final String tab;
  final String query;
  final int page;
  final int perPage;

  static const _keep = Object();

  DietsState copyWith({DietsPage? data, bool? loading, Object? error = _keep, String? tab, String? query, int? page, int? perPage}) => DietsState(
    data: data ?? this.data,
    loading: loading ?? this.loading,
    error: identical(error, _keep) ? this.error : error as String?,
    tab: tab ?? this.tab,
    query: query ?? this.query,
    page: page ?? this.page,
    perPage: perPage ?? this.perPage,
  );

  Map<String, dynamic> get params => {'tab': tab, if (query.isNotEmpty) 'q': query, 'page': page, 'per_page': perPage};
}

/// Panel de dietas (App\Livewire\NutritionDiets): se refresca con cada aviso de tiempo real
/// (sin WebSocket, cada 5 s) mientras la pantalla está abierta. No alerta: los avisos de
/// despachos los da Recepción.
final dietsProvider = NotifierProvider.autoDispose<DietsController, DietsState>(DietsController.new);

class DietsController extends Notifier<DietsState> {
  static const fallbackSeconds = 5;

  bool _loading = false;
  bool _again = false;

  ApiClient get _api => ref.read(sessionProvider.notifier).client;

  @override
  DietsState build() {
    final feed = ref.watch(liveFeedProvider);
    final ticker = LiveTicker(
      feed,
      kinds: const {'diet', 'dispatch', 'day'},
      fallback: const Duration(seconds: fallbackSeconds),
      onTick: refresh,
    );
    ref.onDispose(ticker.dispose);
    Future.microtask(refresh);
    return const DietsState();
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
        final data = await _api.get('/diets', query: params);
        if (!ref.mounted) return;
        // Los filtros cambiaron mientras se consultaba: se vuelve a pedir con los nuevos
        if (state.params.toString() != params.toString()) {
          _again = true;
          continue;
        }
        state = state.copyWith(data: DietsPage.fromJson(data), loading: false, error: null);
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

  void _filter(DietsState next) {
    state = next.copyWith(loading: true);
    refresh();
  }

  void setTab(String tab) => _filter(state.copyWith(tab: tab, page: 1));
  void setQuery(String query) => _filter(state.copyWith(query: query.trim(), page: 1));
  void setPage(int page) => _filter(state.copyWith(page: page));
  void setPerPage(int perPage) => _filter(state.copyWith(perPage: perPage, page: 1));

  /// Registrar (createDiets). Lanza ApiException con los errores por campo (422).
  Future<String> create(DietInput input) async {
    final data = await _api.post('/diets', input.toJson(create: true));
    await refresh();
    return 'Dieta registrada: hab. ${data['habitation'] ?? input.habitation.trim()}.';
  }

  /// Nueva versión (editDiets): la habitación y el paciente no cambian
  Future<String> update(Diet diet, DietInput input) async {
    await _api.put('/diets/${diet.id}', input.toJson(create: false));
    await refresh();
    return 'Dieta actualizada: hab. ${diet.habitation}. Cocina verá el cambio para confirmarlo.';
  }

  /// «Continuar dieta»: autoriza el despacho de mañana
  Future<String> continueDiet(Diet diet) async {
    final data = await _api.post('/diets/${diet.id}/continue');
    await refresh();
    return '${data['message'] ?? 'Dieta autorizada para mañana.'}';
  }

  Future<ClosingSummary> closingSummary(Diet diet) async => ClosingSummary.fromJson(await _api.get('/diets/${diet.id}/closing-summary'));

  Future<String> cancel(Diet diet) async {
    final data = await _api.post('/diets/${diet.id}/cancel');
    await refresh();
    return '${data['message'] ?? 'Dieta cancelada.'}';
  }

  /// Alta; con [destination] y [meals], los tiempos de hoy se envían a otro lugar
  Future<String> finish(Diet diet, {String? destination, List<String> meals = const []}) async {
    final data = await _api.post('/diets/${diet.id}/finish', {
      if (destination != null) 'transfer': {'destination': destination, 'meals': meals},
    });
    await refresh();
    return '${data['message'] ?? 'Dieta finalizada por alta.'}';
  }
}
