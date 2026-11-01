import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/providers.dart';
import '../domain/student.dart';

class StudentsRepository {
  StudentsRepository(this._api);

  final ApiClient _api;

  Future<List<Student>> list({String? search}) async {
    final response = await _api.dio.get<Map<String, dynamic>>(
      '/students',
      queryParameters: <String, dynamic>{
        if (search != null && search.isNotEmpty) 'search': search,
      },
    );

    final List<dynamic> rows = response.data!['data'] as List<dynamic>;
    return rows
        .map((dynamic row) => Student.fromJson(row as Map<String, dynamic>))
        .toList(growable: false);
  }
}

final Provider<StudentsRepository> studentsRepositoryProvider =
    Provider<StudentsRepository>((Ref ref) {
      return StudentsRepository(ref.watch(apiClientProvider));
    });

final FutureProvider<List<Student>> studentsProvider =
    FutureProvider<List<Student>>((Ref ref) async {
      return ref.watch(studentsRepositoryProvider).list();
    });
