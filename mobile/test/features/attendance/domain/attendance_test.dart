import 'package:flutter_test/flutter_test.dart';
import 'package:tahfeez/features/attendance/domain/attendance.dart';
import 'package:tahfeez/features/attendance/domain/attendance_status.dart';

void main() {
  test('parses an attendance record', () {
    final AttendanceRecord record = AttendanceRecord.fromJson(<String, dynamic>{
      'id': 9,
      'student_id': 3,
      'student_name': 'أحمد',
      'group_id': 2,
      'group_name': 'حلقة النور',
      'attendance_date': '2026-10-31',
      'status': 'late',
      'late_minutes': 12,
      'notes': 'تأخر بسبب المواصلات',
    });

    expect(record.id, 9);
    expect(record.studentId, 3);
    expect(record.groupId, 2);
    expect(record.status, AttendanceStatus.late);
    expect(record.lateMinutes, 12);
    expect(record.notes, 'تأخر بسبب المواصلات');
  });

  test('parses a sheet with recorded and unrecorded rows', () {
    final GroupAttendanceSheet sheet = GroupAttendanceSheet.fromJson(
      <String, dynamic>{
        'group_id': 2,
        'group_name': 'حلقة النور',
        'teacher_name': 'أستاذ خالد',
        'date': '2026-10-31',
        'students': <dynamic>[
          <String, dynamic>{
            'student_id': 3,
            'full_name': 'أحمد',
            'attendance_id': 9,
            'status': 'present',
          },
          <String, dynamic>{
            'student_id': 4,
            'full_name': 'خالد',
            'status': null,
          },
        ],
      },
    );

    expect(sheet.students, hasLength(2));
    expect(sheet.students.first.status, AttendanceStatus.present);
    expect(sheet.students.first.attendanceId, 9);
    expect(sheet.students.last.status, isNull);
  });

  test('submission includes late minutes only for late', () {
    const AttendanceSubmission late = AttendanceSubmission(
      studentId: 3,
      status: AttendanceStatus.late,
      lateMinutes: 12,
      notes: 'ملاحظة',
    );

    expect(late.toJson(), <String, dynamic>{
      'student_id': 3,
      'status': 'late',
      'late_minutes': 12,
      'notes': 'ملاحظة',
    });

    const AttendanceSubmission present = AttendanceSubmission(
      studentId: 4,
      status: AttendanceStatus.present,
      lateMinutes: 12,
    );

    expect(present.toJson(), <String, dynamic>{
      'student_id': 4,
      'status': 'present',
    });
  });
}
