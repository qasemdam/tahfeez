import 'attendance_status.dart';

class AttendanceRecord {
  const AttendanceRecord({
    required this.id,
    required this.studentId,
    required this.studentName,
    required this.groupId,
    required this.groupName,
    required this.attendanceDate,
    required this.status,
    this.lateMinutes,
    this.notes,
  });

  final int id;
  final int studentId;
  final String studentName;
  final int groupId;
  final String groupName;
  final String attendanceDate;
  final AttendanceStatus status;
  final int? lateMinutes;
  final String? notes;

  factory AttendanceRecord.fromJson(Map<String, dynamic> json) {
    return AttendanceRecord(
      id: json['id'] as int,
      studentId: json['student_id'] as int? ?? 0,
      studentName: json['student_name'] as String? ?? '',
      groupId: json['group_id'] as int? ?? 0,
      groupName: json['group_name'] as String? ?? '',
      attendanceDate: json['attendance_date'] as String? ?? '',
      status:
          AttendanceStatus.fromWire(json['status'] as String?) ??
          AttendanceStatus.present,
      lateMinutes: json['late_minutes'] as int?,
      notes: json['notes'] as String?,
    );
  }
}

/// One row of the daily sheet. A null [status] means "not recorded yet"; the
/// UI defaults it to present but does not persist anything until save.
class AttendanceSheetRow {
  const AttendanceSheetRow({
    required this.studentId,
    required this.fullName,
    this.gradeLevel,
    this.attendanceId,
    this.status,
    this.lateMinutes,
    this.notes,
  });

  final int studentId;
  final String fullName;
  final String? gradeLevel;
  final int? attendanceId;
  final AttendanceStatus? status;
  final int? lateMinutes;
  final String? notes;

  factory AttendanceSheetRow.fromJson(Map<String, dynamic> json) {
    return AttendanceSheetRow(
      studentId: json['student_id'] as int,
      fullName: json['full_name'] as String? ?? '',
      gradeLevel: json['grade_level'] as String?,
      attendanceId: json['attendance_id'] as int?,
      status: AttendanceStatus.fromWire(json['status'] as String?),
      lateMinutes: json['late_minutes'] as int?,
      notes: json['notes'] as String?,
    );
  }
}

class GroupAttendanceSheet {
  const GroupAttendanceSheet({
    required this.groupId,
    required this.groupName,
    required this.teacherName,
    required this.date,
    required this.students,
  });

  final int groupId;
  final String groupName;
  final String teacherName;
  final String date;
  final List<AttendanceSheetRow> students;

  factory GroupAttendanceSheet.fromJson(Map<String, dynamic> json) {
    final List<dynamic> rawStudents =
        (json['students'] as List<dynamic>?) ?? const <dynamic>[];

    return GroupAttendanceSheet(
      groupId: json['group_id'] as int,
      groupName: json['group_name'] as String? ?? '',
      teacherName: json['teacher_name'] as String? ?? '',
      date: json['date'] as String? ?? '',
      students: rawStudents
          .map(
            (dynamic row) =>
                AttendanceSheetRow.fromJson(row as Map<String, dynamic>),
          )
          .toList(growable: false),
    );
  }
}

/// What the client sends when saving a sheet.
class AttendanceSubmission {
  const AttendanceSubmission({
    required this.studentId,
    required this.status,
    this.lateMinutes,
    this.notes,
  });

  final int studentId;
  final AttendanceStatus status;
  final int? lateMinutes;
  final String? notes;

  Map<String, dynamic> toJson() => <String, dynamic>{
    'student_id': studentId,
    'status': status.wire,
    if (status == AttendanceStatus.late && lateMinutes != null)
      'late_minutes': lateMinutes,
    if (notes != null && notes!.isNotEmpty) 'notes': notes,
  };
}
