/// How an amount was obtained. The wire values follow the backend and
/// database.md: `CALCULATED` is what the product calls "AUTO".
enum AmountSource {
  manual('MANUAL', 'يدوي'),
  calculated('CALCULATED', 'تلقائي');

  const AmountSource(this.wire, this.label);

  final String wire;
  final String label;

  static AmountSource fromWire(String? value) {
    for (final AmountSource source in AmountSource.values) {
      if (source.wire == value) {
        return source;
      }
    }

    return AmountSource.manual;
  }
}

/// Recitation grade, as defined in docs/business-rules.md (BR-REC-01).
enum RecitationGrade {
  excellent('excellent', 'ممتاز'),
  veryGood('very_good', 'جيد جدًا'),
  good('good', 'جيد'),
  needsReview('needs_review', 'يحتاج مراجعة');

  const RecitationGrade(this.wire, this.label);

  final String wire;
  final String label;

  static RecitationGrade? fromWire(String? value) {
    for (final RecitationGrade grade in RecitationGrade.values) {
      if (grade.wire == value) {
        return grade;
      }
    }

    return null;
  }
}
