import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tahfeez/core/network/api_client.dart';
import 'package:tahfeez/features/groups/data/groups_repository.dart';
import 'package:tahfeez/features/groups/domain/group.dart';

import '../../../helpers/fake_http_adapter.dart';

void main() {
  late ApiClient api;
  late GroupsRepository repository;

  setUp(() {
    api = ApiClient();
    repository = GroupsRepository(api);
  });

  tearDown(() => api.close());

  test('lists groups', () async {
    api.dio.httpClientAdapter = FakeHttpAdapter((RequestOptions options) async {
      expect(options.path, '/groups');
      expect(options.method, 'GET');

      return FakeHttpAdapter.json(<String, dynamic>{
        'data': <dynamic>[
          <String, dynamic>{
            'id': 1,
            'name': 'حلقة النور',
            'status': 'active',
            'teacher_id': 2,
            'teacher_name': 'خالد',
            'students_count': 3,
          },
        ],
      });
    });

    final List<Group> groups = await repository.list();

    expect(groups, hasLength(1));
    expect(groups.first.name, 'حلقة النور');
    expect(groups.first.studentsCount, 3);
  });

  test('shows a group with its students', () async {
    api.dio.httpClientAdapter = FakeHttpAdapter((RequestOptions options) async {
      expect(options.path, '/groups/9');

      return FakeHttpAdapter.json(<String, dynamic>{
        'data': <String, dynamic>{
          'id': 9,
          'name': 'حلقة الفجر',
          'status': 'active',
          'teacher_id': 2,
          'teacher_name': 'خالد',
          'students_count': 1,
          'students': <dynamic>[
            <String, dynamic>{'id': 5, 'full_name': 'أحمد', 'status': 'active'},
          ],
        },
      });
    });

    final Group group = await repository.show(9);

    expect(group.id, 9);
    expect(group.students, hasLength(1));
    expect(group.students.first.fullName, 'أحمد');
  });

  test('creates a group with a teacher', () async {
    api.dio.httpClientAdapter = FakeHttpAdapter((RequestOptions options) async {
      expect(options.path, '/groups');
      expect(options.method, 'POST');

      final Map<String, dynamic> body = options.data as Map<String, dynamic>;
      expect(body['name'], 'حلقة جديدة');
      expect(body['teacher_id'], 4);

      return FakeHttpAdapter.json(<String, dynamic>{
        'data': <String, dynamic>{
          'id': 10,
          'name': 'حلقة جديدة',
          'status': 'active',
          'teacher_id': 4,
          'teacher_name': 'سارة',
          'students_count': 0,
        },
      }, statusCode: 201);
    });

    final Group group = await repository.create(
      name: 'حلقة جديدة',
      teacherId: 4,
    );

    expect(group.id, 10);
    expect(group.teacherId, 4);
  });

  test('adds a student and returns the updated group', () async {
    api.dio.httpClientAdapter = FakeHttpAdapter((RequestOptions options) async {
      expect(options.path, '/groups/10/students');
      expect(options.method, 'POST');
      expect((options.data as Map<String, dynamic>)['student_id'], 5);

      return FakeHttpAdapter.json(<String, dynamic>{
        'data': <String, dynamic>{
          'id': 10,
          'name': 'حلقة جديدة',
          'status': 'active',
          'teacher_id': 4,
          'teacher_name': 'سارة',
          'students_count': 1,
          'students': <dynamic>[
            <String, dynamic>{'id': 5, 'full_name': 'أحمد', 'status': 'active'},
          ],
        },
      });
    });

    final Group group = await repository.addStudent(10, 5);

    expect(group.studentsCount, 1);
    expect(group.students.single.id, 5);
  });

  test('removes a student', () async {
    api.dio.httpClientAdapter = FakeHttpAdapter((RequestOptions options) async {
      expect(options.path, '/groups/10/students/5');
      expect(options.method, 'DELETE');

      return ResponseBody.fromString('', 204);
    });

    await repository.removeStudent(10, 5);
  });
}
