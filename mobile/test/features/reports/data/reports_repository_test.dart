import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tahfeez/core/network/api_client.dart';
import 'package:tahfeez/features/reports/data/reports_repository.dart';
import 'package:tahfeez/features/reports/domain/weekly_report.dart';

import '../../../helpers/fake_http_adapter.dart';

Map<String, dynamic> reportJson() {
  return <String, dynamic>{
    'week': <String, dynamic>{
      'week_key': '2026-10-24',
      'start_date': '2026-10-24',
      'end_date': '2026-10-30',
      'start_day': 'saturday',
    },
    'students': <dynamic>[],
    'totals': <String, dynamic>{'students': 0},
  };
}

void main() {
  late ApiClient api;
  late ReportsRepository repository;

  setUp(() {
    api = ApiClient();
    repository = ReportsRepository(api);
  });

  tearDown(() => api.close());

  test('sends the week key and reads the resolved period', () async {
    api.dio.httpClientAdapter = FakeHttpAdapter((RequestOptions options) async {
      expect(options.path, '/reports/weekly');
      expect(options.method, 'GET');
      expect(options.queryParameters['week_key'], '2026-10-26');
      expect(options.queryParameters.containsKey('week_start_date'), isFalse);

      return FakeHttpAdapter.json(<String, dynamic>{'data': reportJson()});
    });

    final WeeklyReport report = await repository.weekly(weekKey: '2026-10-26');

    expect(report.weekKey, '2026-10-24');
    expect(report.endDate, '2026-10-30');
    expect(report.students, isEmpty);
  });

  test('sends a verbatim week start date for historical reports', () async {
    api.dio.httpClientAdapter = FakeHttpAdapter((RequestOptions options) async {
      expect(options.queryParameters['week_start_date'], '2026-10-24');
      expect(options.queryParameters.containsKey('week_key'), isFalse);

      return FakeHttpAdapter.json(<String, dynamic>{'data': reportJson()});
    });

    await repository.weekly(weekStartDate: '2026-10-24');
  });

  test('forwards the group and student filters', () async {
    api.dio.httpClientAdapter = FakeHttpAdapter((RequestOptions options) async {
      expect(options.queryParameters['group_id'], 4);
      expect(options.queryParameters['student_id'], 7);

      return FakeHttpAdapter.json(<String, dynamic>{'data': reportJson()});
    });

    await repository.weekly(weekKey: '2026-10-26', groupId: 4, studentId: 7);
  });
}
