import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/providers.dart';
import '../domain/group.dart';

class GroupsRepository {
  GroupsRepository(this._api);

  final ApiClient _api;

  Future<List<Group>> list() async {
    final response = await _api.dio.get<Map<String, dynamic>>('/groups');
    final List<dynamic> rows = response.data!['data'] as List<dynamic>;

    return rows
        .map((dynamic row) => Group.fromJson(row as Map<String, dynamic>))
        .toList(growable: false);
  }

  Future<Group> show(int id) async {
    final response = await _api.dio.get<Map<String, dynamic>>('/groups/$id');

    return Group.fromJson(response.data!['data'] as Map<String, dynamic>);
  }

  Future<Group> create({required String name, required int teacherId}) async {
    final response = await _api.dio.post<Map<String, dynamic>>(
      '/groups',
      data: <String, dynamic>{'name': name, 'teacher_id': teacherId},
    );

    return Group.fromJson(response.data!['data'] as Map<String, dynamic>);
  }

  Future<Group> update(
    int id, {
    String? name,
    int? teacherId,
    String? status,
  }) async {
    final response = await _api.dio.patch<Map<String, dynamic>>(
      '/groups/$id',
      data: <String, dynamic>{
        if (name != null) 'name': name,
        if (teacherId != null) 'teacher_id': teacherId,
        if (status != null) 'status': status,
      },
    );

    return Group.fromJson(response.data!['data'] as Map<String, dynamic>);
  }

  Future<void> delete(int id) async {
    await _api.dio.delete<void>('/groups/$id');
  }

  Future<Group> addStudent(int groupId, int studentId) async {
    final response = await _api.dio.post<Map<String, dynamic>>(
      '/groups/$groupId/students',
      data: <String, dynamic>{'student_id': studentId},
    );

    return Group.fromJson(response.data!['data'] as Map<String, dynamic>);
  }

  Future<void> removeStudent(int groupId, int studentId) async {
    await _api.dio.delete<void>('/groups/$groupId/students/$studentId');
  }
}

final Provider<GroupsRepository> groupsRepositoryProvider =
    Provider<GroupsRepository>((Ref ref) {
      return GroupsRepository(ref.watch(apiClientProvider));
    });

final FutureProvider<List<Group>> groupsProvider = FutureProvider<List<Group>>((
  Ref ref,
) {
  return ref.watch(groupsRepositoryProvider).list();
});

final FutureProviderFamily<Group, int> groupDetailsProvider =
    FutureProvider.family<Group, int>((Ref ref, int id) {
      return ref.watch(groupsRepositoryProvider).show(id);
    });
