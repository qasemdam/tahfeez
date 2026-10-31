import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tahfeez/core/network/api_client.dart';
import 'package:tahfeez/features/memorization/data/memorization_repository.dart';
import 'package:tahfeez/features/memorization/domain/memorization.dart';

import '../../../helpers/fake_http_adapter.dart';

void main() {
  late ApiClient api;
  late MemorizationRepository repository;

  setUp(() {
    api = ApiClient();
    repository = MemorizationRepository(api);
  });

  tearDown(() => api.close());

  test('creates a manual record', () async {
    api.dio.httpClientAdapter = FakeHttpAdapter((RequestOptions options) async {
      expect(options.path, '/memorization');
      expect(options.method, 'POST');

      final Map<String, dynamic> body = options.data as Map<String, dynamic>;
      expect(body['amount_source'], 'MANUAL');
      expect(body['amount_pages'], '1.5');
      expect(body.containsKey('surah_id'), isFalse);

      return FakeHttpAdapter.json(<String, dynamic>{
        'data': <String, dynamic>{
          'id': 1,
          'student_id': 2,
          'student_name': 'أحمد',
          'group_id': 1,
          'group_name': 'حلقة النور',
          'teacher_name': 'خالد',
          'memorization_date': '2026-10-31',
          'amount_pages': '1.5000',
          'amount_source': 'MANUAL',
        },
      }, statusCode: 201);
    });

    final Memorization record = await repository.create(
      const MemorizationSubmission(
        studentId: 2,
        memorizationDate: '2026-10-31',
        amountSource: AmountSource.manual,
        amountPages: '1.5',
      ),
    );

    expect(record.id, 1);
    expect(record.amountPages, '1.5000');
  });

  test('creates a calculated record', () async {
    api.dio.httpClientAdapter = FakeHttpAdapter((RequestOptions options) async {
      final Map<String, dynamic> body = options.data as Map<String, dynamic>;
      expect(body['amount_source'], 'CALCULATED');
      expect(body['surah_id'], 2);
      expect(body['from_ayah'], 1);
      expect(body['to_ayah'], 5);
      expect(body.containsKey('amount_pages'), isFalse);

      return FakeHttpAdapter.json(<String, dynamic>{
        'data': <String, dynamic>{
          'id': 2,
          'student_id': 2,
          'student_name': 'أحمد',
          'group_id': 1,
          'group_name': 'حلقة النور',
          'teacher_name': 'خالد',
          'memorization_date': '2026-10-31',
          'amount_pages': '0.7333',
          'amount_source': 'CALCULATED',
          'surah_id': 2,
          'surah_name': 'البقرة',
        },
      }, statusCode: 201);
    });

    final Memorization record = await repository.create(
      const MemorizationSubmission(
        studentId: 2,
        memorizationDate: '2026-10-31',
        amountSource: AmountSource.calculated,
        surahId: 2,
        fromAyah: 1,
        toAyah: 5,
      ),
    );

    expect(record.amountSource, AmountSource.calculated);
    expect(record.surahName, 'البقرة');
  });

  test('lists a student records', () async {
    api.dio.httpClientAdapter = FakeHttpAdapter((RequestOptions options) async {
      expect(options.path, '/memorization');
      expect(options.queryParameters['student_id'], 2);

      return FakeHttpAdapter.json(<String, dynamic>{'data': <dynamic>[]});
    });

    expect(await repository.list(studentId: 2), isEmpty);
  });

  test('updates grade and notes', () async {
    api.dio.httpClientAdapter = FakeHttpAdapter((RequestOptions options) async {
      expect(options.path, '/memorization/9');
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
          'memorization_date': '2026-10-31',
          'amount_pages': '1.0000',
          'amount_source': 'MANUAL',
          'grade': 'good',
          'notes': 'تصحيح',
        },
      });
    });

    final Memorization record = await repository.update(
      9,
      grade: RecitationGrade.good,
      notes: 'تصحيح',
    );

    expect(record.grade, RecitationGrade.good);
  });

  test('deletes a record', () async {
    api.dio.httpClientAdapter = FakeHttpAdapter((RequestOptions options) async {
      expect(options.path, '/memorization/9');
      expect(options.method, 'DELETE');

      return ResponseBody.fromString('', 204);
    });

    await repository.delete(9);
  });
}
