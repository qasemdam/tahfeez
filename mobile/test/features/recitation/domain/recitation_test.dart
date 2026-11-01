import 'package:flutter_test/flutter_test.dart';
import 'package:tahfeez/core/domain/assessment.dart';
import 'package:tahfeez/features/recitation/domain/recitation.dart';

void main() {
  test('parses a recitation record', () {
    final Recitation record = Recitation.fromJson(<String, dynamic>{
      'id': 5,
      'student_id': 2,
      'student_name': 'أحمد',
      'group_id': 1,
      'group_name': 'حلقة النور',
      'teacher_name': 'خالد',
      'recitation_date': '2026-10-31',
      'surah_id': 1,
      'surah_name': 'الفاتحة',
      'from_ayah': 1,
      'to_ayah': 7,
      'from_page': 1,
      'to_page': 1,
      'grade': 'excellent',
      'hifz_errors': 1,
      'tajweed_errors': 0,
      'reading_errors': 2,
      'notes': 'تسميع',
    });

    expect(record.id, 5);
    expect(record.surahName, 'الفاتحة');
    expect(record.fromAyah, 1);
    expect(record.toAyah, 7);
    expect(record.grade, RecitationGrade.excellent);
    expect(record.hifzErrors, 1);
    expect(record.readingErrors, 2);
  });

  test('submission sends the range and optional details', () {
    const RecitationSubmission submission = RecitationSubmission(
      studentId: 2,
      recitationDate: '2026-10-31',
      surahId: 1,
      fromAyah: 1,
      toAyah: 7,
      fromPage: 1,
      toPage: 1,
      grade: RecitationGrade.good,
      hifzErrors: 0,
      tajweedErrors: 1,
      readingErrors: 0,
      notes: 'ملاحظة',
    );

    expect(submission.toJson(), <String, dynamic>{
      'student_id': 2,
      'recitation_date': '2026-10-31',
      'surah_id': 1,
      'from_ayah': 1,
      'to_ayah': 7,
      'from_page': 1,
      'to_page': 1,
      'grade': 'good',
      'hifz_errors': 0,
      'tajweed_errors': 1,
      'reading_errors': 0,
      'notes': 'ملاحظة',
    });
  });

  test('submission omits empty optional fields', () {
    const RecitationSubmission submission = RecitationSubmission(
      studentId: 2,
      recitationDate: '2026-10-31',
      surahId: 1,
      fromAyah: 1,
      toAyah: 7,
    );

    expect(submission.toJson(), <String, dynamic>{
      'student_id': 2,
      'recitation_date': '2026-10-31',
      'surah_id': 1,
      'from_ayah': 1,
      'to_ayah': 7,
    });
  });
}
