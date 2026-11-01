import 'package:flutter_test/flutter_test.dart';
import 'package:tahfeez/features/students/domain/student.dart';

void main() {
  test('parses the API payload', () {
    final Student student = Student.fromJson(<String, dynamic>{
      'id': 11,
      'full_name': 'أحمد محمد',
      'status': 'active',
      'grade_level': '5',
      'teacher_name': 'أستاذ خالد',
    });

    expect(student.id, 11);
    expect(student.fullName, 'أحمد محمد');
    expect(student.isActive, isTrue);
    expect(student.gradeLevel, '5');
    expect(student.teacherName, 'أستاذ خالد');
  });

  test('treats a missing status as active and keeps optional fields null', () {
    final Student student = Student.fromJson(<String, dynamic>{'id': 1});

    expect(student.fullName, '');
    expect(student.isActive, isTrue);
    expect(student.gradeLevel, isNull);
    expect(student.teacherName, isNull);
  });

  test('an inactive student is not active', () {
    final Student student = Student.fromJson(<String, dynamic>{
      'id': 2,
      'status': 'inactive',
    });

    expect(student.isActive, isFalse);
  });
}
