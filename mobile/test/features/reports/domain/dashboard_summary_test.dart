import 'package:flutter_test/flutter_test.dart';
import 'package:tahfeez/features/reports/domain/dashboard_summary.dart';

Map<String, dynamic> payload({String pages = '245.5000'}) {
  return <String, dynamic>{
    'period': <String, dynamic>{
      'month': '2026-10',
      'start_date': '2026-10-01',
      'end_date': '2026-10-31',
    },
    'students': <String, dynamic>{'total': 120, 'active': 115},
    'groups': <String, dynamic>{'total': 8},
    'attendance': <String, dynamic>{
      'present': 820,
      'late': 35,
      'excused_absence': 42,
      'unexcused_absence': 18,
    },
    'memorization': <String, dynamic>{'pages': pages, 'sessions': 310},
    'review': <String, dynamic>{'pages': '180.2500', 'sessions': 270},
    'recitation': <String, dynamic>{'sessions': 190},
    'weekly_evaluations': <String, dynamic>{
      'count': 420,
      'approved': 380,
      'computed': 40,
    },
  };
}

void main() {
  test('reads the period and the counts', () {
    final DashboardSummary summary = DashboardSummary.fromJson(payload());

    expect(summary.month, '2026-10');
    expect(summary.startDate, '2026-10-01');
    expect(summary.endDate, '2026-10-31');
    expect(summary.studentsTotal, 120);
    expect(summary.studentsActive, 115);
    expect(summary.groupsTotal, 8);
  });

  test('reads the attendance breakdown', () {
    final DashboardSummary summary = DashboardSummary.fromJson(payload());

    expect(summary.attendance.present, 820);
    expect(summary.attendance.late, 35);
    expect(summary.attendance.excusedAbsence, 42);
    expect(summary.attendance.unexcusedAbsence, 18);
  });

  test('reads page totals without recomputing them', () {
    final DashboardSummary summary = DashboardSummary.fromJson(payload());

    expect(summary.memorization.pages, 245.5);
    expect(summary.memorization.sessions, 310);
    expect(summary.review.pages, 180.25);
    expect(summary.review.sessions, 270);
    expect(summary.recitationSessions, 190);
  });

  test('reads the evaluation counts without promoting a status', () {
    final DashboardSummary summary = DashboardSummary.fromJson(payload());

    expect(summary.weeklyEvaluations.count, 420);
    expect(summary.weeklyEvaluations.approved, 380);
    expect(summary.weeklyEvaluations.computed, 40);
  });

  test('tolerates an empty period', () {
    final DashboardSummary summary = DashboardSummary.fromJson(
      <String, dynamic>{
        'period': <String, dynamic>{
          'month': '2026-10',
          'start_date': '2026-10-01',
          'end_date': '2026-10-31',
        },
        'students': <String, dynamic>{'total': 0, 'active': 0},
        'groups': <String, dynamic>{'total': 0},
        'attendance': <String, dynamic>{
          'present': 0,
          'late': 0,
          'excused_absence': 0,
          'unexcused_absence': 0,
        },
        'memorization': <String, dynamic>{'pages': '0.0000', 'sessions': 0},
        'review': <String, dynamic>{'pages': '0.0000', 'sessions': 0},
        'recitation': <String, dynamic>{'sessions': 0},
        'weekly_evaluations': <String, dynamic>{
          'count': 0,
          'approved': 0,
          'computed': 0,
        },
      },
    );

    expect(summary.studentsTotal, 0);
    expect(summary.memorization.pages, 0);
    expect(summary.weeklyEvaluations.count, 0);
  });
}
