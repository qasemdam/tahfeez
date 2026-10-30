import 'package:flutter_test/flutter_test.dart';
import 'package:tahfeez/features/auth/domain/session.dart';

void main() {
  group('AppUser', () {
    test('parses the API payload', () {
      final AppUser user = AppUser.fromJson(<String, dynamic>{
        'id': 7,
        'name': 'أستاذ خالد',
        'email': 'k@example.test',
        'role': 'center_manager',
        'center_id': 3,
        'center_name': 'مركز النور',
      });

      expect(user.id, 7);
      expect(user.name, 'أستاذ خالد');
      expect(user.role, 'center_manager');
      expect(user.centerId, 3);
      expect(user.centerName, 'مركز النور');
      expect(user.isManager, isTrue);
      expect(user.isTeacher, isFalse);
    });

    test('falls back safely when optional fields are missing', () {
      final AppUser user = AppUser.fromJson(<String, dynamic>{'id': 1});

      expect(user.name, '');
      expect(user.email, '');
      expect(user.role, 'teacher');
      expect(user.centerId, 0);
      expect(user.centerName, '');
      expect(user.isTeacher, isTrue);
      expect(user.isManager, isFalse);
    });

    test('round-trips through JSON', () {
      const AppUser user = AppUser(
        id: 2,
        name: 'سارة',
        email: 's@example.test',
        role: 'teacher',
        centerId: 1,
        centerName: 'مركز',
      );

      final AppUser restored = AppUser.fromJson(user.toJson());

      expect(restored.id, user.id);
      expect(restored.name, user.name);
      expect(restored.email, user.email);
      expect(restored.role, user.role);
      expect(restored.centerId, user.centerId);
      expect(restored.centerName, user.centerName);
    });
  });

  group('Session', () {
    test('round-trips through JSON', () {
      const Session session = Session(
        token: 'abc123',
        user: AppUser(
          id: 1,
          name: 'نور',
          email: 'n@example.test',
          role: 'teacher',
          centerId: 1,
          centerName: 'مركز',
        ),
      );

      final Session restored = Session.fromJson(session.toJson());

      expect(restored.token, 'abc123');
      expect(restored.user.id, 1);
      expect(restored.user.name, 'نور');
    });
  });
}
