import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tahfeez/features/memorization/data/memorization_repository.dart';
import 'package:tahfeez/features/memorization/domain/memorization.dart';
import 'package:tahfeez/features/memorization/presentation/memorization_page.dart';
import 'package:tahfeez/features/quran/data/quran_repository.dart';
import 'package:tahfeez/features/quran/domain/surah.dart';

class _FakeMemorizationRepository implements MemorizationRepository {
  MemorizationSubmission? saved;

  @override
  Future<Memorization> create(MemorizationSubmission submission) async {
    saved = submission;

    return Memorization(
      id: 1,
      studentId: submission.studentId,
      studentName: 'أحمد',
      groupId: 1,
      groupName: 'حلقة النور',
      teacherName: 'خالد',
      memorizationDate: submission.memorizationDate,
      amountPages: submission.amountPages ?? '0',
      amountSource: submission.amountSource,
    );
  }

  @override
  Future<List<Memorization>> list({
    int? studentId,
    int? groupId,
    String? date,
  }) async {
    return <Memorization>[];
  }

  @override
  Future<Memorization> update(
    int id, {
    String? amountPages,
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
  testWidgets('simple mode saves a manual amount', (WidgetTester tester) async {
    final _FakeMemorizationRepository fake = _FakeMemorizationRepository();

    await tester.pumpWidget(
      ProviderScope(
        overrides: <Override>[
          memorizationRepositoryProvider.overrideWithValue(fake),
        ],
        child: const MaterialApp(
          home: MemorizationPage(studentId: 1, studentName: 'أحمد'),
        ),
      ),
    );

    await tester.pumpAndSettle();

    expect(find.text('أحمد'), findsOneWidget);

    await tester.enterText(find.byType(TextFormField).first, '1.5');
    await tester.tap(find.text('حفظ'));
    await tester.pumpAndSettle();

    expect(fake.saved, isNotNull);
    expect(fake.saved!.amountPages, '1.5');
    expect(fake.saved!.amountSource, AmountSource.manual);
  });

  testWidgets('professional mode requires a surah in calculated mode', (
    WidgetTester tester,
  ) async {
    final _FakeMemorizationRepository fake = _FakeMemorizationRepository();

    await tester.pumpWidget(
      ProviderScope(
        overrides: <Override>[
          memorizationRepositoryProvider.overrideWithValue(fake),
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
          home: MemorizationPage(studentId: 1, studentName: 'أحمد'),
        ),
      ),
    );

    await tester.pumpAndSettle();

    await tester.tap(find.text('احترافي'));
    await tester.pumpAndSettle();

    await tester.tap(find.text('حساب تلقائي'));
    await tester.pump();

    await tester.tap(find.text('حفظ'));
    await tester.pump();

    expect(find.text('اختر السورة.'), findsOneWidget);
    expect(fake.saved, isNull);
  });
}
