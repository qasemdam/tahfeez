import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'network/api_client.dart';
import 'storage/session_store.dart';

final Provider<ApiClient> apiClientProvider = Provider<ApiClient>((Ref ref) {
  final ApiClient client = ApiClient();
  ref.onDispose(client.close);
  return client;
});

final Provider<SessionStore> sessionStoreProvider = Provider<SessionStore>(
  (Ref ref) => SessionStore(),
);
