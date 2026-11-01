import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tahfeez/core/domain/assessment.dart';
import 'package:tahfeez/features/quran/data/quran_repository.dart';
import 'package:tahfeez/features/quran/domain/surah.dart';
import 'package:tahfeez/features/review/data/review_repository.dart';
import 'package:tahfeez/features/review/domain/review.dart';
import 'package:tahfeez/features/review/presentation/review_page.dart';

class _FakeReviewRepository implements ReviewRepository {
  ReviewSubmission? saved;

  @override
  Future<Review> create(ReviewSubmission submission) async {
    saved = submission;

    return Review(
      id: 1,
      studentId: submission.studentId,
      studentName: 'أحمد',
      groupId: 1,
      groupName: 'حلقة النور',
      teacherName: 'خالد',
      reviewDate: submission.reviewDate,
      amountPages: submission.amountPages ?? '0',
      amountSource: submission.amountSource,
    );
  }

  @override
  Future<List<Review>> list({
    int? studentId,
    int? groupId,
    String? date,
  }) async {
    return <Review>[];
  }

  @override
  Future<Review> update(
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
  testWidgets('calculated mode requires a surah', (WidgetTester tester) async {
    final _FakeReviewRepository fake = _FakeReviewRepository();

    await tester.pumpWidget(
      ProviderScope(
        overrides: <Override>[
          reviewRepositoryProvider.overrideWithValue(fake),
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
          home: ReviewPage(studentId: 1, studentName: 'أحمد'),
        ),
      ),
    );

    await tester.pumpAndSettle();

    expect(find.text('أحمد'), findsOneWidget);

    await tester.tap(find.text('حفظ'));
    await tester.pump();

    expect(find.text('اختر السورة.'), findsOneWidget);
    expect(fake.saved, isNull);
  });

  testWidgets('manual mode saves the amount', (WidgetTester tester) async {
    final _FakeReviewRepository fake = _FakeReviewRepository();

    await tester.pumpWidget(
      ProviderScope(
        overrides: <Override>[
          reviewRepositoryProvider.overrideWithValue(fake),
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
          home: ReviewPage(studentId: 1, studentName: 'أحمد'),
        ),
      ),
    );

    await tester.pumpAndSettle();

    await tester.tap(find.text('مقدار يدوي'));
    await tester.pump();

    await tester.enterText(find.byKey(const Key('review.amount')), '1.5');
    await tester.tap(find.text('حفظ'));
    await tester.pumpAndSettle();

    expect(fake.saved, isNotNull);
    expect(fake.saved!.amountPages, '1.5');
    expect(fake.saved!.amountSource, AmountSource.manual);
  });
}
