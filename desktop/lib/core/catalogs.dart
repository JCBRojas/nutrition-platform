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
  Catalogs(this.meals, this.consistency, this.specifications);

  final List<Meal> meals;
  final Map<String, String> consistency;
  final Map<String, String> specifications;

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

  /// Respaldo legible para claves fuera del catálogo (como DietReportService::label)
  static String _readable(String key) {
    final text = key.replaceAll('_', ' ').trim();
    return text.isEmpty ? key : text[0].toUpperCase() + text.substring(1);
  }
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
