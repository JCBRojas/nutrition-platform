// Tablero del despachador (GET /api/v1/dispatch/board), como App\Services\DispatchBoard

List<String> _strings(Object? value) => (value as List? ?? const []).map((e) => '$e').toList();

DateTime? _date(Object? value) => value == null ? null : DateTime.tryParse('$value');

/// Diferencia de un campo respecto de la última versión confirmada: listas con agregados y
/// quitados; campos simples con el valor anterior y el nuevo
class FieldChange {
  FieldChange({this.added = const [], this.removed = const [], this.oldValue, this.newValue});

  final List<String> added;
  final List<String> removed;
  final String? oldValue;
  final String? newValue;

  factory FieldChange.fromJson(Map<String, dynamic> json) => FieldChange(
        added: _strings(json['added']),
        removed: _strings(json['removed']),
        oldValue: json['old']?.toString(),
        newValue: json['new']?.toString(),
      );
}

class DispatchRecord {
  DispatchRecord({required this.id, this.dispatchedAt, this.dispatchedBy, this.receivedAt});

  final int id;
  final DateTime? dispatchedAt;
  final String? dispatchedBy;
  final DateTime? receivedAt;

  bool get received => receivedAt != null;
}

class BoardDiet {
  BoardDiet({
    required this.id,
    required this.habitation,
    required this.patient,
    required this.status,
    required this.isActive,
    required this.timeFood,
    required this.consistency,
    required this.specifications,
    this.observations,
    this.isolation,
    required this.hasPending,
    required this.changes,
    this.lastChange,
    required this.transferMeals,
    this.transferDestination,
    this.blockReason,
    this.dispatch,
    this.missingReason,
    this.missing = false,
    required this.delivery,
  });

  final int id;
  final String habitation;
  final String patient;
  final int status;
  final bool isActive;
  final List<String> timeFood;
  final List<String> consistency;
  final List<String> specifications;
  final String? observations;
  final String? isolation;
  final bool hasPending;

  /// Campo → diferencia (timeFood, consistency, specifications, observations, isolation)
  final Map<String, FieldChange> changes;
  final String? lastChange;
  final List<String> transferMeals;
  final String? transferDestination;
  final String? blockReason;
  final DispatchRecord? dispatch;

  /// Nutrición reportó que no llegó: hay que volver a despacharla
  final bool missing;
  final String? missingReason;

  /// Tiempo → 'received' | 'dispatched' en el día
  final Map<String, String> delivery;

  static const cancelled = 0;
  static const finished = 2;

  String get closedLabel => status == cancelled ? 'Cancelada' : (status == finished ? 'Finalizada' : 'Activa');

  factory BoardDiet.fromJson(Map<String, dynamic> json) {
    final rawChanges = json['pending_changes'];
    final dispatch = json['dispatch'] as Map?;
    final missing = json['missing'] as Map?;
    final delivery = json['delivery'];

    return BoardDiet(
      id: json['id'] as int,
      habitation: '${json['habitation']}',
      patient: '${json['name_patient'] ?? ''}',
      status: json['status'] is int ? json['status'] as int : int.tryParse('${json['status']}') ?? 1,
      isActive: json['is_active'] == true,
      timeFood: _strings(json['time_food']),
      consistency: _strings(json['consistency']),
      specifications: _strings(json['specifications']),
      observations: json['observations']?.toString(),
      isolation: json['isolation']?.toString(),
      hasPending: json['has_pending'] == true,
      changes: rawChanges is Map
          ? rawChanges.map((k, v) => MapEntry('$k', FieldChange.fromJson(Map<String, dynamic>.from(v as Map))))
          : const {},
      lastChange: json['last_change']?.toString(),
      transferMeals: _strings(json['transfer_meals']),
      transferDestination: json['transfer_destination']?.toString(),
      blockReason: json['block_reason']?.toString(),
      dispatch: dispatch == null
          ? null
          : DispatchRecord(
              id: dispatch['id'] as int,
              dispatchedAt: _date(dispatch['dispatched_at']),
              dispatchedBy: dispatch['dispatched_by']?.toString(),
              receivedAt: _date(dispatch['received_at']),
            ),
      missing: missing != null,
      missingReason: missing?['reason']?.toString(),
      delivery: delivery is Map ? delivery.map((k, v) => MapEntry('$k', '$v')) : const {},
    );
  }
}

/// Cambio sin confirmar (alerta y contador): una clave por cada versión nueva
class PendingChange {
  PendingChange({required this.dietId, required this.key, required this.habitation, required this.patient, this.lastChange, required this.isActive});

  final int dietId;
  final String key;
  final String habitation;
  final String patient;
  final String? lastChange;
  final bool isActive;

  factory PendingChange.fromJson(Map<String, dynamic> json) => PendingChange(
        dietId: json['diet_id'] as int,
        key: '${json['key']}',
        habitation: '${json['habitation']}',
        patient: '${json['name_patient'] ?? ''}',
        lastChange: json['last_change']?.toString(),
        isActive: json['is_active'] == true,
      );
}

class Board {
  Board({
    required this.meal,
    required this.tab,
    required this.showAll,
    required this.diets,
    required this.dispatchable,
    required this.page,
    required this.lastPage,
    required this.perPage,
    required this.total,
    required this.pending,
    required this.counts,
  });

  final String meal;
  final String tab;
  final bool showAll;
  final List<BoardDiet> diets;
  final Set<int> dispatchable;
  final int page;
  final int lastPage;
  final int perPage;
  final int total;
  final List<PendingChange> pending;

  /// active, closed, pendingActive, pendingClosed, transfers, applies
  final Map<String, int?> counts;

  factory Board.fromJson(Map<String, dynamic> json) {
    final meta = Map<String, dynamic>.from(json['meta'] as Map? ?? const {});
    final counts = Map<String, dynamic>.from(json['counts'] as Map? ?? const {});

    return Board(
      meal: '${json['meal']}',
      tab: '${json['tab'] ?? 'active'}',
      showAll: json['show_all'] != false,
      diets: (json['data'] as List? ?? const []).map((d) => BoardDiet.fromJson(Map<String, dynamic>.from(d as Map))).toList(),
      dispatchable: (json['dispatchable'] as List? ?? const []).map((id) => id as int).toSet(),
      page: meta['current_page'] as int? ?? 1,
      lastPage: meta['last_page'] as int? ?? 1,
      perPage: meta['per_page'] as int? ?? 15,
      total: meta['total'] as int? ?? 0,
      pending: (json['pending'] as List? ?? const []).map((p) => PendingChange.fromJson(Map<String, dynamic>.from(p as Map))).toList(),
      counts: counts.map((k, v) => MapEntry(k, v is int ? v : null)),
    );
  }
}
