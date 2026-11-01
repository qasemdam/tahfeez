import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tahfeez/core/network/api_client.dart';
import 'package:tahfeez/features/reports/data/reports_repository.dart';
import 'package:tahfeez/features/reports/domain/dashboard_summary.dart';
import 'package:tahfeez/features/reports/domain/monthly_report.dart';
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

  test('sends the month and reads the calendar period', () async {
    api.dio.httpClientAdapter = FakeHttpAdapter((RequestOptions options) async {
      expect(options.path, '/reports/monthly');
      expect(options.method, 'GET');
      expect(options.queryParameters['month'], '2026-10');
      expect(options.queryParameters.containsKey('group_id'), isFalse);

      return FakeHttpAdapter.json(<String, dynamic>{
        'data': <String, dynamic>{
          'month': <String, dynamic>{
            'month_key': '2026-10',
            'start_date': '2026-10-01',
            'end_date': '2026-10-31',
          },
          'students': <dynamic>[],
          'totals': <String, dynamic>{'students': 0},
        },
      });
    });

    final MonthlyReport report = await repository.monthly(month: '2026-10');

    expect(report.monthKey, '2026-10');
    expect(report.startDate, '2026-10-01');
    expect(report.endDate, '2026-10-31');
    expect(report.students, isEmpty);
  });

  test('forwards the monthly filters', () async {
    api.dio.httpClientAdapter = FakeHttpAdapter((RequestOptions options) async {
      expect(options.queryParameters['month'], '2026-10');
      expect(options.queryParameters['group_id'], 4);
      expect(options.queryParameters['student_id'], 7);

      return FakeHttpAdapter.json(<String, dynamic>{
        'data': <String, dynamic>{
          'month': <String, dynamic>{
            'month_key': '2026-10',
            'start_date': '2026-10-01',
            'end_date': '2026-10-31',
          },
          'students': <dynamic>[],
          'totals': <String, dynamic>{'students': 0},
        },
      });
    });

    await repository.monthly(month: '2026-10', groupId: 4, studentId: 7);
  });

  test('reads the dashboard for a month', () async {
    api.dio.httpClientAdapter = FakeHttpAdapter((RequestOptions options) async {
      expect(options.path, '/dashboard');
      expect(options.method, 'GET');
      expect(options.queryParameters['month'], '2026-10');
      expect(options.queryParameters.containsKey('group_id'), isFalse);

      return FakeHttpAdapter.json(<String, dynamic>{
        'data': <String, dynamic>{
          'period': <String, dynamic>{
            'month': '2026-10',
            'start_date': '2026-10-01',
            'end_date': '2026-10-31',
          },
          'students': <String, dynamic>{'total': 3, 'active': 2},
          'groups': <String, dynamic>{'total': 1},
          'attendance': <String, dynamic>{
            'present': 5,
            'late': 0,
            'excused_absence': 0,
            'unexcused_absence': 0,
          },
          'memorization': <String, dynamic>{'pages': '2.5000', 'sessions': 2},
          'review': <String, dynamic>{'pages': '1.0000', 'sessions': 1},
          'recitation': <String, dynamic>{'sessions': 1},
          'weekly_evaluations': <String, dynamic>{
            'count': 1,
            'approved': 1,
            'computed': 0,
          },
        },
      });
    });

    final DashboardSummary summary = await repository.dashboard(
      month: '2026-10',
    );

    expect(summary.month, '2026-10');
    expect(summary.studentsTotal, 3);
    expect(summary.memorization.pages, 2.5);
  });

  test('forwards the dashboard group filter', () async {
    api.dio.httpClientAdapter = FakeHttpAdapter((RequestOptions options) async {
      expect(options.queryParameters['month'], '2026-10');
      expect(options.queryParameters['group_id'], 4);

      return FakeHttpAdapter.json(<String, dynamic>{
        'data': <String, dynamic>{
          'period': <String, dynamic>{
            'month': '2026-10',
            'start_date': '2026-10-01',
            'end_date': '2026-10-31',
          },
        },
      });
    });

    await repository.dashboard(month: '2026-10', groupId: 4);
  });
}
