import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/providers.dart';
import '../domain/memorization.dart';

class MemorizationRepository {
  MemorizationRepository(this._api);

  final ApiClient _api;

  Future<List<Memorization>> list({
    int? studentId,
    int? groupId,
    String? date,
  }) async {
    final response = await _api.dio.get<Map<String, dynamic>>(
      '/memorization',
      queryParameters: <String, dynamic>{
        if (studentId != null) 'student_id': studentId,
        if (groupId != null) 'group_id': groupId,
        if (date != null) 'date': date,
      },
    );

    final List<dynamic> rows = response.data!['data'] as List<dynamic>;

    return rows
        .map(
          (dynamic row) => Memorization.fromJson(row as Map<String, dynamic>),
        )
        .toList(growable: false);
  }

  Future<Memorization> create(MemorizationSubmission submission) async {
    final response = await _api.dio.post<Map<String, dynamic>>(
      '/memorization',
      data: submission.toJson(),
    );

    return Memorization.fromJson(
      response.data!['data'] as Map<String, dynamic>,
    );
  }

  Future<Memorization> update(
    int id, {
    String? amountPages,
    RecitationGrade? grade,
    int? hifzErrors,
    int? tajweedErrors,
    int? readingErrors,
    String? notes,
  }) async {
    final response = await _api.dio.patch<Map<String, dynamic>>(
      '/memorization/$id',
      data: <String, dynamic>{
        if (amountPages != null) 'amount_pages': amountPages,
        if (grade != null) 'grade': grade.wire,
        if (hifzErrors != null) 'hifz_errors': hifzErrors,
        if (tajweedErrors != null) 'tajweed_errors': tajweedErrors,
        if (readingErrors != null) 'reading_errors': readingErrors,
        if (notes != null) 'notes': notes,
      },
    );

    return Memorization.fromJson(
      response.data!['data'] as Map<String, dynamic>,
    );
  }

  Future<void> delete(int id) async {
    await _api.dio.delete<void>('/memorization/$id');
  }
}

final Provider<MemorizationRepository> memorizationRepositoryProvider =
    Provider<MemorizationRepository>((Ref ref) {
      return MemorizationRepository(ref.watch(apiClientProvider));
    });

final FutureProviderFamily<List<Memorization>, int>
studentMemorizationsProvider = FutureProvider.family<List<Memorization>, int>((
  Ref ref,
  int studentId,
) {
  return ref.watch(memorizationRepositoryProvider).list(studentId: studentId);
});
