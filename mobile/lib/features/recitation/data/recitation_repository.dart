import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/domain/assessment.dart';
import '../../../core/network/api_client.dart';
import '../../../core/providers.dart';
import '../domain/recitation.dart';

class RecitationRepository {
  RecitationRepository(this._api);

  final ApiClient _api;

  Future<List<Recitation>> list({
    int? studentId,
    int? groupId,
    String? date,
  }) async {
    final response = await _api.dio.get<Map<String, dynamic>>(
      '/recitations',
      queryParameters: <String, dynamic>{
        if (studentId != null) 'student_id': studentId,
        if (groupId != null) 'group_id': groupId,
        if (date != null) 'date': date,
      },
    );

    final List<dynamic> rows = response.data!['data'] as List<dynamic>;

    return rows
        .map((dynamic row) => Recitation.fromJson(row as Map<String, dynamic>))
        .toList(growable: false);
  }

  Future<Recitation> create(RecitationSubmission submission) async {
    final response = await _api.dio.post<Map<String, dynamic>>(
      '/recitations',
      data: submission.toJson(),
    );

    return Recitation.fromJson(response.data!['data'] as Map<String, dynamic>);
  }

  Future<Recitation> update(
    int id, {
    RecitationGrade? grade,
    int? hifzErrors,
    int? tajweedErrors,
    int? readingErrors,
    String? notes,
  }) async {
    final response = await _api.dio.patch<Map<String, dynamic>>(
      '/recitations/$id',
      data: <String, dynamic>{
        if (grade != null) 'grade': grade.wire,
        if (hifzErrors != null) 'hifz_errors': hifzErrors,
        if (tajweedErrors != null) 'tajweed_errors': tajweedErrors,
        if (readingErrors != null) 'reading_errors': readingErrors,
        if (notes != null) 'notes': notes,
      },
    );

    return Recitation.fromJson(response.data!['data'] as Map<String, dynamic>);
  }

  Future<void> delete(int id) async {
    await _api.dio.delete<void>('/recitations/$id');
  }
}

final Provider<RecitationRepository> recitationRepositoryProvider =
    Provider<RecitationRepository>((Ref ref) {
      return RecitationRepository(ref.watch(apiClientProvider));
    });

final FutureProviderFamily<List<Recitation>, int> studentRecitationsProvider =
    FutureProvider.family<List<Recitation>, int>((Ref ref, int studentId) {
      return ref.watch(recitationRepositoryProvider).list(studentId: studentId);
    });
