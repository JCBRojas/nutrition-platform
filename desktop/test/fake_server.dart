import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:nutritrack_desktop/core/api_client.dart';

/// Servidor simulado para las pruebas: responde por «MÉTODO /ruta» y registra las peticiones
class FakeServer implements HttpClientAdapter {
  final Map<String, (int, Object)> routes = {};
  final List<RequestOptions> requests = [];

  void on(String method, String path, Object body, {int status = 200}) => routes['$method $path'] = (status, body);

  ApiClient client(String server) => ApiClient(server, dio: Dio()..httpClientAdapter = this);

  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream, Future<void>? cancelFuture) async {
    requests.add(options);
    final path = options.uri.path.replaceFirst('/api/v1', '');
    final route = routes['${options.method} $path'];
    if (route == null) return ResponseBody.fromString('{"message":"No encontrado"}', 404, headers: _json);

    return ResponseBody.fromString(jsonEncode(route.$2), route.$1, headers: _json);
  }

  static const _json = {Headers.contentTypeHeader: [Headers.jsonContentType]};

  @override
  void close({bool force = false}) {}
}

const metaJson = {
  'app': 'NutriTrack',
  'min_desktop_version': '1.0.0',
  'web_url': 'https://nutritrack.test',
  'catalogs': {'meals': []},
  'realtime': {'enabled': false},
};

Map<String, dynamic> meJson({List<String> permissions = const ['confirmDelivery'], String home = 'dispatch', List<Map<String, Object>>? companies, bool privacy = false}) => {
      'id': 7,
      'name': 'Mariana López',
      'email': 'mariana@nutritrack.com',
      'roles': ['despachador'],
      'permissions': permissions,
      'home': home,
      'companies': companies ?? [{'id': 1, 'name': 'Clínica Central', 'is_default': true}],
      'current_company_id': 1,
      'premium': [],
      'compliance': {'privacy_required': privacy, 'two_factor_setup_required': false},
    };
