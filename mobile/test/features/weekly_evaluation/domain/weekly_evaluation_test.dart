import 'package:flutter_test/flutter_test.dart';
import 'package:tahfeez/features/weekly_evaluation/domain/weekly_evaluation.dart';

void main() {
  Map<String, dynamic> payload({
    String? achievement = '200.00',
    String? weighted = '87.50',
    String status = 'computed',
    List<dynamic> components = const <dynamic>[],
    List<dynamic> blocked = const <dynamic>[],
  }) {
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
      'evaluation_profile_version': 3,
      'required_pages': '1.00',
      'actual_pages': '2.0000',
      'achievement_percentage': achievement,
      'weighted_score': weighted,
      'is_complete': true,
      'status': status,
      'blocked_criteria': blocked,
      'components': components,
    };
  }

  test('keeps achievement above 100 without normalisation', () {
    final WeeklyEvaluation evaluation = WeeklyEvaluation.fromJson(payload());

    expect(evaluation.achievementPercentage, 200);
    expect(evaluation.requiredPages, 1);
    expect(evaluation.actualPages, 2);
  });

  test('reads the frozen week boundaries and start day', () {
    final WeeklyEvaluation evaluation = WeeklyEvaluation.fromJson(payload());

    expect(evaluation.weekStartDate, '2026-10-24');
    expect(evaluation.weekEndDate, '2026-10-30');
    expect(evaluation.startDay, 'saturday');
    expect(evaluation.profileVersion, 3);
  });

  test('marks a null weighted score as not computed', () {
    final WeeklyEvaluation evaluation = WeeklyEvaluation.fromJson(
      payload(weighted: null, blocked: <dynamic>['attendance']),
    );

    expect(evaluation.hasWeightedScore, isFalse);
    expect(evaluation.blockedCriteria, <String>['attendance']);
  });

  test('exposes status labels', () {
    expect(WeeklyEvaluation.fromJson(payload()).statusLabel, 'محسوب');
    expect(
      WeeklyEvaluation.fromJson(payload(status: 'approved')).isApproved,
      isTrue,
    );
    expect(
      WeeklyEvaluation.fromJson(payload(status: 'approved')).statusLabel,
      'معتمد',
    );
  });

  test('parses components and keeps only the included ones', () {
    final WeeklyEvaluation evaluation = WeeklyEvaluation.fromJson(
      payload(
        components: <dynamic>[
          <String, dynamic>{
            'key': 'memorization_pages',
            'weight': '60.00',
            'raw_value': '2.0000',
            'normalized': '200.00',
            'weighted': '120.00',
            'included': true,
            'blocked': false,
            'explanation': 'حجم الحفظ',
          },
          <String, dynamic>{
            'key': 'attendance',
            'weight': '40.00',
            'raw_value': null,
            'normalized': null,
            'weighted': null,
            'included': false,
            'blocked': false,
            'explanation': 'غير مفعّل',
          },
        ],
      ),
    );

    expect(evaluation.components, hasLength(2));
    expect(evaluation.components.first.label, 'الحفظ الجديد');
    expect(evaluation.components.first.normalized, 200);
    expect(evaluation.components.last.label, 'الحضور');
    expect(evaluation.includedComponents, hasLength(1));
  });

  test('builds a submission with the week date only', () {
    const WeeklyEvaluationSubmission submission = WeeklyEvaluationSubmission(
      studentId: 2,
      weekDate: '2026-10-26',
    );

    expect(submission.toJson(), <String, dynamic>{
      'student_id': 2,
      'week_date': '2026-10-26',
    });
    expect(submission.toJson().containsKey('week_key'), isFalse);
    expect(submission.toJson().containsKey('week_start_date'), isFalse);
  });
}
