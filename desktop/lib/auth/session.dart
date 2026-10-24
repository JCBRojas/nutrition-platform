import 'dart:io';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/api_client.dart';
import '../core/api_exception.dart';
import '../core/storage.dart';
import '../core/version.dart';
import 'models.dart';

/// Estado de la sesión de la app
sealed class SessionState {
  const SessionState();
}

class SessionLoading extends SessionState {
  const SessionLoading();
}

/// Primera vez (o se cambió): falta la dirección del servidor
class NeedsServer extends SessionState {
  const NeedsServer({this.error, this.current});
  final String? error;
  final String? current;
}

/// La app es más vieja que la versión mínima que exige el servidor
class UpdateRequired extends SessionState {
  const UpdateRequired(this.minVersion, this.current);
  final String minVersion;
  final String current;
}

class LoggedOut extends SessionState {
  const LoggedOut({this.error, this.notice});
  final String? error;
  final String? notice;
}

class NeedsTwoFactor extends SessionState {
  const NeedsTwoFactor(this.ticket, {this.error});
  final String ticket;
  final String? error;
}

class LoggedIn extends SessionState {
  const LoggedIn(this.me);
  final Me me;
}

final storageProvider = Provider<AppStorage>((ref) => DeviceStorage());

/// Versión instalada (se reemplaza al arrancar con la de package_info_plus)
final appVersionProvider = Provider<String>((ref) => '0.0.0');

/// Nombre del equipo para el token (aparece en los tokens del usuario)
final deviceNameProvider = Provider<String>((ref) {
  try {
    return 'NutriTrack · ${Platform.localHostname}';
  } catch (_) {
    return 'NutriTrack escritorio';
  }
});

/// Fábrica del cliente (las pruebas la reemplazan por uno con respuestas simuladas)
final apiClientFactoryProvider = Provider<ApiClient Function(String server)>((ref) => (server) => ApiClient(server));

final sessionProvider = NotifierProvider<SessionController, SessionState>(SessionController.new);

class SessionController extends Notifier<SessionState> {
  ApiClient? _client;
  Meta? _meta;

  /// Cliente con el token y la empresa de la sesión (las pantallas lo usan con ref.read)
  ApiClient get client => _client!;
  Meta? get meta => _meta;

  AppStorage get _storage => ref.read(storageProvider);

  @override
  SessionState build() {
    Future.microtask(start);
    return const SessionLoading();
  }

  /// Al abrir la app: servidor → versión mínima → token guardado → usuario
  Future<void> start() async {
    state = const SessionLoading();
    final server = await _storage.serverUrl();
    if (server == null || server.isEmpty) {
      state = const NeedsServer();
      return;
    }

    try {
      await _connect(server);
    } on ApiException catch (e) {
      state = NeedsServer(error: e.message, current: server);
      return;
    }
    if (state is UpdateRequired) return;

    final token = await _storage.token();
    if (token == null) {
      state = const LoggedOut();
      return;
    }
    client.token = token;
    client.companyId = await _storage.companyId();
    await _loadMe();
  }

  /// Pantalla «Servidor»: valida la dirección consultando /meta y la guarda
  Future<void> configureServer(String url) async {
    final server = ApiClient.normalizeServer(url);
    state = const SessionLoading();
    try {
      await _connect(server);
    } on ApiException catch (e) {
      state = NeedsServer(error: e.message, current: url);
      return;
    }
    await _storage.setServerUrl(server);
    await _storage.setToken(null);
    await _storage.setCompanyId(null);
    if (state is! UpdateRequired) state = const LoggedOut();
  }

  Future<void> changeServer() async {
    state = NeedsServer(current: await _storage.serverUrl());
  }

  Future<void> login(String email, String password) async {
    try {
      final data = await client.post('/auth/login', {
        'email': email.trim(),
        'password': password,
        'device_name': ref.read(deviceNameProvider),
      });
      if (data['two_factor_required'] == true) {
        state = NeedsTwoFactor(data['ticket'].toString());
        return;
      }
      await _signedIn(data['token'].toString());
    } on ApiException catch (e) {
      state = LoggedOut(error: e.message);
    }
  }

  Future<void> verifyTwoFactor({String? code, String? recoveryCode}) async {
    final current = state;
    if (current is! NeedsTwoFactor) return;
    try {
      final data = await client.post('/auth/two-factor', {
        'ticket': current.ticket,
        if (code != null) 'code': code.trim(),
        if (recoveryCode != null) 'recovery_code': recoveryCode.trim(),
        'device_name': ref.read(deviceNameProvider),
      });
      await _signedIn(data['token'].toString());
    } on ApiException catch (e) {
      // Ticket vencido: volver a iniciar sesión
      state = e.message.contains('Inicia sesión de nuevo') ? LoggedOut(error: e.message) : NeedsTwoFactor(current.ticket, error: e.message);
    }
  }

  void cancelTwoFactor() => state = const LoggedOut();

  /// Selector de empresa: queda para las próximas peticiones (X-Company-Id)
  Future<void> selectCompany(int companyId) async {
    await _storage.setCompanyId(companyId);
    client.companyId = companyId;
    await _loadMe();
  }

  Future<void> refreshMe() => _loadMe();

  Future<void> logout() async {
    try {
      await client.post('/auth/logout');
    } on ApiException {
      // El token puede haber vencido: igual se cierra la sesión local
    }
    await _clearSession();
    state = const LoggedOut(notice: 'Sesión cerrada.');
  }

  /// Cualquier pantalla que reciba 401 llama aquí: token vencido o revocado
  Future<void> expired() async {
    await _clearSession();
    state = const LoggedOut(error: 'Tu sesión venció. Inicia sesión de nuevo.');
  }

  Future<void> _connect(String server) async {
    _client = ref.read(apiClientFactoryProvider)(server);
    _meta = Meta.fromJson(await client.get('/meta'));
    final version = ref.read(appVersionProvider);
    if (compareVersions(version, _meta!.minVersion) < 0) {
      state = UpdateRequired(_meta!.minVersion, version);
    }
  }

  Future<void> _signedIn(String token) async {
    await _storage.setToken(token);
    client.token = token;
    client.companyId = await _storage.companyId();
    await _loadMe();
  }

  Future<void> _loadMe() async {
    try {
      var me = Me.fromJson(await client.get('/me'));
      // La empresa guardada ya no es elegible: se usa la que indica el servidor
      if (client.companyId != null && !me.companies.any((c) => c.id == client.companyId)) {
        client.companyId = null;
        await _storage.setCompanyId(null);
        me = Me.fromJson(await client.get('/me'));
      }
      client.companyId ??= me.currentCompanyId;
      state = LoggedIn(me);
    } on ApiException catch (e) {
      if (e.isUnauthorized || e.code == 'company_forbidden') {
        await _clearSession();
        state = LoggedOut(error: e.isUnauthorized ? 'Tu sesión venció. Inicia sesión de nuevo.' : e.message);
      } else {
        state = LoggedOut(error: e.message);
      }
    }
  }

  Future<void> _clearSession() async {
    await _storage.setToken(null);
    _client?.token = null;
  }
}
