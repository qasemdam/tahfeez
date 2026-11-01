import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/providers.dart';
import '../domain/attendance.dart';
import '../domain/attendance_status.dart';

/// The sheet is keyed by group + date; a Dart record has structural equality,
/// so Riverpod caches it correctly.
typedef AttendanceSheetArgs = ({int groupId, String date});

class AttendanceRepository {
  AttendanceRepository(this._api);

  final ApiClient _api;

  Future<GroupAttendanceSheet> sheet(int groupId, {String? date}) async {
    final response = await _api.dio.get<Map<String, dynamic>>(
      '/groups/$groupId/attendance',
      queryParameters: <String, dynamic>{if (date != null) 'date': date},
    );

    return GroupAttendanceSheet.fromJson(
      response.data!['data'] as Map<String, dynamic>,
    );
  }

  Future<GroupAttendanceSheet> saveSheet(
    int groupId,
    String date,
    List<AttendanceSubmission> records,
  ) async {
    final response = await _api.dio.post<Map<String, dynamic>>(
      '/groups/$groupId/attendance',
      data: <String, dynamic>{
        'attendance_date': date,
        'records': records
            .map((AttendanceSubmission record) => record.toJson())
            .toList(growable: false),
      },
    );

    return GroupAttendanceSheet.fromJson(
      response.data!['data'] as Map<String, dynamic>,
    );
  }

  Future<AttendanceRecord> update(
    int attendanceId, {
    AttendanceStatus? status,
    int? lateMinutes,
    String? notes,
  }) async {
    final response = await _api.dio.patch<Map<String, dynamic>>(
      '/attendance/$attendanceId',
      data: <String, dynamic>{
        if (status != null) 'status': status.wire,
        if (lateMinutes != null) 'late_minutes': lateMinutes,
        if (notes != null) 'notes': notes,
      },
    );

    return AttendanceRecord.fromJson(
      response.data!['data'] as Map<String, dynamic>,
    );
  }
}

final Provider<AttendanceRepository> attendanceRepositoryProvider =
    Provider<AttendanceRepository>((Ref ref) {
      return AttendanceRepository(ref.watch(apiClientProvider));
    });

final FutureProviderFamily<GroupAttendanceSheet, AttendanceSheetArgs>
groupAttendanceSheetProvider =
    FutureProvider.family<GroupAttendanceSheet, AttendanceSheetArgs>((
      Ref ref,
      AttendanceSheetArgs args,
    ) {
      return ref
          .watch(attendanceRepositoryProvider)
          .sheet(args.groupId, date: args.date);
    });
