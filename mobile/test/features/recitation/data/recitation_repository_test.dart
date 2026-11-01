import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tahfeez/core/domain/assessment.dart';
import 'package:tahfeez/core/network/api_client.dart';
import 'package:tahfeez/features/recitation/data/recitation_repository.dart';
import 'package:tahfeez/features/recitation/domain/recitation.dart';

import '../../../helpers/fake_http_adapter.dart';

void main() {
  late ApiClient api;
  late RecitationRepository repository;

  setUp(() {
    api = ApiClient();
    repository = RecitationRepository(api);
  });

  tearDown(() => api.close());

  test('creates a recitation', () async {
    api.dio.httpClientAdapter = FakeHttpAdapter((RequestOptions options) async {
      expect(options.path, '/recitations');
      expect(options.method, 'POST');

      final Map<String, dynamic> body = options.data as Map<String, dynamic>;
      expect(body['recitation_date'], '2026-10-31');
      expect(body['surah_id'], 1);
      expect(body['from_ayah'], 1);
      expect(body['to_ayah'], 7);
      expect(body.containsKey('amount_pages'), isFalse);

      return FakeHttpAdapter.json(<String, dynamic>{
        'data': <String, dynamic>{
          'id': 1,
          'student_id': 2,
          'student_name': 'أحمد',
          'group_id': 1,
          'group_name': 'حلقة النور',
          'teacher_name': 'خالد',
          'recitation_date': '2026-10-31',
          'surah_id': 1,
          'surah_name': 'الفاتحة',
          'from_ayah': 1,
          'to_ayah': 7,
        },
      }, statusCode: 201);
    });

    final Recitation record = await repository.create(
      const RecitationSubmission(
        studentId: 2,
        recitationDate: '2026-10-31',
        surahId: 1,
        fromAyah: 1,
        toAyah: 7,
      ),
    );

    expect(record.surahName, 'الفاتحة');
    expect(record.fromAyah, 1);
  });

  test('lists a student recitations', () async {
    api.dio.httpClientAdapter = FakeHttpAdapter((RequestOptions options) async {
      expect(options.path, '/recitations');
      expect(options.queryParameters['student_id'], 2);

      return FakeHttpAdapter.json(<String, dynamic>{'data': <dynamic>[]});
    });

    expect(await repository.list(studentId: 2), isEmpty);
  });

  test('updates grade and notes', () async {
    api.dio.httpClientAdapter = FakeHttpAdapter((RequestOptions options) async {
      expect(options.path, '/recitations/9');
      expect(options.method, 'PATCH');

      final Map<String, dynamic> body = options.data as Map<String, dynamic>;
      expect(body['grade'], 'good');
      expect(body['notes'], 'تصحيح');

      return FakeHttpAdapter.json(<String, dynamic>{
        'data': <String, dynamic>{
          'id': 9,
          'student_id': 2,
          'student_name': 'أحمد',
          'group_id': 1,
          'group_name': 'حلقة النور',
          'teacher_name': 'خالد',
          'recitation_date': '2026-10-31',
          'surah_id': 1,
          'surah_name': 'الفاتحة',
          'from_ayah': 1,
          'to_ayah': 7,
          'grade': 'good',
          'notes': 'تصحيح',
        },
      });
    });

    final Recitation record = await repository.update(
      9,
      grade: RecitationGrade.good,
      notes: 'تصحيح',
    );

    expect(record.grade, RecitationGrade.good);
  });

  test('deletes a recitation', () async {
    api.dio.httpClientAdapter = FakeHttpAdapter((RequestOptions options) async {
      expect(options.path, '/recitations/9');
      expect(options.method, 'DELETE');

      return ResponseBody.fromString('', 204);
    });

    await repository.delete(9);
  });
}
