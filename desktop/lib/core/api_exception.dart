import 'package:dio/dio.dart';

/// Error de la API v1 con el mensaje en español que envía Laravel (`message`), el código
/// propio (`code`, p. ej. `privacy_required`, `company_forbidden`) y los errores por campo (422).
class ApiException implements Exception {
  ApiException(this.message, {this.status, this.code, this.url, this.fieldErrors = const {}});

  final String message;
  final int? status;
  final String? code;
  final String? url;
  final Map<String, List<String>> fieldErrors;

  bool get isUnauthorized => status == 401;

  /// Primer error de un campo, o null
  String? field(String name) => fieldErrors[name]?.first;

  factory ApiException.fromDio(DioException e) {
    final response = e.response;
    if (response == null) {
      return ApiException('No hay conexión con el servidor. Revisa la red o la dirección del servidor.');
    }

    final data = response.data;
    final status = response.statusCode;
    if (data is Map) {
      final errors = <String, List<String>>{};
      final raw = data['errors'];
      if (raw is Map) {
        raw.forEach((key, value) {
          if (value is List) errors['$key'] = value.map((m) => '$m').toList();
        });
      }
      final message = errors.isNotEmpty ? errors.values.first.first : (data['message']?.toString() ?? _fallback(status));

      return ApiException(message, status: status, code: data['code']?.toString(), url: data['url']?.toString(), fieldErrors: errors);
    }

    return ApiException(_fallback(status), status: status);
  }

  static String _fallback(int? status) => switch (status) {
        401 => 'Tu sesión venció. Inicia sesión de nuevo.',
        402 => 'Esta función está disponible en el plan Premium.',
        403 => 'No tienes permiso para esta acción.',
        404 => 'No se encontró el registro.',
        429 => 'Demasiados intentos. Espera un momento.',
        _ => 'Ocurrió un error en el servidor. Inténtalo de nuevo.',
      };

  @override
  String toString() => message;
}
