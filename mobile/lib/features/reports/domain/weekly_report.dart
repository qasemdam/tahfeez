/// Read-only weekly report returned by `GET /reports/weekly`.
///
/// The week boundaries come from the server: the client never derives them.
/// Achievement is reported uncapped (2 pages of 1 = 200%) and is never
/// recomputed here — the value shown is the value the server returned.
class WeeklyReport {
  const WeeklyReport({
    required this.weekKey,
    required this.startDate,
    required this.endDate,
    required this.startDay,
    required this.students,
  });

  final String weekKey;
  final String startDate;
  final String endDate;
  final String startDay;
  final List<WeeklyReportRow> students;

  factory WeeklyReport.fromJson(Map<String, dynamic> json) {
    final Map<String, dynamic> week =
        json['week'] as Map<String, dynamic>? ?? const <String, dynamic>{};
    final List<dynamic> students =
        json['students'] as List<dynamic>? ?? const <dynamic>[];

    return WeeklyReport(
      weekKey: week['week_key'] as String? ?? '',
      startDate: week['start_date'] as String? ?? '',
      endDate: week['end_date'] as String? ?? '',
      startDay: week['start_day'] as String? ?? '',
      students: students
          .map(
            (dynamic row) =>
                WeeklyReportRow.fromJson(row as Map<String, dynamic>),
          )
          .toList(growable: false),
    );
  }
}

class WeeklyReportRow {
  const WeeklyReportRow({
    required this.studentId,
    required this.studentName,
    required this.attendance,
    required this.memorization,
    required this.review,
    required this.recitation,
    this.groupId,
    this.groupName,
    this.teacherId,
    this.teacherName,
    this.evaluation,
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

  /// The **stored** weekly evaluation, or null when none exists for the week.
  final EvaluationSummary? evaluation;

  factory WeeklyReportRow.fromJson(Map<String, dynamic> json) {
    final Map<String, dynamic> student =
        json['student'] as Map<String, dynamic>? ?? const <String, dynamic>{};
    final Map<String, dynamic>? group = json['group'] as Map<String, dynamic>?;
    final Map<String, dynamic>? teacher =
        json['teacher'] as Map<String, dynamic>?;
    final Map<String, dynamic>? evaluation =
        json['evaluation'] as Map<String, dynamic>?;

    return WeeklyReportRow(
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
      evaluation: evaluation == null
          ? null
          : EvaluationSummary.fromJson(evaluation),
    );
  }
}

class AttendanceSummary {
  const AttendanceSummary({
    required this.present,
    required this.late,
    required this.excusedAbsence,
    required this.unexcusedAbsence,
  });

  final int present;
  final int late;
  final int excusedAbsence;
  final int unexcusedAbsence;

  factory AttendanceSummary.fromJson(Map<String, dynamic> json) {
    return AttendanceSummary(
      present: json['present'] as int? ?? 0,
      late: json['late'] as int? ?? 0,
      excusedAbsence: json['excused_absence'] as int? ?? 0,
      unexcusedAbsence: json['unexcused_absence'] as int? ?? 0,
    );
  }
}

class MemorizationSummary {
  const MemorizationSummary({
    required this.requiredPages,
    required this.actualPages,
    required this.achievementPercentage,
    required this.sessions,
  });

  final double? requiredPages;
  final double? actualPages;

  /// Uncapped: 2 pages of 1 reads 200, never 100.
  final double? achievementPercentage;
  final int sessions;

  factory MemorizationSummary.fromJson(Map<String, dynamic> json) {
    return MemorizationSummary(
      requiredPages: _decimal(json['required_pages']),
      actualPages: _decimal(json['actual_pages']),
      achievementPercentage: _decimal(json['achievement_percentage']),
      sessions: json['sessions'] as int? ?? 0,
    );
  }
}

/// Review stays a separate figure; it is never folded into memorization.
class ReviewSummary {
  const ReviewSummary({required this.pages, required this.sessions});

  final double? pages;
  final int sessions;

  factory ReviewSummary.fromJson(Map<String, dynamic> json) {
    return ReviewSummary(
      pages: _decimal(json['pages']),
      sessions: json['sessions'] as int? ?? 0,
    );
  }
}

/// Recitation as recorded: sessions, grade counts and error totals. No score is
/// derived from a grade on the client.
class RecitationSummary {
  const RecitationSummary({
    required this.sessions,
    required this.grades,
    required this.hifzErrors,
    required this.tajweedErrors,
    required this.readingErrors,
  });

  final int sessions;
  final Map<String, int> grades;
  final int hifzErrors;
  final int tajweedErrors;
  final int readingErrors;

  factory RecitationSummary.fromJson(Map<String, dynamic> json) {
    final Map<String, dynamic> grades =
        json['grades'] as Map<String, dynamic>? ?? const <String, dynamic>{};
    final Map<String, dynamic> errors =
        json['errors'] as Map<String, dynamic>? ?? const <String, dynamic>{};

    return RecitationSummary(
      sessions: json['sessions'] as int? ?? 0,
      grades: grades.map(
        (String key, dynamic value) =>
            MapEntry<String, int>(key, value as int? ?? 0),
      ),
      hifzErrors: errors['hifz'] as int? ?? 0,
      tajweedErrors: errors['tajweed'] as int? ?? 0,
      readingErrors: errors['reading'] as int? ?? 0,
    );
  }
}

/// The stored weekly evaluation snapshot. Null means "not computed yet"; the
/// client must not invent one.
class EvaluationSummary {
  const EvaluationSummary({
    required this.id,
    required this.achievementPercentage,
    required this.isComplete,
    required this.status,
    this.weightedScore,
  });

  final int id;
  final double? weightedScore;
  final double? achievementPercentage;
  final bool isComplete;
  final String status;

  bool get isApproved => status == 'approved';

  factory EvaluationSummary.fromJson(Map<String, dynamic> json) {
    return EvaluationSummary(
      id: json['id'] as int? ?? 0,
      weightedScore: _decimal(json['weighted_score']),
      achievementPercentage: _decimal(json['achievement_percentage']),
      isComplete: json['is_complete'] as bool? ?? false,
      status: json['status'] as String? ?? 'computed',
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
