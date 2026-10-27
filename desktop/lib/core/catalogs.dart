import '../auth/models.dart';

/// Tiempo de comida del catálogo (config/timeFood.php), en orden cronológico
class Meal {
  const Meal(this.key, this.label, this.time);

  final String key;
  final String label;

  /// Hora programada «HH:mm» (fraccionada no tiene)
  final String? time;
}

/// Catálogos de dietas que envía el servidor en /meta: las mismas claves y etiquetas que la web
class Catalogs {
  Catalogs(this.meals, this.consistency, this.specifications, {this.billingTypes = const {}, this.billingTypeRequired = false, this.habitationMax = 4, this.nameMax = 150});

  final List<Meal> meals;
  final Map<String, String> consistency;
  final Map<String, String> specifications;

  /// Tipos de dieta para facturación (solo se piden si el servidor no los deduce)
  final Map<String, String> billingTypes;
  final bool billingTypeRequired;
  final int habitationMax;
  final int nameMax;

  factory Catalogs.fromMeta(Meta? meta) {
    final raw = meta?.catalogs ?? const {};
    Map<String, String> labels(String key) =>
        (raw[key] is Map ? raw[key] as Map : const {}).map((k, v) => MapEntry('$k', '$v'));

    return Catalogs(
      (raw['meals'] as List? ?? const [])
          .whereType<Map>()
          .map((m) => Meal('${m['key']}', '${m['label']}', m['time']?.toString()))
          .toList(),
      labels('consistency'),
      labels('specifications'),
      billingTypes: labels('billing_types'),
      billingTypeRequired: meta?.dietForm['billing_type_required'] == true,
      habitationMax: meta?.dietForm['habitation_max'] as int? ?? 4,
      nameMax: meta?.dietForm['name_max'] as int? ?? 150,
    );
  }

  /// Claves guardadas de un mismo tiempo: 'media_manana' = 'mediamanana'
  static String normalizeMeal(String key) => key == 'media_manana' ? 'mediamanana' : key;

  String mealLabel(String key) {
    final normalized = normalizeMeal(key);
    return meals.where((m) => m.key == normalized).firstOrNull?.label ?? _readable(key);
  }

  String consistencyLabel(String key) => consistency[key] ?? _readable(key);

  String specificationLabel(String key) => specifications[key] ?? _readable(key);

  /// Columna «Cambios» del panel (mismas etiquetas que la web; las copias del día no muestran nada)
  static String changeLabel(String? key) => switch (key) {
        null || '' || 'NA' || 'completada' || 'continuada' => '—',
        'con_cambios' => 'Con cambios',
        'aislado' => 'Se aisló',
        'ya_no_aislado' => 'Ya no está aislado',
        'cancelada' => 'Cancelada',
        'finalizada' => 'Finalizada (alta)',
        _ => _readable(key),
      };

  /// Respaldo legible para claves fuera del catálogo (como DietReportService::label)
  static String _readable(String key) {
    final text = key.replaceAll('_', ' ').trim();
    return text.isEmpty ? key : text[0].toUpperCase() + text.substring(1);
  }
}

/// Número con separador de miles y coma decimal (es-CO): 1.234 o 12,5
String formatNumber(num value, {int decimals = 0}) {
  final fixed = value.toStringAsFixed(decimals);
  final parts = fixed.split('.');
  final digits = parts[0].replaceAll('-', '');
  final grouped = digits.replaceAllMapped(RegExp(r'\B(?=(\d{3})+(?!\d))'), (_) => '.');
  return '${value < 0 ? '-' : ''}$grouped${parts.length > 1 ? ',${parts[1]}' : ''}';
}

/// «10:32 a. m.» en hora local
String formatTime(DateTime? value) {
  if (value == null) return '—';
  final local = value.toLocal();
  final hour = local.hour % 12 == 0 ? 12 : local.hour % 12;
  final minute = local.minute.toString().padLeft(2, '0');
  return '$hour:$minute ${local.hour < 12 ? 'a. m.' : 'p. m.'}';
}

const _months = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

/// «24 oct · 10:32 a. m.» o solo la hora si es de hoy
String formatWhen(DateTime? value, {DateTime? now}) {
  if (value == null) return '—';
  final local = value.toLocal();
  final today = (now ?? DateTime.now()).toLocal();
  final sameDay = local.year == today.year && local.month == today.month && local.day == today.day;
  return sameDay ? formatTime(local) : '${local.day} ${_months[local.month - 1]} · ${formatTime(local)}';
}
