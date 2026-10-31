import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tahfeez/features/groups/data/groups_repository.dart';
import 'package:tahfeez/features/groups/domain/group.dart';
import 'package:tahfeez/features/groups/presentation/groups_page.dart';

void main() {
  testWidgets('renders the group list', (WidgetTester tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: <Override>[
          groupsProvider.overrideWith(
            (Ref ref) async => <Group>[
              const Group(
                id: 1,
                name: 'حلقة النور',
                status: 'active',
                teacherId: 2,
                teacherName: 'أستاذ خالد',
                studentsCount: 3,
              ),
            ],
          ),
        ],
        child: const MaterialApp(home: GroupsPage()),
      ),
    );

    await tester.pumpAndSettle();

    expect(find.text('حلقة النور'), findsOneWidget);
    expect(find.textContaining('3 طالب'), findsOneWidget);
  });

  testWidgets('shows an empty state when there are no groups', (
    WidgetTester tester,
  ) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: <Override>[
          groupsProvider.overrideWith((Ref ref) async => <Group>[]),
        ],
        child: const MaterialApp(home: GroupsPage()),
      ),
    );

    await tester.pumpAndSettle();

    expect(find.text('لا توجد حلقات بعد'), findsOneWidget);
  });
}
