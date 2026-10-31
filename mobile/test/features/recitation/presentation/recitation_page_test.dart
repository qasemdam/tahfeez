import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tahfeez/core/domain/assessment.dart';
import 'package:tahfeez/features/quran/data/quran_repository.dart';
import 'package:tahfeez/features/quran/domain/surah.dart';
import 'package:tahfeez/features/recitation/data/recitation_repository.dart';
import 'package:tahfeez/features/recitation/domain/recitation.dart';
import 'package:tahfeez/features/recitation/presentation/recitation_page.dart';

class _FakeRecitationRepository implements RecitationRepository {
  RecitationSubmission? saved;

  @override
  Future<Recitation> create(RecitationSubmission submission) async {
    saved = submission;

    return Recitation(
      id: 1,
      studentId: submission.studentId,
      studentName: 'أحمد',
      groupId: 1,
      groupName: 'حلقة النور',
      teacherName: 'خالد',
      recitationDate: submission.recitationDate,
      surahId: submission.surahId,
      surahName: 'الفاتحة',
      fromAyah: submission.fromAyah,
      toAyah: submission.toAyah,
    );
  }

  @override
  Future<List<Recitation>> list({
    int? studentId,
    int? groupId,
    String? date,
  }) async {
    return <Recitation>[];
  }

  @override
  Future<Recitation> update(
    int id, {
    RecitationGrade? grade,
    int? hifzErrors,
    int? tajweedErrors,
    int? readingErrors,
    String? notes,
  }) {
    throw UnimplementedError();
  }

  @override
  Future<void> delete(int id) async {}
}

void main() {
  testWidgets('requires a surah before saving', (WidgetTester tester) async {
    final _FakeRecitationRepository fake = _FakeRecitationRepository();

    await tester.pumpWidget(
      ProviderScope(
        overrides: <Override>[
          recitationRepositoryProvider.overrideWithValue(fake),
          surahsProvider.overrideWith(
            (Ref ref) async => <Surah>[
              const Surah(
                id: 1,
                nameAr: 'الفاتحة',
                ayahCount: 7,
                pageStart: 1,
                pageEnd: 1,
              ),
            ],
          ),
        ],
        child: const MaterialApp(
          home: RecitationPage(studentId: 1, studentName: 'أحمد'),
        ),
      ),
    );

    await tester.pumpAndSettle();

    expect(find.text('أحمد'), findsOneWidget);
    expect(find.text('ممتاز'), findsOneWidget);
    expect(find.text('يحتاج مراجعة'), findsOneWidget);

    await tester.tap(find.text('حفظ'));
    await tester.pump();

    expect(find.text('اختر السورة.'), findsOneWidget);
    expect(fake.saved, isNull);
  });
}
