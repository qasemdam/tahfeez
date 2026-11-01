import 'weekly_report.dart';

/// Read-only monthly report returned by `GET /reports/monthly`.
///
/// The month is a calendar month resolved by the server. `memorization`
/// reuses the weekly summaries: `requiredPages` is the sum of the requirements
/// frozen in the stored weekly evaluations, and `achievementPercentage` is
/// null when no evaluation is stored (the ratio is undefined, not zero).
class MonthlyReport {
  const MonthlyReport({
    required this.monthKey,
    required this.startDate,
    required this.endDate,
    required this.students,
  });

  final String monthKey;
  final String startDate;
  final String endDate;
  final List<MonthlyReportRow> students;

  factory MonthlyReport.fromJson(Map<String, dynamic> json) {
    final Map<String, dynamic> month =
        json['month'] as Map<String, dynamic>? ?? const <String, dynamic>{};
    final List<dynamic> students =
        json['students'] as List<dynamic>? ?? const <dynamic>[];

    return MonthlyReport(
      monthKey: month['month_key'] as String? ?? '',
      startDate: month['start_date'] as String? ?? '',
      endDate: month['end_date'] as String? ?? '',
      students: students
          .map(
            (dynamic row) =>
                MonthlyReportRow.fromJson(row as Map<String, dynamic>),
          )
          .toList(growable: false),
    );
  }
}

class MonthlyReportRow {
  const MonthlyReportRow({
    required this.studentId,
    required this.studentName,
    required this.attendance,
    required this.memorization,
    required this.review,
    required this.recitation,
    required this.weeklyEvaluations,
    this.groupId,
    this.groupName,
    this.teacherId,
    this.teacherName,
  });

  final int studentId;
  final String studentName;
  final int? groupId;
  final String? groupName;
  final int? teacherId;
  final String? teacherName;
  final AttendanceSummary attendance;
  final MemorizationSummary memorization;
  final ReviewSummary review;
  final RecitationSummary recitation;
  final WeeklyEvaluationCounts weeklyEvaluations;

  factory MonthlyReportRow.fromJson(Map<String, dynamic> json) {
    final Map<String, dynamic> student =
        json['student'] as Map<String, dynamic>? ?? const <String, dynamic>{};
    final Map<String, dynamic>? group = json['group'] as Map<String, dynamic>?;
    final Map<String, dynamic>? teacher =
        json['teacher'] as Map<String, dynamic>?;

    return MonthlyReportRow(
      studentId: student['id'] as int? ?? 0,
      studentName: student['name'] as String? ?? '',
      groupId: group?['id'] as int?,
      groupName: group?['name'] as String?,
      teacherId: teacher?['id'] as int?,
      teacherName: teacher?['name'] as String?,
      attendance: AttendanceSummary.fromJson(
        json['attendance'] as Map<String, dynamic>? ??
            const <String, dynamic>{},
      ),
      memorization: MemorizationSummary.fromJson(
        json['memorization'] as Map<String, dynamic>? ??
            const <String, dynamic>{},
      ),
      review: ReviewSummary.fromJson(
        json['review'] as Map<String, dynamic>? ?? const <String, dynamic>{},
      ),
      recitation: RecitationSummary.fromJson(
        json['recitation'] as Map<String, dynamic>? ??
            const <String, dynamic>{},
      ),
      weeklyEvaluations: WeeklyEvaluationCounts.fromJson(
        json['weekly_evaluations'] as Map<String, dynamic>? ??
            const <String, dynamic>{},
      ),
    );
  }
}

/// How many stored weekly evaluations the month has, by status. The report
/// never computes or promotes one.
class WeeklyEvaluationCounts {
  const WeeklyEvaluationCounts({
    required this.count,
    required this.completed,
    required this.approved,
    required this.computed,
  });

  final int count;
  final int completed;
  final int approved;
  final int computed;

  factory WeeklyEvaluationCounts.fromJson(Map<String, dynamic> json) {
    return WeeklyEvaluationCounts(
      count: json['count'] as int? ?? 0,
      completed: json['completed'] as int? ?? 0,
      approved: json['approved'] as int? ?? 0,
      computed: json['computed'] as int? ?? 0,
    );
  }
}
