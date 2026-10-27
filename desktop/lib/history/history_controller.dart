import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../auth/session.dart';
import '../core/api_client.dart';
import '../core/api_exception.dart';

DateTime? _date(Object? value) => value == null ? null : DateTime.tryParse('$value');

/// Dieta en la lista del historial (GET /api/v1/history/diets)
class HistoryDiet {
  HistoryDiet({required this.id, required this.habitation, required this.patient, required this.status, required this.isActive, required this.versions, this.updatedAt});

  final int id;
  final String habitation;
  final String patient;
  final int status;
  final bool isActive;
  final int versions;
  final DateTime? updatedAt;

  factory HistoryDiet.fromJson(Map<String, dynamic> json) => HistoryDiet(
        id: json['id'] as int,
        habitation: '${json['habitation']}',
        patient: '${json['name_patient'] ?? ''}',
        status: json['status'] as int? ?? 1,
        isActive: json['is_active'] == true,
        versions: json['versions'] as int? ?? 0,
        updatedAt: _date(json['updated_at']),
      );

  /// Estado legible (Diet::ACTIVE, CANCELLED, FINISHED, COMPLETED)
  String get statusLabel => switch (status) {
        0 => 'Cancelada',
        2 => 'Finalizada (alta)',
        3 => 'Completada hoy',
        _ => 'Activa',
      };
}

/// Elemento de un campo de lista: mantenido, agregado o quitado respecto de la versión anterior
typedef ListItem = ({String label, String state});

/// Campo simple: valor, valor anterior y si cambió
typedef ScalarField = ({String? value, String? old, bool changed});

/// Versión en la línea de tiempo (DietHistoryService::timeline)
class VersionEntry {
  VersionEntry({required this.version, required this.type, this.createdAt, this.updatedBy, required this.lists, required this.listChanged, required this.scalars});

  final int version;

  /// creacion | edicion | cancelacion | finalizacion | completada | continuacion
  final String type;
  final DateTime? createdAt;
  final String? updatedBy;
  final Map<String, List<ListItem>> lists;
  final Map<String, bool> listChanged;
  final Map<String, ScalarField> scalars;

  static const listFields = ['timeFood', 'consistency', 'specifications'];
  static const scalarFields = ['billing_type', 'observations', 'isolation'];

  bool get hasChanges => listChanged.values.any((c) => c) || scalars.values.any((s) => s.changed);

  factory VersionEntry.fromJson(Map<String, dynamic> json) {
    final fields = json['fields'] is Map ? json['fields'] as Map : const {};
    final lists = <String, List<ListItem>>{};
    final listChanged = <String, bool>{};
    final scalars = <String, ScalarField>{};
    for (final f in listFields) {
      final raw = fields[f] is Map ? fields[f] as Map : const {};
      lists[f] = [
        for (final item in (raw['items'] as List? ?? const []).whereType<Map>()) (label: '${item['label']}', state: '${item['state']}'),
      ];
      listChanged[f] = raw['changed'] == true;
    }
    for (final f in scalarFields) {
      final raw = fields[f] is Map ? fields[f] as Map : const {};
      scalars[f] = (value: raw['value']?.toString(), old: raw['old']?.toString(), changed: raw['changed'] == true);
    }

    return VersionEntry(
      version: json['version'] as int? ?? 0,
      type: '${json['type'] ?? 'edicion'}',
      createdAt: _date(json['created_at']),
      updatedBy: json['updated_by']?.toString(),
      lists: lists,
      listChanged: listChanged,
      scalars: scalars,
    );
  }
}

class HistoryState {
  const HistoryState({
    this.diets,
    this.page = 1,
    this.lastPage = 1,
    this.total = 0,
    this.loading = true,
    this.error,
    this.query = '',
    this.status = 'all',
    this.selected,
    this.versions,
    this.timelineError,
  });

  final List<HistoryDiet>? diets;
  final int page;
  final int lastPage;
  final int total;
  final bool loading;
  final String? error;
  final String query;

  /// all | active | cancelled | finished
  final String status;

  /// Dieta elegida (con los datos que envía el servidor junto a la línea de tiempo)
  final HistoryDiet? selected;

  /// null mientras carga la línea de tiempo de la dieta elegida
  final List<VersionEntry>? versions;
  final String? timelineError;

  static const _keep = Object();

