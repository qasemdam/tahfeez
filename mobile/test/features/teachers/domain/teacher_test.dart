import 'package:flutter_test/flutter_test.dart';
import 'package:tahfeez/features/teachers/domain/teacher.dart';

void main() {
  test('parses the teacher payload', () {
    final Teacher teacher = Teacher.fromJson(<String, dynamic>{
      'id': 4,
      'name': 'أستاذ خالد',
      'email': 'k@example.test',
      'is_active': true,
    });

    expect(teacher.id, 4);
    expect(teacher.name, 'أستاذ خالد');
    expect(teacher.email, 'k@example.test');
    expect(teacher.isActive, isTrue);
  });

  test('falls back safely when fields are missing', () {
    final Teacher teacher = Teacher.fromJson(<String, dynamic>{'id': 1});

    expect(teacher.name, '');
    expect(teacher.email, '');
    expect(teacher.isActive, isTrue);
  });
}
