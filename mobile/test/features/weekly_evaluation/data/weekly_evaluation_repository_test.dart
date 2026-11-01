import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tahfeez/core/network/api_client.dart';
import 'package:tahfeez/features/weekly_evaluation/data/weekly_evaluation_repository.dart';
import 'package:tahfeez/features/weekly_evaluation/domain/weekly_evaluation.dart';

import '../../../helpers/fake_http_adapter.dart';

Map<String, dynamic> evaluationJson({String status = 'computed'}) {
  return <String, dynamic>{
    'id': 5,
    'student_id': 2,
    'student_name': 'أحمد',
    'group_id': 1,
    'group_name': 'حلقة النور',
    'teacher_name': 'خالد',
    'week_key': '2026-10-24',
    'week_start_date': '2026-10-24',
    'week_end_date': '2026-10-30',
    'weekly_evaluation_start_day': 'saturday',
    'required_pages': '1.00',
    'actual_pages': '1.5000',
    'achievement_percentage': '150.00',
    'weighted_score': '80.00',
    'is_complete': true,
    'status': status,
    'blocked_criteria': <dynamic>[],
    'components': <dynamic>[],
  };
}

void main() {
  late ApiClient api;
  late WeeklyEvaluationRepository repository;

  setUp(() {
    api = ApiClient();
    repository = WeeklyEvaluationRepository(api);
  });

  tearDown(() => api.close());

  test(
    'creates a weekly evaluation with a week date, not boundaries',
    () async {
      api.dio.httpClientAdapter = FakeHttpAdapter((
        RequestOptions options,
      ) async {
        expect(options.path, '/weekly-evaluations');
        expect(options.method, 'POST');

        final Map<String, dynamic> body = options.data as Map<String, dynamic>;
        expect(body['student_id'], 2);
        expect(body['week_date'], '2026-10-26');
        expect(body.containsKey('week_start_date'), isFalse);
        expect(body.containsKey('weekly_evaluation_start_day'), isFalse);

        return FakeHttpAdapter.json(<String, dynamic>{
          'data': evaluationJson(),
        }, statusCode: 201);
      });

      final WeeklyEvaluation evaluation = await repository.create(
        const WeeklyEvaluationSubmission(studentId: 2, weekDate: '2026-10-26'),
      );

      expect(evaluation.achievementPercentage, 150);
      expect(evaluation.weekStartDate, '2026-10-24');
    },
  );

  test('lists by student', () async {
    api.dio.httpClientAdapter = FakeHttpAdapter((RequestOptions options) async {
      expect(options.path, '/weekly-evaluations');
      expect(options.queryParameters['student_id'], 2);

      return FakeHttpAdapter.json(<String, dynamic>{
        'data': <dynamic>[evaluationJson()],
      });
    });

    final List<WeeklyEvaluation> rows = await repository.list(studentId: 2);

    expect(rows, hasLength(1));
    expect(rows.first.actualPages, 1.5);
  });

  test('forwards the week filters', () async {
    api.dio.httpClientAdapter = FakeHttpAdapter((RequestOptions options) async {
      expect(options.queryParameters['week_key'], '2026-10-24');
      expect(options.queryParameters['group_id'], 1);

      return FakeHttpAdapter.json(<String, dynamic>{'data': <dynamic>[]});
    });

    expect(await repository.list(groupId: 1, weekKey: '2026-10-24'), isEmpty);
  });

  test('approves through a status-only patch', () async {
    api.dio.httpClientAdapter = FakeHttpAdapter((RequestOptions options) async {
      expect(options.path, '/weekly-evaluations/5');
      expect(options.method, 'PATCH');
      expect(options.data, <String, dynamic>{'status': 'approved'});

      return FakeHttpAdapter.json(<String, dynamic>{
        'data': evaluationJson(status: 'approved'),
      });
    });

    final WeeklyEvaluation evaluation = await repository.approve(5);

    expect(evaluation.isApproved, isTrue);
  });
}
