import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/domain/assessment.dart';
import '../../../core/network/api_client.dart';
import '../../../core/providers.dart';
import '../domain/review.dart';

class ReviewRepository {
  ReviewRepository(this._api);

  final ApiClient _api;

  Future<List<Review>> list({
    int? studentId,
    int? groupId,
    String? date,
  }) async {
    final response = await _api.dio.get<Map<String, dynamic>>(
      '/reviews',
      queryParameters: <String, dynamic>{
        if (studentId != null) 'student_id': studentId,
        if (groupId != null) 'group_id': groupId,
        if (date != null) 'date': date,
      },
    );

    final List<dynamic> rows = response.data!['data'] as List<dynamic>;

    return rows
        .map((dynamic row) => Review.fromJson(row as Map<String, dynamic>))
        .toList(growable: false);
  }

  Future<Review> create(ReviewSubmission submission) async {
    final response = await _api.dio.post<Map<String, dynamic>>(
      '/reviews',
      data: submission.toJson(),
    );

    return Review.fromJson(response.data!['data'] as Map<String, dynamic>);
  }

  Future<Review> update(
    int id, {
    String? amountPages,
    RecitationGrade? grade,
    int? hifzErrors,
    int? tajweedErrors,
    int? readingErrors,
    String? notes,
  }) async {
    final response = await _api.dio.patch<Map<String, dynamic>>(
      '/reviews/$id',
      data: <String, dynamic>{
        if (amountPages != null) 'amount_pages': amountPages,
        if (grade != null) 'grade': grade.wire,
        if (hifzErrors != null) 'hifz_errors': hifzErrors,
        if (tajweedErrors != null) 'tajweed_errors': tajweedErrors,
        if (readingErrors != null) 'reading_errors': readingErrors,
        if (notes != null) 'notes': notes,
      },
    );

    return Review.fromJson(response.data!['data'] as Map<String, dynamic>);
  }

  Future<void> delete(int id) async {
    await _api.dio.delete<void>('/reviews/$id');
  }
}

final Provider<ReviewRepository> reviewRepositoryProvider =
    Provider<ReviewRepository>((Ref ref) {
      return ReviewRepository(ref.watch(apiClientProvider));
    });

final FutureProviderFamily<List<Review>, int> studentReviewsProvider =
    FutureProvider.family<List<Review>, int>((Ref ref, int studentId) {
      return ref.watch(reviewRepositoryProvider).list(studentId: studentId);
    });
