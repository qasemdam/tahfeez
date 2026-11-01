import 'package:flutter_test/flutter_test.dart';
import 'package:tahfeez/core/domain/assessment.dart';
import 'package:tahfeez/features/review/domain/review.dart';

void main() {
  test('parses a review record', () {
    final Review review = Review.fromJson(<String, dynamic>{
      'id': 7,
      'student_id': 2,
      'student_name': 'أحمد',
      'group_id': 1,
      'group_name': 'حلقة النور',
      'teacher_name': 'خالد',
      'review_date': '2026-10-31',
      'surah_id': 1,
      'surah_name': 'الفاتحة',
      'from_ayah': 1,
      'to_ayah': 7,
      'from_page': 1,
      'to_page': 1,
      'amount_pages': '1.0000',
      'amount_lines': 8,
      'amount_source': 'CALCULATED',
      'grade': 'good',
      'hifz_errors': 1,
      'tajweed_errors': 0,
      'reading_errors': 2,
      'notes': 'مراجعة',
    });

    expect(review.id, 7);
    expect(review.surahName, 'الفاتحة');
    expect(review.amountPages, '1.0000');
    expect(review.amountSource.wire, 'CALCULATED');
    expect(review.grade?.wire, 'good');
    expect(review.fromAyah, 1);
    expect(review.toAyah, 7);
    expect(review.amountLines, 8);
  });

  test('calculated submission sends the range and no amount', () {
    const ReviewSubmission submission = ReviewSubmission(
      studentId: 2,
      reviewDate: '2026-10-31',
      amountSource: AmountSource.calculated,
      surahId: 1,
      fromAyah: 1,
      toAyah: 7,
      grade: RecitationGrade.good,
    );

    expect(submission.toJson(), <String, dynamic>{
      'student_id': 2,
      'review_date': '2026-10-31',
      'amount_source': 'CALCULATED',
      'surah_id': 1,
      'from_ayah': 1,
      'to_ayah': 7,
      'grade': 'good',
    });
  });

  test('manual submission sends the amount and pages', () {
    const ReviewSubmission submission = ReviewSubmission(
      studentId: 2,
      reviewDate: '2026-10-31',
      amountSource: AmountSource.manual,
      amountPages: '1.5',
      fromPage: 1,
      toPage: 2,
      notes: 'ملاحظة',
    );

    expect(submission.toJson(), <String, dynamic>{
      'student_id': 2,
      'review_date': '2026-10-31',
      'amount_source': 'MANUAL',
      'amount_pages': '1.5',
      'from_page': 1,
      'to_page': 2,
      'notes': 'ملاحظة',
    });
  });
}
