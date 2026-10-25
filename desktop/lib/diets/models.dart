// Panel de dietas (GET /api/v1/diets), como App\Services\NutritionBoard y DietResource

List<String> _strings(Object? value) => (value as List? ?? const []).map((e) => '$e').toList();

Map<String, String> _labels(Object? value) => value is Map ? value.map((k, v) => MapEntry('$k', '$v')) : const {};

/// Estado del día de una dieta activa (DietDayService::statusByDiet)
class DayStatus {
  const DayStatus({this.completed = false, this.authorizedToday = false, this.authorizedTomorrow = false});

  final bool completed;
  final bool authorizedToday;
  final bool authorizedTomorrow;

  factory DayStatus.fromJson(Object? json) {
    final m = json is Map ? json : const {};
    return DayStatus(completed: m['completed'] == true, authorizedToday: m['authorizedToday'] == true, authorizedTomorrow: m['authorizedTomorrow'] == true);
  }
}

class Diet {
  Diet({
    required this.id,
    required this.habitation,
    required this.patient,
    required this.status,
    required this.isActive,
    this.version,
    required this.timeFood,
    required this.consistency,
    required this.specifications,
    this.observations,
    this.changes,
    this.billingType,
    this.isolation,
    this.updatedAt,
    this.updatedBy,
    this.transferDestination,
    this.transferMeals = const [],
    this.delivery = const {},
    this.day,
  });

  final int id;
  final String habitation;
  final String patient;
  final int status;
  final bool isActive;
  final int? version;
  final List<String> timeFood;
  final List<String> consistency;
  final List<String> specifications;
  final String? observations;
  final String? changes;
  final String? billingType;
  final String? isolation;
  final DateTime? updatedAt;
  final String? updatedBy;
  final String? transferDestination;
  final List<String> transferMeals;

  /// Tiempo de comida (clave normalizada) → 'dispatched' | 'received', solo hoy
  final Map<String, String> delivery;

  /// Solo en la pestaña de activas
  final DayStatus? day;

  bool get isolated => isolation == 'Sí' || isolation == 'Si';

  factory Diet.fromJson(Map<String, dynamic> json) {
    final transfer = json['transfer'] is Map ? json['transfer'] as Map : null;
    return Diet(
      id: json['id'] as int,
      habitation: '${json['habitation']}',
      patient: '${json['name_patient'] ?? ''}',
      status: json['status'] as int? ?? 1,
      isActive: json['is_active'] == true,
      version: json['version'] as int?,
      timeFood: _strings(json['timeFood']),
      consistency: _strings(json['consistency']),
      specifications: _strings(json['specifications']),
      observations: json['observations']?.toString(),
      changes: json['changes']?.toString(),
      billingType: json['billing_type']?.toString(),
      isolation: json['isolation']?.toString(),
      updatedAt: json['updated_at'] == null ? null : DateTime.tryParse('${json['updated_at']}'),
      updatedBy: json['updated_by']?.toString(),
      transferDestination: transfer?['destination']?.toString(),
      transferMeals: _strings(transfer?['meals']),
      delivery: _labels(json['delivery']),
      day: json['day'] == null ? null : DayStatus.fromJson(json['day']),
    );
  }
}

class DietsPage {
  DietsPage({
    required this.tab,
    required this.diets,
    required this.page,
    required this.lastPage,
    required this.perPage,
    required this.total,
    required this.counts,
    required this.isolated,
    required this.withChanges,
    required this.activeRooms,
  });

  final String tab;
  final List<Diet> diets;
  final int page;
  final int lastPage;
  final int perPage;
  final int total;

  /// active | cancelled | finished → total de la pestaña
  final Map<String, int> counts;
  final int isolated;
  final int withChanges;

  /// Habitaciones con dieta activa: aviso al registrar (SCRUM-17)
  final Set<String> activeRooms;

  factory DietsPage.fromJson(Map<String, dynamic> json) {
    final meta = json['meta'] as Map? ?? const {};
    final stats = json['stats'] as Map? ?? const {};
    final counts = json['counts'] as Map? ?? const {};
    return DietsPage(
      tab: '${json['tab'] ?? 'active'}',
      diets: (json['data'] as List? ?? const []).map((d) => Diet.fromJson(Map<String, dynamic>.from(d as Map))).toList(),
      page: meta['current_page'] as int? ?? 1,
      lastPage: meta['last_page'] as int? ?? 1,
      perPage: meta['per_page'] as int? ?? 15,
      total: meta['total'] as int? ?? 0,
      counts: counts.map((k, v) => MapEntry('$k', v as int? ?? 0)),
      isolated: stats['isolated'] as int? ?? 0,
      withChanges: stats['changes'] as int? ?? 0,
      activeRooms: _strings(json['active_rooms']).toSet(),
    );
  }
}

/// Despacho sin confirmar la recepción, en el resumen de cierre
class PendingDispatch {
  PendingDispatch(this.date, this.today, this.meal, this.at);

  final String date;
  final bool today;
  final String meal;
  final String at;
}

/// Lo que conviene saber antes de cancelar o dar de alta (DispatchService::closingSummary)
class ClosingSummary {
  ClosingSummary({required this.pending, required this.receivedToday, required this.candidates});

  final List<PendingDispatch> pending;
  final List<String> receivedToday;

  /// Tiempos de hoy aún sin despachar que se pueden enviar a otro lugar: clave → etiqueta
  final Map<String, String> candidates;

  factory ClosingSummary.fromJson(Map<String, dynamic> json) => ClosingSummary(
    pending: (json['pending'] as List? ?? const []).whereType<Map>().map((p) => PendingDispatch('${p['date']}', p['today'] == true, '${p['meal']}', '${p['at']}')).toList(),
    receivedToday: _strings(json['receivedToday']),
    // PHP envía [] cuando no hay candidatos
    candidates: _labels(json['candidates']),
  );
}

/// Datos de registrar o versionar una dieta (mismos campos que StoreDietRequest)
class DietInput {
  DietInput({
    this.habitation = '',
    this.patient = '',
    this.timeFood = const [],
    this.consistency = const [],
    this.specifications = const [],
    this.observations = '',
    this.isolation = 'No',
    this.billingType,
    this.changes,
    this.allowDuplicateRoom = false,
  });

  final String habitation;
  final String patient;
  final List<String> timeFood;
  final List<String> consistency;
  final List<String> specifications;
  final String observations;
  final String isolation;
  final String? billingType;
  final String? changes;
  final bool allowDuplicateRoom;

  Map<String, dynamic> toJson({required bool create}) => {
    if (create) ...{'habitation': habitation.trim(), 'name_patient': patient.trim()},
    'timeFood': timeFood,
    'consistency': consistency,
    'specifications': specifications,
    'observations': observations.trim().isEmpty ? null : observations.trim(),
    'isolation': isolation,
    if (billingType != null) 'billing_type': billingType,
    if (changes != null) 'changes': changes,
    if (create && allowDuplicateRoom) 'allow_duplicate_room': true,
  };
}
