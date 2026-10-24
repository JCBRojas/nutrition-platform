import 'package:dio/dio.dart';

import 'api_exception.dart';

/// Cliente de la API v1 de NutriTrack (`{servidor}/api/v1`). Agrega el token de Sanctum y
/// la empresa activa (`X-Company-Id`) a cada petición, y convierte los errores en
/// [ApiException] con el mensaje en español del servidor.
class ApiClient {
  ApiClient(String serverUrl, {Dio? dio}) : _dio = dio ?? Dio() {
    _dio.options
      ..baseUrl = '${normalizeServer(serverUrl)}/api/v1'
      ..connectTimeout = const Duration(seconds: 10)
      ..receiveTimeout = const Duration(seconds: 20)
      ..headers = {'Accept': 'application/json'};
    _dio.interceptors.add(InterceptorsWrapper(onRequest: (options, handler) {
      if (token != null) options.headers['Authorization'] = 'Bearer $token';
      if (companyId != null) options.headers['X-Company-Id'] = '$companyId';
      handler.next(options);
    }));
  }

  final Dio _dio;
  String? token;
  int? companyId;

  /// Quita espacios, la barra final y agrega https:// si no trae esquema
  static String normalizeServer(String url) {
    var value = url.trim();
    while (value.endsWith('/')) {
      value = value.substring(0, value.length - 1);
    }
    if (!value.startsWith('http://') && !value.startsWith('https://')) value = 'https://$value';
    return value;
  }

  Future<Map<String, dynamic>> get(String path, {Map<String, dynamic>? query}) =>
      _send(() => _dio.get<dynamic>(path, queryParameters: query));

  Future<Map<String, dynamic>> post(String path, [Map<String, dynamic>? body]) =>
      _send(() => _dio.post<dynamic>(path, data: body ?? const {}));

  Future<Map<String, dynamic>> put(String path, Map<String, dynamic> body) =>
      _send(() => _dio.put<dynamic>(path, data: body));

  Future<Map<String, dynamic>> _send(Future<Response<dynamic>> Function() request) async {
    try {
      final response = await request();
      final data = response.data;
      return data is Map<String, dynamic> ? data : <String, dynamic>{'data': data};
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }
}
