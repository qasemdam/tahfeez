import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tahfeez/features/weekly_evaluation/data/weekly_evaluation_repository.dart';
import 'package:tahfeez/features/weekly_evaluation/domain/weekly_evaluation.dart';
import 'package:tahfeez/features/weekly_evaluation/presentation/weekly_evaluation_page.dart';

WeeklyEvaluation _evaluation({
  double achievement = 200,
  double? weighted = 87.5,
  String status = 'computed',
}) {
  return WeeklyEvaluation(
    id: 5,
    studentId: 2,
    studentName: 'أحمد',
    groupId: 1,
    groupName: 'حلقة النور',
    teacherName: 'خالد',
    weekKey: '2026-10-24',
    weekStartDate: '2026-10-24',
    weekEndDate: '2026-10-30',
    startDay: 'saturday',
    profileVersion: 3,
    requiredPages: 1,
    actualPages: 2,
    achievementPercentage: achievement,
    weightedScore: weighted,
    isComplete: true,
    status: status,
    blockedCriteria: const <String>[],
    components: const <EvaluationComponent>[],
  );
}

class _FakeWeeklyEvaluationRepository implements WeeklyEvaluationRepository {
  _FakeWeeklyEvaluationRepository(this.rows);

  final List<WeeklyEvaluation> rows;
  WeeklyEvaluationSubmission? created;

  @override
  Future<List<WeeklyEvaluation>> list({
    int? studentId,
    int? groupId,
    String? weekKey,
    String? weekStartDate,
  }) async {
    return rows;
  }

  @override
  Future<WeeklyEvaluation> show(int id) async => rows.first;

  @override
  Future<WeeklyEvaluation> create(WeeklyEvaluationSubmission submission) async {
    created = submission;

    return rows.isEmpty ? _evaluation() : rows.first;
  }

  @override
  Future<WeeklyEvaluation> approve(int id) async => rows.first;
}

Future<void> _pump(
  WidgetTester tester,
  _FakeWeeklyEvaluationRepository fake,
) async {
  await tester.pumpWidget(
    ProviderScope(
      overrides: <Override>[
        weeklyEvaluationRepositoryProvider.overrideWithValue(fake),
      ],
      child: const MaterialApp(
        home: WeeklyEvaluationPage(studentId: 2, studentName: 'أحمد'),
      ),
    ),
  );

  await tester.pumpAndSettle();
}

void main() {
  testWidgets('shows achievement above 100 and the frozen week', (
    WidgetTester tester,
  ) async {
    final _FakeWeeklyEvaluationRepository fake =
        _FakeWeeklyEvaluationRepository(<WeeklyEvaluation>[_evaluation()]);

    await _pump(tester, fake);

    expect(find.text('من 2026-10-24 إلى 2026-10-30'), findsOneWidget);
    expect(find.text('200%'), findsOneWidget);
    expect(find.text('87.5'), findsOneWidget);
    expect(find.text('محسوب'), findsOneWidget);
  });

  testWidgets('marks an incomplete evaluation as not computed', (
    WidgetTester tester,
  ) async {
    final _FakeWeeklyEvaluationRepository fake =
        _FakeWeeklyEvaluationRepository(<WeeklyEvaluation>[
          _evaluation(weighted: null),
        ]);

    await _pump(tester, fake);

    expect(find.text('غير محسوبة'), findsOneWidget);
  });

  testWidgets('computes a week through the repository', (
    WidgetTester tester,
  ) async {
    final _FakeWeeklyEvaluationRepository fake =
        _FakeWeeklyEvaluationRepository(<WeeklyEvaluation>[_evaluation()]);

    await _pump(tester, fake);

    await tester.tap(find.text('احتساب تقييم الأسبوع'));
    await tester.pumpAndSettle();

    expect(fake.created, isNotNull);
    expect(fake.created!.studentId, 2);
    expect(fake.created!.weekDate, matches(RegExp(r'^\d{4}-\d{2}-\d{2}$')));
  });

  testWidgets('shows an empty state when nothing is stored', (
    WidgetTester tester,
  ) async {
    final _FakeWeeklyEvaluationRepository fake =
        _FakeWeeklyEvaluationRepository(<WeeklyEvaluation>[]);

    await _pump(tester, fake);

    expect(find.text('لا يوجد تقييم محفوظ لهذا الطالب بعد.'), findsOneWidget);
  });
}
