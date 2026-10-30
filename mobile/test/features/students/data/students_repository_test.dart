import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tahfeez/core/network/api_client.dart';
import 'package:tahfeez/features/students/data/students_repository.dart';
import 'package:tahfeez/features/students/domain/student.dart';

import '../../../helpers/fake_http_adapter.dart';

void main() {
  late ApiClient api;

  setUp(() => api = ApiClient());
  tearDown(() => api.close());

  test('lists students from the paginated payload', () async {
    api.dio.httpClientAdapter = FakeHttpAdapter((RequestOptions options) async {
      expect(options.path, '/students');
      expect(options.queryParameters, isEmpty);

      return FakeHttpAdapter.json(<String, dynamic>{
        'data': <dynamic>[
          <String, dynamic>{'id': 1, 'full_name': 'أحمد', 'status': 'active'},
          <String, dynamic>{'id': 2, 'full_name': 'سارة', 'status': 'inactive'},
        ],
      });
    });

    final List<Student> students = await StudentsRepository(api).list();

    expect(students, hasLength(2));
    expect(students.first.fullName, 'أحمد');
    expect(students.first.isActive, isTrue);
    expect(students.last.isActive, isFalse);
  });

  test('passes the search term as a query parameter', () async {
    api.dio.httpClientAdapter = FakeHttpAdapter((RequestOptions options) async {
      expect(options.queryParameters['search'], 'سعيد');

      return FakeHttpAdapter.json(<String, dynamic>{'data': <dynamic>[]});
    });

    final List<Student> students = await StudentsRepository(api)
        .list(search: 'سعيد');

    expect(students, isEmpty);
  });
}
