import 'package:flutter_test/flutter_test.dart';
import 'package:tahfeez/features/reports/domain/weekly_report.dart';

Map<String, dynamic> payload({
  String achievement = '200.00',
  Map<String, dynamic>? evaluation,
}) {
  return <String, dynamic>{
    'week': <String, dynamic>{
      'week_key': '2026-10-24',
      'start_date': '2026-10-24',
      'end_date': '2026-10-30',
      'start_day': 'saturday',
    },
    'students': <dynamic>[
      <String, dynamic>{
        'student': <String, dynamic>{'id': 2, 'name': 'أحمد'},
        'group': <String, dynamic>{'id': 1, 'name': 'حلقة النور'},
        'teacher': <String, dynamic>{'id': 3, 'name': 'خالد'},
        'attendance': <String, dynamic>{
          'present': 3,
          'late': 1,
          'excused_absence': 1,
          'unexcused_absence': 0,
        },
        'memorization': <String, dynamic>{
          'required_pages': '1.00',
          'actual_pages': '2.0000',
          'achievement_percentage': achievement,
          'sessions': 2,
        },
        'review': <String, dynamic>{'pages': '0.5000', 'sessions': 1},
        'recitation': <String, dynamic>{
          'sessions': 1,
          'grades': <String, dynamic>{'good': 1},
          'errors': <String, dynamic>{'hifz': 2, 'tajweed': 0, 'reading': 1},
        },
        'evaluation': evaluation,
      },
    ],
    'totals': <String, dynamic>{'students': 1},
  };
}

void main() {
  test('reads the server-resolved week', () {
    final WeeklyReport report = WeeklyReport.fromJson(payload());

    expect(report.weekKey, '2026-10-24');
    expect(report.startDate, '2026-10-24');
    expect(report.endDate, '2026-10-30');
    expect(report.startDay, 'saturday');
  });

  test('keeps achievement above 100 and does not recompute it', () {
    final WeeklyReportRow row = WeeklyReport.fromJson(payload()).students.first;

    expect(row.memorization.requiredPages, 1);
    expect(row.memorization.actualPages, 2);
    expect(row.memorization.achievementPercentage, 200);
  });

  test('reads attendance, review and recitation separately', () {
    final WeeklyReportRow row = WeeklyReport.fromJson(payload()).students.first;

    expect(row.attendance.present, 3);
    expect(row.attendance.excusedAbsence, 1);
    expect(row.review.pages, 0.5);
    expect(row.review.sessions, 1);
    expect(row.recitation.grades['good'], 1);
    expect(row.recitation.hifzErrors, 2);
    expect(row.recitation.readingErrors, 1);
  });

  test('reports a missing evaluation as null, never as zero', () {
    final WeeklyReportRow row = WeeklyReport.fromJson(payload()).students.first;

    expect(row.evaluation, isNull);
  });

  test('reads a stored evaluation snapshot', () {
    final WeeklyReportRow row = WeeklyReport.fromJson(
      payload(
        evaluation: <String, dynamic>{
          'id': 9,
          'weighted_score': '87.50',
          'achievement_percentage': '200.00',
          'is_complete': true,
          'status': 'approved',
        },
      ),
    ).students.first;

    expect(row.evaluation?.id, 9);
    expect(row.evaluation?.weightedScore, 87.5);
    expect(row.evaluation?.achievementPercentage, 200);
    expect(row.evaluation?.isApproved, isTrue);
  });

  test('tolerates a null weighted score', () {
    final WeeklyReportRow row = WeeklyReport.fromJson(
      payload(
        evaluation: <String, dynamic>{
          'id': 9,
          'weighted_score': null,
          'achievement_percentage': '100.00',
          'is_complete': false,
          'status': 'computed',
        },
      ),
    ).students.first;

    expect(row.evaluation?.weightedScore, isNull);
    expect(row.evaluation?.isComplete, isFalse);
  });
}
