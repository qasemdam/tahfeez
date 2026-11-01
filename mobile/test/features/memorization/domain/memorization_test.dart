import 'package:flutter_test/flutter_test.dart';
import 'package:tahfeez/features/memorization/domain/memorization.dart';

void main() {
  test('maps amount sources to their wire values and labels', () {
    expect(AmountSource.fromWire('MANUAL'), AmountSource.manual);
    expect(AmountSource.manual.label, 'يدوي');
    expect(AmountSource.fromWire('CALCULATED'), AmountSource.calculated);
    expect(AmountSource.calculated.label, 'تلقائي');
    expect(AmountSource.fromWire(null), AmountSource.manual);
    expect(AmountSource.fromWire('unknown'), AmountSource.manual);
  });

  test('maps recitation grades', () {
    expect(RecitationGrade.fromWire('excellent'), RecitationGrade.excellent);
    expect(RecitationGrade.excellent.label, 'ممتاز');
    expect(RecitationGrade.fromWire('very_good'), RecitationGrade.veryGood);
    expect(RecitationGrade.fromWire('good'), RecitationGrade.good);
    expect(
      RecitationGrade.fromWire('needs_review'),
      RecitationGrade.needsReview,
    );
    expect(RecitationGrade.fromWire(null), isNull);
    expect(RecitationGrade.fromWire('unknown'), isNull);
  });

  test('parses a memorization record', () {
    final Memorization record = Memorization.fromJson(<String, dynamic>{
      'id': 4,
      'student_id': 2,
      'student_name': 'أحمد',
      'group_id': 1,
      'group_name': 'حلقة النور',
      'teacher_name': 'خالد',
      'memorization_date': '2026-10-31',
      'surah_id': 2,
      'surah_name': 'البقرة',
      'from_ayah': 1,
      'to_ayah': 5,
      'from_page': 2,
      'to_page': 2,
      'amount_pages': '0.7333',
      'amount_lines': 11,
      'amount_source': 'CALCULATED',
      'grade': 'very_good',
      'hifz_errors': 1,
      'tajweed_errors': 0,
      'reading_errors': 0,
      'notes': 'جيد',
    });

    expect(record.id, 4);
    expect(record.surahName, 'البقرة');
    expect(record.amountPages, '0.7333');
    expect(record.amountSource, AmountSource.calculated);
    expect(record.grade, RecitationGrade.veryGood);
    expect(record.fromAyah, 1);
    expect(record.toAyah, 5);
    expect(record.amountLines, 11);
  });

  test('manual submission omits the Quran range', () {
    const MemorizationSubmission submission = MemorizationSubmission(
      studentId: 2,
      memorizationDate: '2026-10-31',
      amountSource: AmountSource.manual,
      amountPages: '1.5',
      grade: RecitationGrade.good,
      notes: 'ملاحظة',
    );

    expect(submission.toJson(), <String, dynamic>{
      'student_id': 2,
      'memorization_date': '2026-10-31',
      'amount_source': 'MANUAL',
      'amount_pages': '1.5',
      'grade': 'good',
      'notes': 'ملاحظة',
    });
  });

  test('calculated submission sends the range and no amount', () {
    const MemorizationSubmission submission = MemorizationSubmission(
      studentId: 2,
      memorizationDate: '2026-10-31',
      amountSource: AmountSource.calculated,
      surahId: 2,
      fromAyah: 1,
      toAyah: 5,
    );

    expect(submission.toJson(), <String, dynamic>{
      'student_id': 2,
      'memorization_date': '2026-10-31',
      'amount_source': 'CALCULATED',
      'surah_id': 2,
      'from_ayah': 1,
      'to_ayah': 5,
    });
  });
}
