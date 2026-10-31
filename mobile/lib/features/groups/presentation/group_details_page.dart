import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_client.dart';
import '../../auth/domain/session.dart';
import '../../auth/presentation/auth_controller.dart';
import '../../students/data/students_repository.dart';
import '../../students/domain/student.dart';
import '../data/groups_repository.dart';
import '../domain/group.dart';

class GroupDetailsPage extends ConsumerWidget {
  const GroupDetailsPage({super.key, required this.groupId});

  final int groupId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AsyncValue<Group> group = ref.watch(groupDetailsProvider(groupId));
    final AppUser? user = ref.watch(authControllerProvider).valueOrNull?.user;
    final bool canManage = user?.isManager ?? false;

    return Scaffold(
      appBar: AppBar(
        title: const Text('تفاصيل الحلقة'),
        actions: <Widget>[
          IconButton(
            tooltip: 'الحضور',
            onPressed: () => context.push('/groups/$groupId/attendance'),
            icon: const Icon(Icons.fact_check_rounded),
          ),
        ],
      ),
      floatingActionButton: canManage
          ? FloatingActionButton.extended(
              onPressed: () =>
                  _showAddStudentDialog(context, ref, group.valueOrNull),
              icon: const Icon(Icons.person_add_alt_1_rounded),
              label: const Text('إضافة طالب'),
            )
          : null,
      body: group.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (Object error, StackTrace _) => Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              Text(describeApiError(error)),
              const SizedBox(height: 12),
              OutlinedButton(
                onPressed: () => ref.invalidate(groupDetailsProvider(groupId)),
                child: const Text('إعادة المحاولة'),
              ),
            ],
          ),
        ),
        data: (Group value) => RefreshIndicator(
          onRefresh: () async => ref.invalidate(groupDetailsProvider(groupId)),
          child: ListView(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 96),
            children: <Widget>[
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Text(
                        value.name,
                        style: Theme.of(context).textTheme.titleLarge,
                      ),
                      const SizedBox(height: 8),
                      Text('المعلم: ${value.teacherName}'),
                      Text('الحالة: ${value.isActive ? 'نشطة' : 'موقوفة'}'),
                      Text('عدد الطلاب: ${value.studentsCount}'),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 16),
              Text('الطلاب', style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 8),
              if (value.students.isEmpty)
                const Card(
                  child: ListTile(title: Text('لا يوجد طلاب في هذه الحلقة')),
                )
              else
                ...value.students.map(
                  (GroupStudent student) => Card(
                    child: ListTile(
                      leading: CircleAvatar(
                        child: Text(
                          student.fullName.isEmpty
                              ? '؟'
                              : student.fullName.substring(0, 1),
                        ),
                      ),
                      title: Text(student.fullName),
                      subtitle: Text(student.gradeLevel ?? ''),
                      trailing: canManage
                          ? IconButton(
                              tooltip: 'إزالة من الحلقة',
                              icon: const Icon(
                                Icons.person_remove_alt_1_rounded,
                              ),
                              onPressed: () =>
                                  _removeStudent(context, ref, student),
                            )
                          : null,
                    ),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _removeStudent(
    BuildContext context,
    WidgetRef ref,
    GroupStudent student,
  ) async {
    final bool? confirmed = await showDialog<bool>(
      context: context,
      builder: (BuildContext dialogContext) => AlertDialog(
        title: const Text('إزالة الطالب'),
        content: Text('هل تريد إزالة ${student.fullName} من الحلقة؟'),
        actions: <Widget>[
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(false),
            child: const Text('إلغاء'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(dialogContext).pop(true),
            child: const Text('إزالة'),
          ),
        ],
      ),
    );

    if (confirmed != true) {
      return;
    }

    try {
      await ref
          .read(groupsRepositoryProvider)
          .removeStudent(groupId, student.id);

      ref.invalidate(groupDetailsProvider(groupId));
      ref.invalidate(groupsProvider);
    } catch (error) {
      if (context.mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(describeApiError(error))));
      }
    }
  }

  Future<void> _showAddStudentDialog(
    BuildContext context,
    WidgetRef ref,
    Group? group,
  ) async {
    if (group == null) {
      return;
    }

    final int? added = await showDialog<int>(
      context: context,
      builder: (BuildContext dialogContext) => _AddStudentDialog(group: group),
    );

    if (added != null) {
      ref.invalidate(groupDetailsProvider(groupId));
      ref.invalidate(groupsProvider);
    }
  }
}

class _AddStudentDialog extends ConsumerWidget {
  const _AddStudentDialog({required this.group});

  final Group group;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AsyncValue<List<Student>> students = ref.watch(studentsProvider);
    final Set<int> memberIds = group.students
        .map((GroupStudent student) => student.id)
        .toSet();

    return AlertDialog(
      title: const Text('إضافة طالب'),
      content: SizedBox(
        width: 320,
        height: 360,
        child: students.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (Object error, StackTrace _) => Text(describeApiError(error)),
          data: (List<Student> rows) {
            final List<Student> available = rows
                .where((Student student) => !memberIds.contains(student.id))
                .toList(growable: false);

            if (available.isEmpty) {
              return const Center(child: Text('لا يوجد طلاب متاحون'));
            }

            return ListView.separated(
              itemCount: available.length,
              separatorBuilder: (_, __) => const Divider(height: 1),
              itemBuilder: (BuildContext context, int index) {
                final Student student = available[index];

                return ListTile(
                  title: Text(student.fullName),
                  subtitle: Text(student.gradeLevel ?? ''),
                  onTap: () async {
                    try {
                      await ref
                          .read(groupsRepositoryProvider)
                          .addStudent(group.id, student.id);

                      if (context.mounted) {
                        Navigator.of(context).pop(student.id);
                      }
                    } catch (error) {
                      if (context.mounted) {
                        ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(content: Text(describeApiError(error))),
                        );
                      }
                    }
                  },
                );
              },
            );
          },
        ),
      ),
      actions: <Widget>[
        TextButton(
          onPressed: () => Navigator.of(context).pop(),
          child: const Text('إغلاق'),
        ),
      ],
    );
  }
}
