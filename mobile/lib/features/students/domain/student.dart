class Student {
  const Student({
    required this.id,
    required this.fullName,
    required this.status,
    this.gradeLevel,
    this.teacherName,
  });

  final int id;
  final String fullName;
  final String status;
  final String? gradeLevel;
  final String? teacherName;

  bool get isActive => status == 'active';

  factory Student.fromJson(Map<String, dynamic> json) {
    return Student(
      id: json['id'] as int,
      fullName: json['full_name'] as String? ?? '',
      status: json['status'] as String? ?? 'active',
      gradeLevel: json['grade_level'] as String?,
      teacherName: json['teacher_name'] as String?,
    );
  }
}
