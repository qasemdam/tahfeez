import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tahfeez/core/domain/assessment.dart';
import 'package:tahfeez/core/network/api_client.dart';
import 'package:tahfeez/features/review/data/review_repository.dart';
import 'package:tahfeez/features/review/domain/review.dart';

import '../../../helpers/fake_http_adapter.dart';

void main() {
  late ApiClient api;
  late ReviewRepository repository;

  setUp(() {
    api = ApiClient();
    repository = ReviewRepository(api);
  });

  tearDown(() => api.close());

  test('creates a calculated review', () async {
    api.dio.httpClientAdapter = FakeHttpAdapter((RequestOptions options) async {
      expect(options.path, '/reviews');
      expect(options.method, 'POST');

      final Map<String, dynamic> body = options.data as Map<String, dynamic>;
      expect(body['review_date'], '2026-10-31');
      expect(body['amount_source'], 'CALCULATED');
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
          'review_date': '2026-10-31',
          'amount_pages': '1.0000',
          'amount_source': 'CALCULATED',
          'surah_id': 1,
          'surah_name': 'الفاتحة',
        },
      }, statusCode: 201);
    });

    final Review review = await repository.create(
      const ReviewSubmission(
        studentId: 2,
        reviewDate: '2026-10-31',
        amountSource: AmountSource.calculated,
        surahId: 1,
        fromAyah: 1,
        toAyah: 7,
      ),
    );

    expect(review.amountSource, AmountSource.calculated);
    expect(review.surahName, 'الفاتحة');
  });

  test('creates a manual review', () async {
    api.dio.httpClientAdapter = FakeHttpAdapter((RequestOptions options) async {
      final Map<String, dynamic> body = options.data as Map<String, dynamic>;
      expect(body['amount_source'], 'MANUAL');
      expect(body['amount_pages'], '1.5');

      return FakeHttpAdapter.json(<String, dynamic>{
        'data': <String, dynamic>{
          'id': 2,
          'student_id': 2,
          'student_name': 'أحمد',
          'group_id': 1,
          'group_name': 'حلقة النور',
          'teacher_name': 'خالد',
          'review_date': '2026-10-31',
          'amount_pages': '1.5000',
          'amount_source': 'MANUAL',
        },
      }, statusCode: 201);
    });

    final Review review = await repository.create(
      const ReviewSubmission(
        studentId: 2,
        reviewDate: '2026-10-31',
        amountSource: AmountSource.manual,
        amountPages: '1.5',
      ),
    );

    expect(review.amountPages, '1.5000');
  });

  test('lists a student reviews', () async {
    api.dio.httpClientAdapter = FakeHttpAdapter((RequestOptions options) async {
      expect(options.path, '/reviews');
      expect(options.queryParameters['student_id'], 2);

      return FakeHttpAdapter.json(<String, dynamic>{'data': <dynamic>[]});
    });

    expect(await repository.list(studentId: 2), isEmpty);
  });

  test('updates grade and notes', () async {
    api.dio.httpClientAdapter = FakeHttpAdapter((RequestOptions options) async {
      expect(options.path, '/reviews/9');
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
          'review_date': '2026-10-31',
          'amount_pages': '1.0000',
          'amount_source': 'MANUAL',
          'grade': 'good',
          'notes': 'تصحيح',
        },
      });
    });

    final Review review = await repository.update(
      9,
      grade: RecitationGrade.good,
      notes: 'تصحيح',
    );

    expect(review.grade, RecitationGrade.good);
  });

  test('deletes a review', () async {
    api.dio.httpClientAdapter = FakeHttpAdapter((RequestOptions options) async {
      expect(options.path, '/reviews/9');
      expect(options.method, 'DELETE');

      return ResponseBody.fromString('', 204);
    });

    await repository.delete(9);
  });
}
