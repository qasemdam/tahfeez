import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/providers.dart';
import '../domain/weekly_evaluation.dart';

class WeeklyEvaluationRepository {
  WeeklyEvaluationRepository(this._api);

  final ApiClient _api;

  Future<List<WeeklyEvaluation>> list({
    int? studentId,
    int? groupId,
    String? weekKey,
    String? weekStartDate,
  }) async {
    final response = await _api.dio.get<Map<String, dynamic>>(
      '/weekly-evaluations',
      queryParameters: <String, dynamic>{
        if (studentId != null) 'student_id': studentId,
        if (groupId != null) 'group_id': groupId,
        if (weekKey != null) 'week_key': weekKey,
        if (weekStartDate != null) 'week_start_date': weekStartDate,
      },
    );

    final List<dynamic> rows = response.data!['data'] as List<dynamic>;

    return rows
        .map(
          (dynamic row) =>
              WeeklyEvaluation.fromJson(row as Map<String, dynamic>),
        )
        .toList(growable: false);
  }

  Future<WeeklyEvaluation> show(int id) async {
    final response = await _api.dio.get<Map<String, dynamic>>(
      '/weekly-evaluations/$id',
    );

    return WeeklyEvaluation.fromJson(
      response.data!['data'] as Map<String, dynamic>,
    );
  }

  Future<WeeklyEvaluation> create(WeeklyEvaluationSubmission submission) async {
    final response = await _api.dio.post<Map<String, dynamic>>(
      '/weekly-evaluations',
      data: submission.toJson(),
    );

    return WeeklyEvaluation.fromJson(
      response.data!['data'] as Map<String, dynamic>,
    );
  }

  /// Approves a stored evaluation. The computed values and snapshots are
  /// immutable, so this is the only mutation the API accepts.
  Future<WeeklyEvaluation> approve(int id) async {
    final response = await _api.dio.patch<Map<String, dynamic>>(
      '/weekly-evaluations/$id',
      data: <String, dynamic>{'status': 'approved'},
    );

    return WeeklyEvaluation.fromJson(
      response.data!['data'] as Map<String, dynamic>,
    );
  }
}

final Provider<WeeklyEvaluationRepository> weeklyEvaluationRepositoryProvider =
    Provider<WeeklyEvaluationRepository>((Ref ref) {
      return WeeklyEvaluationRepository(ref.watch(apiClientProvider));
    });

final FutureProviderFamily<List<WeeklyEvaluation>, int>
studentWeeklyEvaluationsProvider =
    FutureProvider.family<List<WeeklyEvaluation>, int>((
      Ref ref,
      int studentId,
    ) {
      return ref
          .watch(weeklyEvaluationRepositoryProvider)
          .list(studentId: studentId);
    });
