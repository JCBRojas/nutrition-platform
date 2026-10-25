import 'package:clock/clock.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../auth/session.dart';
import '../core/api_client.dart';
import '../core/api_exception.dart';
import '../live/live_feed.dart';

DateTime? _date(Object? value) => value == null ? null : DateTime.tryParse('$value');

/// Despacho del día (GET /api/v1/dispatches/day)
class LogEntry {
  LogEntry({required this.id, required this.habitation, required this.patient, required this.meal, this.dispatchedAt, this.dispatchedBy, this.receivedAt, this.receivedBy});

  final int id;
  final String habitation;
  final String patient;
  final String meal;
  final DateTime? dispatchedAt;
  final String? dispatchedBy;
  final DateTime? receivedAt;
  final String? receivedBy;

  factory LogEntry.fromJson(Map<String, dynamic> json) => LogEntry(
    id: json['id'] as int,
    habitation: '${json['habitation'] ?? ''}',
    patient: '${json['name_patient'] ?? ''}',
    meal: '${json['meal']}',
    dispatchedAt: _date(json['dispatched_at']),
    dispatchedBy: json['dispatched_by']?.toString(),
    receivedAt: _date(json['received_at']),
    receivedBy: json['received_by']?.toString(),
  );
}

/// Resumen de un tiempo: despachados, recibidos y programados (solo hoy)
class MealSummary {
  MealSummary(this.meal, this.label, this.dispatched, this.received, this.planned);

  final String meal;
  final String label;
  final int dispatched;
  final int received;
  final int? planned;
}

/// Despacho anulado o que no llegó
class VoidEntry {
  VoidEntry({required this.id, required this.habitation, required this.meal, required this.label, this.reason, this.voidedAt, this.voidedBy});

  final int id;
  final String habitation;
  final String meal;
  final String label;
  final String? reason;
  final DateTime? voidedAt;
  final String? voidedBy;

  factory VoidEntry.fromJson(Map<String, dynamic> json) => VoidEntry(
    id: json['id'] as int,
    habitation: '${json['habitation'] ?? ''}',
    meal: '${json['meal']}',
    label: '${json['label'] ?? json['kind']}',
    reason: json['reason']?.toString(),
    voidedAt: _date(json['voided_at']),
    voidedBy: json['voided_by']?.toString(),
  );
}

class DispatchLog {
  DispatchLog({required this.entries, required this.page, required this.lastPage, required this.perPage, required this.total, required this.summary, required this.voids});

  final List<LogEntry> entries;
  final int page;
  final int lastPage;
  final int perPage;
  final int total;
  final List<MealSummary> summary;
  final List<VoidEntry> voids;

  factory DispatchLog.fromJson(Map<String, dynamic> json) {
    final meta = json['meta'] as Map? ?? const {};
    final summary = json['summary'] is Map ? json['summary'] as Map : const {};
    return DispatchLog(
      entries: (json['data'] as List? ?? const []).map((r) => LogEntry.fromJson(Map<String, dynamic>.from(r as Map))).toList(),
      page: meta['current_page'] as int? ?? 1,
      lastPage: meta['last_page'] as int? ?? 1,
      perPage: meta['per_page'] as int? ?? 15,
      total: meta['total'] as int? ?? 0,
      summary: [
        for (final e in summary.entries)
          if (e.value is Map)
            MealSummary('${e.key}', '${(e.value as Map)['label']}', (e.value as Map)['dispatched'] as int? ?? 0, (e.value as Map)['received'] as int? ?? 0, (e.value as Map)['planned'] as int?),
      ],
      voids: (json['voids'] as List? ?? const []).map((v) => VoidEntry.fromJson(Map<String, dynamic>.from(v as Map))).toList(),
    );
  }
}

class DispatchLogState {
  const DispatchLogState({this.log, this.loading = true, this.error, required this.date, this.meal = '', this.status = '', this.query = '', this.page = 1, this.perPage = 15});

  final DispatchLog? log;
  final bool loading;
  final String? error;
  final DateTime date;

  /// '' = todos los servicios
  final String meal;

  /// '' | pending | received
  final String status;
  final String query;
  final int page;
  final int perPage;

  static const _keep = Object();

  DispatchLogState copyWith({DispatchLog? log, bool? loading, Object? error = _keep, DateTime? date, String? meal, String? status, String? query, int? page, int? perPage}) => DispatchLogState(
    log: log ?? this.log,
    loading: loading ?? this.loading,
    error: identical(error, _keep) ? this.error : error as String?,
    date: date ?? this.date,
    meal: meal ?? this.meal,
    status: status ?? this.status,
    query: query ?? this.query,
    page: page ?? this.page,
    perPage: perPage ?? this.perPage,
  );

  String get dateParam => '${date.year}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';

  Map<String, dynamic> get params => {
    'fecha': dateParam,
    if (meal.isNotEmpty) 'servicio': meal,
    if (status.isNotEmpty) 'estado': status,
    if (query.isNotEmpty) 'q': query,
    'page': page,
    'per_page': perPage,
  };
}

/// «Despachos del día» (App\Livewire\DispatchLog, Premium): se refresca con los avisos de
/// despachos (sin WebSocket, cada 5 s) mientras la pantalla está abierta.
final dispatchLogProvider = NotifierProvider.autoDispose<DispatchLogController, DispatchLogState>(DispatchLogController.new);

class DispatchLogController extends Notifier<DispatchLogState> {
  static const fallbackSeconds = 5;

  bool _loading = false;
  bool _again = false;

  ApiClient get _api => ref.read(sessionProvider.notifier).client;

  @override
  DispatchLogState build() {
    final feed = ref.watch(liveFeedProvider);
    final ticker = LiveTicker(
      feed,
      kinds: const {'dispatch'},
      fallback: const Duration(seconds: fallbackSeconds),
      onTick: refresh,
      safety: const Duration(seconds: 10),
    );
    ref.onDispose(ticker.dispose);
    Future.microtask(refresh);
    final now = clock.now();
    return DispatchLogState(date: DateTime(now.year, now.month, now.day));
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
        final data = await _api.get('/dispatches/day', query: params);
        if (!ref.mounted) return;
        if (state.params.toString() != params.toString()) {
          _again = true;
          continue;
        }
        state = state.copyWith(log: DispatchLog.fromJson(data), loading: false, error: null);
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

  void _filter(DispatchLogState next) {
    state = next.copyWith(loading: true);
    refresh();
  }

  void setDate(DateTime date) => _filter(state.copyWith(date: DateTime(date.year, date.month, date.day), page: 1));
  void setMeal(String meal) => _filter(state.copyWith(meal: meal, page: 1));
  void setStatus(String status) => _filter(state.copyWith(status: status, page: 1));
  void setQuery(String query) => _filter(state.copyWith(query: query.trim(), page: 1));
  void setPage(int page) => _filter(state.copyWith(page: page));
  void setPerPage(int perPage) => _filter(state.copyWith(perPage: perPage, page: 1));
}
