import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/providers.dart';
import '../domain/weekly_report.dart';

/// Read-only access to `GET /reports/weekly`.
///
/// Either [weekKey] (a reference date the server resolves) or [weekStartDate]
/// (a boundary used verbatim) must be supplied; the client never computes a
/// period itself.
class ReportsRepository {
  ReportsRepository(this._api);

  final ApiClient _api;

  Future<WeeklyReport> weekly({
    String? weekKey,
    String? weekStartDate,
    int? groupId,
    int? studentId,
  }) async {
    final response = await _api.dio.get<Map<String, dynamic>>(
      '/reports/weekly',
      queryParameters: <String, dynamic>{
        if (weekKey != null) 'week_key': weekKey,
        if (weekStartDate != null) 'week_start_date': weekStartDate,
        if (groupId != null) 'group_id': groupId,
        if (studentId != null) 'student_id': studentId,
      },
    );

    return WeeklyReport.fromJson(
      response.data!['data'] as Map<String, dynamic>,
    );
  }
}

final Provider<ReportsRepository> reportsRepositoryProvider =
    Provider<ReportsRepository>((Ref ref) {
      return ReportsRepository(ref.watch(apiClientProvider));
    });
