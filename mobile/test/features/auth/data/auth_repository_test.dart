import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:tahfeez/core/network/api_client.dart';
import 'package:tahfeez/core/storage/session_store.dart';
import 'package:tahfeez/features/auth/data/auth_repository.dart';
import 'package:tahfeez/features/auth/domain/session.dart';

import '../../../helpers/fake_http_adapter.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  late ApiClient api;
  late SessionStore store;

  setUp(() {
    SharedPreferences.setMockInitialValues(<String, Object>{});
    api = ApiClient();
    store = SessionStore();
  });

  tearDown(() => api.close());

  test('login stores the token and persists the session', () async {
    api.dio.httpClientAdapter = FakeHttpAdapter((RequestOptions options) async {
      expect(options.path, '/auth/login');

      final Map<String, dynamic> body = options.data as Map<String, dynamic>;
      expect(body['email'], 'k@example.test');
      expect(body['password'], 'secret');
      expect(body['device_name'], 'flutter');

      return FakeHttpAdapter.json(<String, dynamic>{
        'data': <String, dynamic>{
          'token': 'plain-token',
          'expires_at': '2026-12-01T00:00:00+00:00',
          'user': <String, dynamic>{
            'id': 5,
            'name': 'خالد',
            'email': 'k@example.test',
            'role': 'center_manager',
            'center_id': 2,
            'center_name': 'مركز النور',
          },
        },
      });
    });

    final AuthRepository repository = AuthRepository(api, store);
    final Session session = await repository.login(
      email: 'k@example.test',
      password: 'secret',
    );

    expect(session.token, 'plain-token');
    expect(session.user.id, 5);
    expect(session.user.isManager, isTrue);

    // The client now authenticates subsequent calls with the token.
    expect(api.token, 'plain-token');

    final Session? persisted = await store.read();
    expect(persisted, isNotNull);
    expect(persisted!.token, 'plain-token');
    expect(persisted.user.name, 'خالد');
  });

  test('restore returns null when nothing is stored', () async {
    final AuthRepository repository = AuthRepository(api, store);

    expect(await repository.restore(), isNull);
    expect(api.token, isNull);
  });

  test('logout clears the token even when the network call fails', () async {
    api.token = 'plain-token';
    api.dio.httpClientAdapter = FakeHttpAdapter((RequestOptions options) async {
      throw DioException(
        requestOptions: options,
        type: DioExceptionType.connectionError,
      );
    });

    final AuthRepository repository = AuthRepository(api, store);
    await repository.logout();

    expect(api.token, isNull);
    expect(await store.read(), isNull);
  });
}
