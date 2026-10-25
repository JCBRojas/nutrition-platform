import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Lo que la app guarda en el equipo: la dirección del servidor, la empresa elegida y si las
/// alertas están activadas (preferencias) y el token de la API (almacenamiento seguro: Llavero en macOS,
/// Administrador de credenciales en Windows). Nunca la contraseña.
abstract class AppStorage {
  Future<String?> serverUrl();
  Future<void> setServerUrl(String url);
  Future<String?> token();
  Future<void> setToken(String? token);
  Future<int?> companyId();
  Future<void> setCompanyId(int? id);
  Future<bool> alertsEnabled();
  Future<void> setAlertsEnabled(bool enabled);
}

class DeviceStorage implements AppStorage {
  DeviceStorage({FlutterSecureStorage? secure})
      // Llavero clásico en macOS: funciona aunque la app no esté firmada con un equipo de desarrollo
      : _secure = secure ?? const FlutterSecureStorage(mOptions: MacOsOptions(usesDataProtectionKeychain: false));

  final FlutterSecureStorage _secure;

  static const _serverKey = 'server_url';
  static const _companyKey = 'company_id';
  static const _tokenKey = 'api_token';
  static const _alertsKey = 'alerts_enabled';

  @override
  Future<String?> serverUrl() async => (await SharedPreferences.getInstance()).getString(_serverKey);

  @override
  Future<void> setServerUrl(String url) async => (await SharedPreferences.getInstance()).setString(_serverKey, url);

  @override
  Future<int?> companyId() async => (await SharedPreferences.getInstance()).getInt(_companyKey);

  @override
  Future<void> setCompanyId(int? id) async {
    final prefs = await SharedPreferences.getInstance();
    id == null ? await prefs.remove(_companyKey) : await prefs.setInt(_companyKey, id);
  }

  @override
  Future<bool> alertsEnabled() async => (await SharedPreferences.getInstance()).getBool(_alertsKey) ?? true;

  @override
  Future<void> setAlertsEnabled(bool enabled) async => (await SharedPreferences.getInstance()).setBool(_alertsKey, enabled);

  @override
  Future<String?> token() => _secure.read(key: _tokenKey);

  @override
  Future<void> setToken(String? token) =>
      token == null ? _secure.delete(key: _tokenKey) : _secure.write(key: _tokenKey, value: token);
}

/// Almacenamiento en memoria para las pruebas
class MemoryStorage implements AppStorage {
  MemoryStorage({this.server, this.savedToken, this.company, this.alerts = true});

  String? server;
  String? savedToken;
  int? company;
  bool alerts;

  @override
  Future<String?> serverUrl() async => server;
  @override
  Future<void> setServerUrl(String url) async => server = url;
  @override
  Future<String?> token() async => savedToken;
  @override
  Future<void> setToken(String? token) async => savedToken = token;
  @override
  Future<int?> companyId() async => company;
  @override
  Future<void> setCompanyId(int? id) async => company = id;
  @override
  Future<bool> alertsEnabled() async => alerts;
  @override
  Future<void> setAlertsEnabled(bool enabled) async => alerts = enabled;
}
