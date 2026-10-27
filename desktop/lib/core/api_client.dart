import 'dart:convert';

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

  /// Descarga un archivo (p. ej. el Excel del reporte): bytes y nombre de Content-Disposition
  Future<({List<int> bytes, String? filename})> download(String path, {Map<String, dynamic>? query}) async {
    try {
      final response = await _dio.get<List<int>>(path, queryParameters: query, options: Options(responseType: ResponseType.bytes));
      final disposition = response.headers.value('content-disposition') ?? '';
      final match = RegExp(r'filename="?([^";]+)"?').firstMatch(disposition);
      return (bytes: response.data ?? const <int>[], filename: match?.group(1));
    } on DioException catch (e) {
      // El error llega en bytes: se decodifica para leer el mensaje en español
      final data = e.response?.data;
      if (data is List<int>) {
        try {
          e.response!.data = jsonDecode(utf8.decode(data));
        } on FormatException {
          // sin cuerpo JSON: mensaje genérico según el estado
        }
      }
      throw ApiException.fromDio(e);
    }
  }

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
