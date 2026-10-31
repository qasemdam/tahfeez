class Group {
  const Group({
    required this.id,
    required this.name,
    required this.status,
    required this.teacherId,
    required this.teacherName,
    required this.studentsCount,
    this.students = const <GroupStudent>[],
  });

  final int id;
  final String name;
  final String status;
  final int teacherId;
  final String teacherName;
  final int studentsCount;
  final List<GroupStudent> students;

  bool get isActive => status == 'active';

  factory Group.fromJson(Map<String, dynamic> json) {
    final List<dynamic> rawStudents =
        (json['students'] as List<dynamic>?) ?? const <dynamic>[];

    return Group(
      id: json['id'] as int,
      name: json['name'] as String? ?? '',
      status: json['status'] as String? ?? 'active',
      teacherId: json['teacher_id'] as int? ?? 0,
      teacherName: json['teacher_name'] as String? ?? '',
      studentsCount: json['students_count'] as int? ?? rawStudents.length,
      students: rawStudents
          .map(
            (dynamic row) => GroupStudent.fromJson(row as Map<String, dynamic>),
          )
          .toList(growable: false),
    );
  }
}

class GroupStudent {
  const GroupStudent({
    required this.id,
    required this.fullName,
    required this.status,
    this.gradeLevel,
    this.joinedAt,
  });

  final int id;
  final String fullName;
  final String status;
  final String? gradeLevel;
  final String? joinedAt;

  bool get isActive => status == 'active';

  factory GroupStudent.fromJson(Map<String, dynamic> json) {
    return GroupStudent(
      id: json['id'] as int,
      fullName: json['full_name'] as String? ?? '',
      status: json['status'] as String? ?? 'active',
      gradeLevel: json['grade_level'] as String?,
      joinedAt: json['joined_at'] as String?,
    );
  }
}
