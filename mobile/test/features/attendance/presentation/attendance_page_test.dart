import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tahfeez/features/attendance/data/attendance_repository.dart';
import 'package:tahfeez/features/attendance/domain/attendance.dart';
import 'package:tahfeez/features/attendance/domain/attendance_status.dart';
import 'package:tahfeez/features/attendance/presentation/attendance_page.dart';

class _FakeAttendanceRepository implements AttendanceRepository {
  List<AttendanceSubmission>? savedRecords;

  @override
  Future<GroupAttendanceSheet> sheet(int groupId, {String? date}) async {
    return GroupAttendanceSheet(
      groupId: groupId,
      groupName: 'حلقة النور',
      teacherName: 'أستاذ خالد',
      date: date ?? '2026-10-31',
      students: const <AttendanceSheetRow>[
        AttendanceSheetRow(studentId: 1, fullName: 'أحمد'),
        AttendanceSheetRow(studentId: 2, fullName: 'خالد'),
      ],
    );
  }

  @override
  Future<GroupAttendanceSheet> saveSheet(
    int groupId,
    String date,
    List<AttendanceSubmission> records,
  ) async {
    savedRecords = records;

    return sheet(groupId, date: date);
  }

  @override
  Future<AttendanceRecord> update(
    int attendanceId, {
    AttendanceStatus? status,
    int? lateMinutes,
    String? notes,
  }) {
    throw UnimplementedError();
  }
}

void main() {
  testWidgets('renders the sheet and defaults every student to present', (
    WidgetTester tester,
  ) async {
    final _FakeAttendanceRepository fake = _FakeAttendanceRepository();

    await tester.pumpWidget(
      ProviderScope(
        overrides: <Override>[
          attendanceRepositoryProvider.overrideWithValue(fake),
        ],
        child: const MaterialApp(
          home: AttendancePage(groupId: 1, initialDate: '2026-10-31'),
        ),
      ),
    );

    await tester.pumpAndSettle();

    expect(find.text('حلقة النور'), findsOneWidget);
    expect(find.text('أحمد'), findsOneWidget);
    expect(find.text('خالد'), findsOneWidget);
    expect(find.text('حاضر'), findsNWidgets(2));
  });

  testWidgets('saves the selected statuses in one call', (
    WidgetTester tester,
  ) async {
    final _FakeAttendanceRepository fake = _FakeAttendanceRepository();

    await tester.pumpWidget(
      ProviderScope(
        overrides: <Override>[
          attendanceRepositoryProvider.overrideWithValue(fake),
        ],
        child: const MaterialApp(
          home: AttendancePage(groupId: 1, initialDate: '2026-10-31'),
        ),
      ),
    );

    await tester.pumpAndSettle();

    await tester.tap(find.text('غائب').first);
    await tester.pump();

    await tester.tap(find.text('حفظ الحضور'));
    await tester.pumpAndSettle();

    expect(fake.savedRecords, isNotNull);
    expect(fake.savedRecords, hasLength(2));
    expect(fake.savedRecords!.first.status, AttendanceStatus.unexcused);
    expect(fake.savedRecords!.last.status, AttendanceStatus.present);
  });
}
