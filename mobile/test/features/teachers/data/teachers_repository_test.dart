import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tahfeez/core/network/api_client.dart';
import 'package:tahfeez/features/teachers/data/teachers_repository.dart';
import 'package:tahfeez/features/teachers/domain/teacher.dart';

import '../../../helpers/fake_http_adapter.dart';

void main() {
  late ApiClient api;

  setUp(() => api = ApiClient());
  tearDown(() => api.close());

  test('lists teachers', () async {
    api.dio.httpClientAdapter = FakeHttpAdapter((RequestOptions options) async {
      expect(options.path, '/teachers');

      return FakeHttpAdapter.json(<String, dynamic>{
        'data': <dynamic>[
          <String, dynamic>{
            'id': 1,
            'name': 'أستاذ خالد',
            'email': 'k@example.test',
            'is_active': true,
          },
        ],
      });
    });

    final List<Teacher> teachers = await TeachersRepository(api).list();

    expect(teachers, hasLength(1));
    expect(teachers.single.name, 'أستاذ خالد');
  });
}
