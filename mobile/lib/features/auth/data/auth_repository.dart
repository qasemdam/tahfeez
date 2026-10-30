import '../../../core/network/api_client.dart';
import '../../../core/storage/session_store.dart';
import '../domain/session.dart';

class AuthRepository {
  AuthRepository(this._api, this._store);

  final ApiClient _api;
  final SessionStore _store;

  Future<Session> login({
    required String email,
    required String password,
  }) async {
    final response = await _api.dio.post<Map<String, dynamic>>(
      '/auth/login',
      data: <String, dynamic>{
        'email': email,
        'password': password,
        'device_name': 'flutter',
      },
    );

    final Map<String, dynamic> payload =
        response.data!['data'] as Map<String, dynamic>;
    final Session session = Session.fromJson(payload);
    _api.token = session.token;
    await _store.save(session);
    return session;
  }

  Future<void> logout() async {
    try {
      await _api.dio.post<void>('/auth/logout');
    } catch (_) {
      // Offline logout must still clear local state.
    }
    _api.token = null;
    await _store.clear();
  }

  /// Restores a persisted session without hitting the network.
  Future<Session?> restore() async {
    final Session? session = await _store.read();
    if (session == null) {
      return null;
    }
    _api.token = session.token;
    return session;
  }
}
