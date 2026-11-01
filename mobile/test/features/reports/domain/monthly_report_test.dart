import 'package:flutter_test/flutter_test.dart';
import 'package:tahfeez/features/reports/domain/monthly_report.dart';

Map<String, dynamic> payload({
  String? achievement = '150.00',
  String required = '4.00',
  Map<String, dynamic>? weeklyEvaluations,
}) {
  return <String, dynamic>{
    'month': <String, dynamic>{
      'month_key': '2026-10',
      'start_date': '2026-10-01',
      'end_date': '2026-10-31',
    },
    'students': <dynamic>[
      <String, dynamic>{
        'student': <String, dynamic>{'id': 2, 'name': 'أحمد'},
        'group': <String, dynamic>{'id': 1, 'name': 'حلقة النور'},
        'teacher': <String, dynamic>{'id': 3, 'name': 'خالد'},
        'attendance': <String, dynamic>{
          'present': 15,
          'late': 2,
          'excused_absence': 1,
          'unexcused_absence': 0,
        },
        'memorization': <String, dynamic>{
          'required_pages': required,
          'actual_pages': '6.0000',
          'achievement_percentage': achievement,
          'sessions': 8,
        },
        'review': <String, dynamic>{'pages': '3.5000', 'sessions': 6},
        'recitation': <String, dynamic>{
          'sessions': 5,
          'grades': <String, dynamic>{'good': 4, 'excellent': 1},
          'errors': <String, dynamic>{'hifz': 3, 'tajweed': 1, 'reading': 2},
        },
        'weekly_evaluations':
            weeklyEvaluations ??
            <String, dynamic>{
              'count': 4,
              'completed': 4,
              'approved': 3,
              'computed': 1,
            },
      },
    ],
    'totals': <String, dynamic>{'students': 1},
  };
}

void main() {
  test('reads the calendar month', () {
    final MonthlyReport report = MonthlyReport.fromJson(payload());

    expect(report.monthKey, '2026-10');
    expect(report.startDate, '2026-10-01');
    expect(report.endDate, '2026-10-31');
  });

  test('keeps the monthly achievement uncapped', () {
    final MonthlyReportRow row = MonthlyReport.fromJson(payload())
        .students
        .first;

    expect(row.memorization.requiredPages, 4);
    expect(row.memorization.actualPages, 6);
    expect(row.memorization.achievementPercentage, 150);
  });

  test('reports an undefined ratio as null, not zero', () {
    final MonthlyReportRow row = MonthlyReport.fromJson(
      payload(achievement: null, required: '0.00'),
    ).students.first;

    expect(row.memorization.requiredPages, 0);
    expect(row.memorization.achievementPercentage, isNull);
  });

  test('reads the evaluation counts without promoting a status', () {
    final MonthlyReportRow row = MonthlyReport.fromJson(payload())
        .students
        .first;

    expect(row.weeklyEvaluations.count, 4);
    expect(row.weeklyEvaluations.completed, 4);
    expect(row.weeklyEvaluations.approved, 3);
    expect(row.weeklyEvaluations.computed, 1);
  });

  test('reads a month without any evaluation', () {
    final MonthlyReportRow row = MonthlyReport.fromJson(
      payload(
        achievement: null,
        required: '0.00',
        weeklyEvaluations: <String, dynamic>{
          'count': 0,
          'completed': 0,
          'approved': 0,
          'computed': 0,
        },
      ),
    ).students.first;

    expect(row.weeklyEvaluations.count, 0);
    expect(row.weeklyEvaluations.approved, 0);
  });

  test('reads attendance, review and recitation', () {
    final MonthlyReportRow row = MonthlyReport.fromJson(payload())
        .students
        .first;

    expect(row.attendance.present, 15);
    expect(row.attendance.late, 2);
    expect(row.review.pages, 3.5);
    expect(row.review.sessions, 6);
    expect(row.recitation.grades['excellent'], 1);
    expect(row.recitation.errors.tajweed, 1);
  });
}
