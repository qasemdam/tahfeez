import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/providers.dart';
import '../data/auth_repository.dart';
import '../domain/session.dart';

final Provider<AuthRepository> authRepositoryProvider =
    Provider<AuthRepository>((Ref ref) {
      return AuthRepository(
        ref.watch(apiClientProvider),
        ref.watch(sessionStoreProvider),
      );
    });

final StateNotifierProvider<AuthController, AsyncValue<Session?>>
authControllerProvider =
    StateNotifierProvider<AuthController, AsyncValue<Session?>>((Ref ref) {
      return AuthController(ref.watch(authRepositoryProvider));
    });

class AuthController extends StateNotifier<AsyncValue<Session?>> {
  AuthController(this._repository)
    : super(const AsyncValue<Session?>.data(null));

  final AuthRepository _repository;

  Future<bool> login({required String email, required String password}) async {
    state = const AsyncValue<Session?>.loading();
    try {
      final Session session = await _repository.login(
        email: email,
        password: password,
      );
      state = AsyncValue<Session?>.data(session);
      return true;
    } catch (error, stackTrace) {
      state = AsyncValue<Session?>.error(error, stackTrace);
      return false;
    }
  }

  Future<void> restore() async {
    final Session? session = await _repository.restore();
    state = AsyncValue<Session?>.data(session);
  }

  Future<void> logout() async {
    await _repository.logout();
    state = const AsyncValue<Session?>.data(null);
  }

  String? get errorMessage {
    final AsyncValue<Session?> current = state;
    return current.hasError ? describeApiError(current.error!) : null;
  }
}
