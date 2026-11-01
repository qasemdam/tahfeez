import 'weekly_report.dart';

/// Center KPIs for one month, from `GET /dashboard`.
///
/// Every figure is already scoped by the server to the groups the actor can
/// see. Nothing here is derived on the client.
class DashboardSummary {
  const DashboardSummary({
    required this.month,
    required this.startDate,
    required this.endDate,
    required this.studentsTotal,
    required this.studentsActive,
    required this.groupsTotal,
    required this.attendance,
    required this.memorization,
    required this.review,
    required this.recitationSessions,
    required this.weeklyEvaluations,
  });

  final String month;
  final String startDate;
  final String endDate;
  final int studentsTotal;
  final int studentsActive;
  final int groupsTotal;
  final AttendanceSummary attendance;
  final PageTotals memorization;
  final PageTotals review;
  final int recitationSessions;
  final WeeklyEvaluationTotals weeklyEvaluations;

  factory DashboardSummary.fromJson(Map<String, dynamic> json) {
    final Map<String, dynamic> period =
        json['period'] as Map<String, dynamic>? ?? const <String, dynamic>{};
    final Map<String, dynamic> students =
        json['students'] as Map<String, dynamic>? ?? const <String, dynamic>{};
    final Map<String, dynamic> groups =
        json['groups'] as Map<String, dynamic>? ?? const <String, dynamic>{};
    final Map<String, dynamic> recitation =
        json['recitation'] as Map<String, dynamic>? ??
        const <String, dynamic>{};

    return DashboardSummary(
      month: period['month'] as String? ?? '',
      startDate: period['start_date'] as String? ?? '',
      endDate: period['end_date'] as String? ?? '',
      studentsTotal: students['total'] as int? ?? 0,
      studentsActive: students['active'] as int? ?? 0,
      groupsTotal: groups['total'] as int? ?? 0,
      attendance: AttendanceSummary.fromJson(
        json['attendance'] as Map<String, dynamic>? ??
            const <String, dynamic>{},
      ),
      memorization: PageTotals.fromJson(
        json['memorization'] as Map<String, dynamic>? ??
            const <String, dynamic>{},
      ),
      review: PageTotals.fromJson(
        json['review'] as Map<String, dynamic>? ?? const <String, dynamic>{},
      ),
      recitationSessions: recitation['sessions'] as int? ?? 0,
      weeklyEvaluations: WeeklyEvaluationTotals.fromJson(
        json['weekly_evaluations'] as Map<String, dynamic>? ??
            const <String, dynamic>{},
      ),
    );
  }
}

/// A page total with its session count. Decimal strings are parsed, never
/// re-summed on the client.
class PageTotals {
  const PageTotals({required this.pages, required this.sessions});

  final double? pages;
  final int sessions;

  factory PageTotals.fromJson(Map<String, dynamic> json) {
    return PageTotals(
      pages: _decimal(json['pages']),
      sessions: json['sessions'] as int? ?? 0,
    );
  }
}

/// Stored weekly evaluations, counted by status. `computed` is never promoted.
class WeeklyEvaluationTotals {
  const WeeklyEvaluationTotals({
    required this.count,
    required this.approved,
    required this.computed,
  });

  final int count;
  final int approved;
  final int computed;

  factory WeeklyEvaluationTotals.fromJson(Map<String, dynamic> json) {
    return WeeklyEvaluationTotals(
      count: json['count'] as int? ?? 0,
      approved: json['approved'] as int? ?? 0,
      computed: json['computed'] as int? ?? 0,
    );
  }
}

double? _decimal(Object? value) {
  if (value == null) {
    return null;
  }
  if (value is num) {
    return value.toDouble();
  }
  return double.tryParse(value.toString());
}