  HistoryState copyWith({
    List<HistoryDiet>? diets,
    int? page,
    int? lastPage,
    int? total,
    bool? loading,
    Object? error = _keep,
    String? query,
    String? status,
    Object? selected = _keep,
    Object? versions = _keep,
    Object? timelineError = _keep,
  }) =>
      HistoryState(
        diets: diets ?? this.diets,
        page: page ?? this.page,
        lastPage: lastPage ?? this.lastPage,
        total: total ?? this.total,
        loading: loading ?? this.loading,
        error: identical(error, _keep) ? this.error : error as String?,
        query: query ?? this.query,
        status: status ?? this.status,
        selected: identical(selected, _keep) ? this.selected : selected as HistoryDiet?,
        versions: identical(versions, _keep) ? this.versions : versions as List<VersionEntry>?,
        timelineError: identical(timelineError, _keep) ? this.timelineError : timelineError as String?,
      );

  Map<String, dynamic> get params => {
        if (query.isNotEmpty) 'q': query,
        'estado': status,
        'page': page,
      };
}

/// Dieta que otra pantalla pidió abrir en el historial (p. ej. «Ver historial» en Dietas activas)
final historyDietRequestProvider = NotifierProvider<HistoryDietRequest, int?>(HistoryDietRequest.new);

class HistoryDietRequest extends Notifier<int?> {
  @override
  int? build() => null;

  void request(int? dietId) => state = dietId;
}

/// «Historial de cambios» (App\Livewire\DietHistoryViewer): lista de dietas con búsqueda y
/// estado, y la línea de tiempo de la elegida. Solo nutrición; sin tiempo real (botón actualizar).
final historyProvider = NotifierProvider.autoDispose<HistoryController, HistoryState>(HistoryController.new);

class HistoryController extends Notifier<HistoryState> {
  bool _loading = false;
  bool _again = false;

  /// Evita mostrar la línea de tiempo de una dieta anterior si se eligió otra mientras cargaba
  int _timelineRequest = 0;

  ApiClient get _api => ref.read(sessionProvider.notifier).client;

  @override
  HistoryState build() {
    ref.listen(historyDietRequestProvider, (_, id) {
      if (id != null) _takeRequest();
    });
    Future.microtask(() {
      refresh();
      _takeRequest();
    });
    return const HistoryState();
  }

  void _takeRequest() {
    final id = ref.read(historyDietRequestProvider);
    if (id == null) return;
    ref.read(historyDietRequestProvider.notifier).request(null);
    select(id);
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
        final data = await _api.get('/history/diets', query: params);
        if (!ref.mounted) return;
        if (state.params.toString() != params.toString()) {
          _again = true;
          continue;
        }
        final meta = data['meta'] as Map? ?? const {};
        state = state.copyWith(
          diets: (data['data'] as List? ?? const []).map((d) => HistoryDiet.fromJson(Map<String, dynamic>.from(d as Map))).toList(),
          page: meta['current_page'] as int? ?? 1,
          lastPage: meta['last_page'] as int? ?? 1,
          total: meta['total'] as int? ?? 0,
          loading: false,
          error: null,
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

  void _filter(HistoryState next) {
    state = next.copyWith(loading: true);
    refresh();
  }

  void setQuery(String query) => _filter(state.copyWith(query: query.trim(), page: 1));
  void setStatus(String status) => _filter(state.copyWith(status: status, page: 1));
  void setPage(int page) => _filter(state.copyWith(page: page));

  /// Abre la línea de tiempo de una dieta (queda en la auditoría del servidor)
  Future<void> select(int dietId) async {
    final request = ++_timelineRequest;
    final known = state.diets?.where((d) => d.id == dietId).firstOrNull;
    state = state.copyWith(selected: known ?? state.selected, versions: null, timelineError: null);
    try {
      final data = await _api.get('/diets/$dietId/history');
      if (!ref.mounted || request != _timelineRequest) return;
      final diet = Map<String, dynamic>.from(data['diet'] as Map? ?? const {});
      state = state.copyWith(
        selected: HistoryDiet.fromJson({...diet, 'versions': (data['versions'] as List? ?? const []).length}),
        versions: (data['versions'] as List? ?? const []).map((v) => VersionEntry.fromJson(Map<String, dynamic>.from(v as Map))).toList(),
      );
    } on ApiException catch (e) {
      if (!ref.mounted || request != _timelineRequest) return;
      if (e.isUnauthorized) {
        await ref.read(sessionProvider.notifier).expired();
        return;
      }
      state = state.copyWith(versions: const <VersionEntry>[], timelineError: e.message);
    }
  }
}
