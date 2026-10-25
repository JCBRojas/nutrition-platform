import 'dart:async';
import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:nutritrack_desktop/core/api_client.dart';
import 'package:nutritrack_desktop/live/alerts.dart';
import 'package:nutritrack_desktop/live/live_feed.dart';

/// Servidor simulado para las pruebas: responde por «MÉTODO /ruta» y registra las peticiones
class FakeServer implements HttpClientAdapter {
  /// Las pantallas de operación consultan al abrir: por defecto, sin datos
  FakeServer() {
    on('GET', '/dispatch/board', boardJson());
    on('GET', '/receipts/pending', {'data': []});
  }

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

Map<String, dynamic> meJson({List<String> permissions = const ['confirmDelivery'], String home = 'dispatch', List<Map<String, Object>>? companies, bool privacy = false, List<String> premium = const []}) => {
      'id': 7,
      'name': 'Mariana López',
      'email': 'mariana@nutritrack.com',
      'roles': ['despachador'],
      'permissions': permissions,
      'home': home,
      'companies': companies ?? [{'id': 1, 'name': 'Clínica Central', 'is_default': true}],
      'current_company_id': 1,
      'premium': premium,
      'compliance': {'privacy_required': privacy, 'two_factor_setup_required': false},
    };

Map<String, dynamic> boardJson({List<Map<String, dynamic>> diets = const [], List<int> dispatchable = const [], List<Map<String, dynamic>> pending = const [], String tab = 'active'}) => {
      'meal': 'almuerzo',
      'tab': tab,
      'show_all': true,
      'data': diets,
      'dispatchable': dispatchable,
      'meta': {'current_page': 1, 'last_page': 1, 'per_page': 15, 'total': diets.length},
      'pending': pending,
      'counts': {'active': diets.length, 'closed': 0, 'pendingActive': pending.length, 'pendingClosed': 0, 'transfers': 0, 'applies': null},
    };

/// Tiempo real simulado: las pruebas emiten los avisos a mano
class FakeLiveFeed implements LiveFeed {
  FakeLiveFeed({bool connected = true}) : connected = ValueNotifier(connected);

  final _events = StreamController<Set<String>>.broadcast();

  @override
  Stream<Set<String>> get events => _events.stream;

  @override
  final ValueNotifier<bool> connected;

  void push(Set<String> kinds) => _events.add(kinds);

  @override
  void dispose() {}
}

/// Registra las alertas en lugar de sonar y notificar
class RecordingAlerts implements Alerts {
  final sent = <(String, String)>[];

  @override
  Future<void> notify(String title, String body) async => sent.add((title, body));
}
