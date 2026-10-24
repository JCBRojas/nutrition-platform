import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nutritrack_desktop/auth/models.dart';
import 'package:nutritrack_desktop/core/api_client.dart';
import 'package:nutritrack_desktop/core/api_exception.dart';
import 'package:nutritrack_desktop/core/version.dart';

import 'fake_server.dart';

void main() {
  test('normaliza la dirección del servidor', () {
    expect(ApiClient.normalizeServer(' nutritrack.hospital.org/ '), 'https://nutritrack.hospital.org');
    expect(ApiClient.normalizeServer('http://nutrition-platform.test//'), 'http://nutrition-platform.test');
  });

  test('compara versiones', () {
    expect(compareVersions('1.0.0', '1.0.0'), 0);
    expect(compareVersions('1.0.0', '1.2.0') < 0, isTrue);
    expect(compareVersions('2.0.0+3', '1.9.9') > 0, isTrue);
  });

  test('el cliente envía el token y la empresa, y traduce los errores 422', () async {
    final server = FakeServer()
      ..on('POST', '/auth/login', {
        'message': 'Credenciales incorrectas.',
        'errors': {'email': ['Credenciales incorrectas.']},
      }, status: 422);
    final client = server.client('https://nutritrack.test')
      ..token = 'abc'
      ..companyId = 3;

    await expectLater(
      client.post('/auth/login', {'email': 'x'}),
      throwsA(isA<ApiException>()
          .having((e) => e.message, 'message', 'Credenciales incorrectas.')
          .having((e) => e.field('email'), 'field', 'Credenciales incorrectas.')
          .having((e) => e.status, 'status', 422)),
    );
    expect(server.requests.single.headers['Authorization'], 'Bearer abc');
    expect(server.requests.single.headers['X-Company-Id'], '3');
    expect(server.requests.single.uri.toString(), 'https://nutritrack.test/api/v1/auth/login');
  });

  test('sin respuesta del servidor: mensaje de conexión', () {
    final e = ApiException.fromDio(DioException(requestOptions: RequestOptions(path: '/meta')));
    expect(e.message, contains('No hay conexión'));
  });

  test('errores con código propio (403 privacy_required)', () {
    final e = ApiException.fromDio(DioException(
      requestOptions: RequestOptions(path: '/dispatch/board'),
      response: Response(requestOptions: RequestOptions(), statusCode: 403, data: {'message': 'Debes aceptar…', 'code': 'privacy_required', 'url': 'https://x/politica'}),
    ));
    expect(e.code, 'privacy_required');
    expect(e.url, 'https://x/politica');
  });

  test('Me lee permisos, empresas y cumplimiento', () {
    final me = Me.fromJson(meJson(privacy: true));
    expect(me.can('confirmDelivery'), isTrue);
    expect(me.can('editDiets'), isFalse);
    expect(me.currentCompany?.name, 'Clínica Central');
    expect(me.needsWebAction, isTrue);
  });
}
