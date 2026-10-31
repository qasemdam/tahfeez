import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/providers.dart';
import '../domain/teacher.dart';

class TeachersRepository {
  TeachersRepository(this._api);

  final ApiClient _api;

  Future<List<Teacher>> list() async {
    final response = await _api.dio.get<Map<String, dynamic>>('/teachers');
    final List<dynamic> rows = response.data!['data'] as List<dynamic>;

    return rows
        .map((dynamic row) => Teacher.fromJson(row as Map<String, dynamic>))
        .toList(growable: false);
  }
}

final Provider<TeachersRepository> teachersRepositoryProvider =
    Provider<TeachersRepository>((Ref ref) {
      return TeachersRepository(ref.watch(apiClientProvider));
    });

final FutureProvider<List<Teacher>> teachersProvider =
    FutureProvider<List<Teacher>>((Ref ref) {
      return ref.watch(teachersRepositoryProvider).list();
    });
