import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:tahfeez/core/network/api_client.dart';
import 'package:tahfeez/features/attendance/data/attendance_repository.dart';
import 'package:tahfeez/features/attendance/domain/attendance.dart';
import 'package:tahfeez/features/attendance/domain/attendance_status.dart';

import '../../../helpers/fake_http_adapter.dart';

void main() {
  late ApiClient api;
  late AttendanceRepository repository;

  setUp(() {
    api = ApiClient();
    repository = AttendanceRepository(api);
  });

  tearDown(() => api.close());

  test('loads a group sheet for a date', () async {
    api.dio.httpClientAdapter = FakeHttpAdapter((RequestOptions options) async {
      expect(options.path, '/groups/2/attendance');
      expect(options.queryParameters['date'], '2026-10-31');

      return FakeHttpAdapter.json(<String, dynamic>{
        'data': <String, dynamic>{
          'group_id': 2,
          'group_name': 'حلقة النور',
          'teacher_name': 'خالد',
          'date': '2026-10-31',
          'students': <dynamic>[
            <String, dynamic>{
              'student_id': 3,
              'full_name': 'أحمد',
              'status': null,
            },
          ],
        },
      });
    });

    final GroupAttendanceSheet sheet = await repository.sheet(
      2,
      date: '2026-10-31',
    );

    expect(sheet.groupId, 2);
    expect(sheet.students.single.fullName, 'أحمد');
    expect(sheet.students.single.status, isNull);
  });

  test('saves a sheet with all records', () async {
    api.dio.httpClientAdapter = FakeHttpAdapter((RequestOptions options) async {
      expect(options.path, '/groups/2/attendance');
      expect(options.method, 'POST');

      final Map<String, dynamic> body = options.data as Map<String, dynamic>;
      expect(body['attendance_date'], '2026-10-31');

      final List<dynamic> records = body['records'] as List<dynamic>;
      expect(records, hasLength(2));
      expect((records.first as Map<String, dynamic>)['status'], 'present');

      return FakeHttpAdapter.json(<String, dynamic>{
        'data': <String, dynamic>{
          'group_id': 2,
          'group_name': 'حلقة النور',
          'teacher_name': 'خالد',
          'date': '2026-10-31',
          'students': <dynamic>[],
        },
      });
    });

    await repository.saveSheet(2, '2026-10-31', <AttendanceSubmission>[
      const AttendanceSubmission(
        studentId: 3,
        status: AttendanceStatus.present,
      ),
      const AttendanceSubmission(
        studentId: 4,
        status: AttendanceStatus.unexcused,
      ),
    ]);
  });

  test('updates a single record', () async {
    api.dio.httpClientAdapter = FakeHttpAdapter((RequestOptions options) async {
      expect(options.path, '/attendance/9');
      expect(options.method, 'PATCH');

      final Map<String, dynamic> body = options.data as Map<String, dynamic>;
      expect(body['status'], 'late');
      expect(body['late_minutes'], 15);

      return FakeHttpAdapter.json(<String, dynamic>{
        'data': <String, dynamic>{
          'id': 9,
          'student_id': 3,
          'student_name': 'أحمد',
          'group_id': 2,
          'group_name': 'حلقة النور',
          'attendance_date': '2026-10-31',
          'status': 'late',
          'late_minutes': 15,
        },
      });
    });

    final AttendanceRecord record = await repository.update(
      9,
      status: AttendanceStatus.late,
      lateMinutes: 15,
    );

    expect(record.status, AttendanceStatus.late);
    expect(record.lateMinutes, 15);
  });
}
