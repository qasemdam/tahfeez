import 'package:flutter_test/flutter_test.dart';
import 'package:tahfeez/features/groups/domain/group.dart';

void main() {
  test('parses a list row', () {
    final Group group = Group.fromJson(<String, dynamic>{
      'id': 3,
      'name': 'حلقة النور',
      'status': 'active',
      'teacher_id': 7,
      'teacher_name': 'أستاذ خالد',
      'students_count': 12,
    });

    expect(group.id, 3);
    expect(group.name, 'حلقة النور');
    expect(group.isActive, isTrue);
    expect(group.teacherId, 7);
    expect(group.teacherName, 'أستاذ خالد');
    expect(group.studentsCount, 12);
    expect(group.students, isEmpty);
  });

  test('parses the detail payload with students', () {
    final Group group = Group.fromJson(<String, dynamic>{
      'id': 4,
      'name': 'حلقة الفجر',
      'status': 'inactive',
      'teacher_id': 8,
      'teacher_name': 'أستاذة سارة',
      'students_count': 2,
      'students': <dynamic>[
        <String, dynamic>{
          'id': 1,
          'full_name': 'أحمد',
          'status': 'active',
          'grade_level': '5',
          'joined_at': '2026-10-01',
        },
        <String, dynamic>{'id': 2, 'full_name': 'خالد', 'status': 'inactive'},
      ],
    });

    expect(group.isActive, isFalse);
    expect(group.students, hasLength(2));
    expect(group.students.first.fullName, 'أحمد');
    expect(group.students.first.gradeLevel, '5');
    expect(group.students.first.joinedAt, '2026-10-01');
    expect(group.students.last.isActive, isFalse);
  });

  test('falls back safely when fields are missing', () {
    final Group group = Group.fromJson(<String, dynamic>{'id': 1});

    expect(group.name, '');
    expect(group.status, 'active');
    expect(group.teacherId, 0);
    expect(group.teacherName, '');
    expect(group.studentsCount, 0);
    expect(group.students, isEmpty);
  });
}
