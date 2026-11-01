/// One criterion's contribution inside a stored weekly evaluation.
///
/// These values come from the frozen `result_snapshot`; the client never
/// recomputes them and never reads the current profile or rule set.
class EvaluationComponent {
  const EvaluationComponent({
    required this.key,
    required this.weight,
    required this.included,
    required this.blocked,
    required this.explanation,
    this.rawValue,
    this.normalized,
    this.weighted,
  });

  final String key;
  final double weight;
  final double? rawValue;

  /// The criterion score. Uncapped for memorization volume, so it may exceed
  /// 100 — that is the achievement scale, not the weighted score.
  final double? normalized;
  final double? weighted;
  final bool included;
  final bool blocked;
  final String explanation;

  /// Arabic label for the closed criterion catalogue.
  String get label {
    switch (key) {
      case 'memorization_pages':
        return 'الحفظ الجديد';
      case 'review_completion':
        return 'المراجعة';
      case 'recitation_quality':
        return 'التسميع';
      case 'error_rate':
        return 'معدل الأخطاء';
      case 'attendance':
        return 'الحضور';
      default:
        return key;
    }
  }

  factory EvaluationComponent.fromJson(Map<String, dynamic> json) {
    return EvaluationComponent(
      key: json['key'] as String? ?? '',
      weight: _decimal(json['weight']) ?? 0,
      rawValue: _decimal(json['raw_value']),
      normalized: _decimal(json['normalized']),
      weighted: _decimal(json['weighted']),
      included: json['included'] as bool? ?? false,
      blocked: json['blocked'] as bool? ?? false,
      explanation: json['explanation'] as String? ?? '',
    );
  }
}

/// A stored, historical weekly evaluation.
///
/// Everything shown here was frozen at creation time: the week boundaries, the
/// profile version, the rule set and the computed result. Changing the center
/// settings or the profile later never rewrites this record.
class WeeklyEvaluation {
  const WeeklyEvaluation({
    required this.id,
    required this.studentId,
    required this.studentName,
    required this.groupName,
    required this.teacherName,
    required this.weekKey,
    required this.weekStartDate,
    required this.weekEndDate,
    required this.startDay,
    required this.isComplete,
    required this.status,
    required this.components,
    required this.blockedCriteria,
    this.groupId,
    this.profileVersion,
    this.requiredPages,
    this.actualPages,
    this.achievementPercentage,
    this.weightedScore,
  });

  final int id;
  final int studentId;
  final String studentName;
  final int? groupId;
  final String groupName;
  final String teacherName;

  /// Canonical Gregorian dates (`YYYY-MM-DD`), already resolved server-side
  /// from the center timezone and `weekly_evaluation_start_day`.
  final String weekKey;
  final String weekStartDate;
  final String weekEndDate;

  /// The snapshot of the center setting used for this week.
  final String startDay;

  final int? profileVersion;
  final double? requiredPages;
  final double? actualPages;

  /// `actual / required x 100`, uncapped: 1 page of 1 = 100, 2 of 1 = 200.
  final double? achievementPercentage;

  /// 0..100, or null when a criterion blocked the computation.
  final double? weightedScore;

  final bool isComplete;
  final String status;
  final List<String> blockedCriteria;
  final List<EvaluationComponent> components;

  bool get isApproved => status == 'approved';

  bool get hasWeightedScore => weightedScore != null;

  String get statusLabel => isApproved ? 'معتمد' : 'محسوب';

  /// The components that actually fed the weighted score.
  List<EvaluationComponent> get includedComponents {
    return components
        .where((EvaluationComponent component) => component.included)
        .toList(growable: false);
  }

  factory WeeklyEvaluation.fromJson(Map<String, dynamic> json) {
    final List<dynamic> components =
        json['components'] as List<dynamic>? ?? const <dynamic>[];
    final List<dynamic> blocked =
        json['blocked_criteria'] as List<dynamic>? ?? const <dynamic>[];

    return WeeklyEvaluation(
      id: json['id'] as int,
      studentId: json['student_id'] as int? ?? 0,
      studentName: json['student_name'] as String? ?? '',
      groupId: json['group_id'] as int?,
      groupName: json['group_name'] as String? ?? '',
      teacherName: json['teacher_name'] as String? ?? '',
      weekKey: json['week_key'] as String? ?? '',
      weekStartDate: json['week_start_date'] as String? ?? '',
      weekEndDate: json['week_end_date'] as String? ?? '',
      startDay: json['weekly_evaluation_start_day'] as String? ?? '',
      profileVersion: json['evaluation_profile_version'] as int?,
      requiredPages: _decimal(json['required_pages']),
      actualPages: _decimal(json['actual_pages']),
      achievementPercentage: _decimal(json['achievement_percentage']),
      weightedScore: _decimal(json['weighted_score']),
      isComplete: json['is_complete'] as bool? ?? false,
      status: json['status'] as String? ?? 'computed',
      blockedCriteria: blocked
          .map((dynamic key) => key as String)
          .toList(growable: false),
      components: components
          .map(
            (dynamic row) =>
                EvaluationComponent.fromJson(row as Map<String, dynamic>),
          )
          .toList(growable: false),
    );
  }
}

/// The payload for a new weekly evaluation.
///
/// Only `week_date` is sent: any day inside the week. The server resolves the
/// boundaries from the center setting, so the client never hard-codes a start
/// day and never guesses a timezone.
class WeeklyEvaluationSubmission {
  const WeeklyEvaluationSubmission({
    required this.studentId,
    required this.weekDate,
    this.profileVersionId,
  });

  final int studentId;
  final String weekDate;
  final int? profileVersionId;

  Map<String, dynamic> toJson() => <String, dynamic>{
    'student_id': studentId,
    'week_date': weekDate,
    if (profileVersionId != null) 'profile_version_id': profileVersionId,
  };
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
