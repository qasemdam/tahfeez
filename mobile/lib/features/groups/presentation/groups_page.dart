import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_client.dart';
import '../../auth/domain/session.dart';
import '../../auth/presentation/auth_controller.dart';
import '../../teachers/data/teachers_repository.dart';
import '../../teachers/domain/teacher.dart';
import '../data/groups_repository.dart';
import '../domain/group.dart';

/// Manager: every group in the center. Teacher: only their own ("حلقاتي").
class GroupsPage extends ConsumerWidget {
  const GroupsPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AsyncValue<List<Group>> groups = ref.watch(groupsProvider);
    final AppUser? user = ref.watch(authControllerProvider).valueOrNull?.user;
    final bool isManager = user?.isManager ?? false;

    return Scaffold(
      appBar: AppBar(
        title: Text(isManager ? 'الحلقات' : 'حلقاتي'),
        actions: <Widget>[
          IconButton(
            tooltip: 'الطلاب',
            onPressed: () => context.push('/students'),
            icon: const Icon(Icons.people_alt_rounded),
          ),
          IconButton(
            tooltip: 'تحديث',
            onPressed: () => ref.invalidate(groupsProvider),
            icon: const Icon(Icons.refresh_rounded),
          ),
          IconButton(
            tooltip: 'تسجيل الخروج',
            onPressed: () async {
              await ref.read(authControllerProvider.notifier).logout();
              if (context.mounted) {
                context.go('/login');
              }
            },
            icon: const Icon(Icons.logout_rounded),
          ),
        ],
      ),
      floatingActionButton: isManager
          ? FloatingActionButton.extended(
              onPressed: () => _showCreateDialog(context, ref),
              icon: const Icon(Icons.add_rounded),
              label: const Text('حلقة جديدة'),
            )
          : null,
      body: groups.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (Object error, StackTrace _) => _ErrorState(
          message: describeApiError(error),
          onRetry: () => ref.invalidate(groupsProvider),
        ),
        data: (List<Group> rows) {
          if (rows.isEmpty) {
            return const _EmptyState();
          }

          return RefreshIndicator(
            onRefresh: () async => ref.invalidate(groupsProvider),
            child: ListView.separated(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 96),
              itemCount: rows.length,
              separatorBuilder: (_, __) => const SizedBox(height: 8),
              itemBuilder: (BuildContext context, int index) {
                return _GroupTile(group: rows[index]);
              },
            ),
          );
        },
      ),
    );
  }

  Future<void> _showCreateDialog(BuildContext context, WidgetRef ref) async {
    final String? created = await showDialog<String>(
      context: context,
      builder: (BuildContext dialogContext) => const _CreateGroupDialog(),
    );

    if (created != null) {
      ref.invalidate(groupsProvider);
    }
  }
}

class _GroupTile extends StatelessWidget {
  const _GroupTile({required this.group});

  final Group group;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Card(
      child: ListTile(
        onTap: () => context.push('/groups/${group.id}'),
        leading: CircleAvatar(
          backgroundColor: theme.colorScheme.primaryContainer,
          child: Icon(
            Icons.groups_rounded,
            color: theme.colorScheme.onPrimaryContainer,
          ),
        ),
        title: Text(group.name),
        subtitle: Text(
          'المعلم: ${group.teacherName} · ${group.studentsCount} طالب',
        ),
        trailing: Chip(
          label: Text(group.isActive ? 'نشطة' : 'موقوفة'),
          visualDensity: VisualDensity.compact,
        ),
      ),
    );
  }
}

class _CreateGroupDialog extends ConsumerStatefulWidget {
  const _CreateGroupDialog();

  @override
  ConsumerState<_CreateGroupDialog> createState() => _CreateGroupDialogState();
}

class _CreateGroupDialogState extends ConsumerState<_CreateGroupDialog> {
  final TextEditingController _name = TextEditingController();

  int? _teacherId;
  bool _saving = false;
  String? _error;

  @override
  void dispose() {
    _name.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    final String name = _name.text.trim();

    if (name.length < 2 || _teacherId == null) {
      setState(() => _error = 'أدخل اسم الحلقة واختر المعلم.');
      return;
    }

    setState(() {
      _saving = true;
      _error = null;
    });

    try {
      await ref
          .read(groupsRepositoryProvider)
          .create(name: name, teacherId: _teacherId!);

      if (mounted) {
        Navigator.of(context).pop(name);
      }
    } catch (error) {
      if (mounted) {
        setState(() {
          _saving = false;
          _error = describeApiError(error);
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final AsyncValue<List<Teacher>> teachers = ref.watch(teachersProvider);

    return AlertDialog(
      title: const Text('حلقة جديدة'),
      content: SizedBox(
        width: 320,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            TextField(
              controller: _name,
              decoration: const InputDecoration(labelText: 'اسم الحلقة'),
            ),
            const SizedBox(height: 12),
            teachers.when(
              loading: () => const LinearProgressIndicator(),
              error: (Object error, StackTrace _) =>
                  Text(describeApiError(error)),
              data: (List<Teacher> rows) {
                if (rows.isEmpty) {
                  return const Text('لا يوجد معلمون في المركز بعد.');
                }

                return DropdownMenu<int>(
                  initialSelection: _teacherId,
                  label: const Text('المعلم'),
                  expandedInsets: EdgeInsets.zero,
                  dropdownMenuEntries: rows
                      .map(
                        (Teacher teacher) => DropdownMenuEntry<int>(
                          value: teacher.id,
                          label: teacher.name,
                        ),
                      )
                      .toList(growable: false),
                  onSelected: (int? value) =>
                      setState(() => _teacherId = value),
                );
              },
            ),
            if (_error != null) ...<Widget>[
              const SizedBox(height: 12),
              Text(
                _error!,
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
            ],
          ],
        ),
      ),
      actions: <Widget>[
        TextButton(
          onPressed: _saving ? null : () => Navigator.of(context).pop(),
          child: const Text('إلغاء'),
        ),
        FilledButton(
          onPressed: _saving ? null : _save,
          child: _saving
              ? const SizedBox(
                  height: 18,
                  width: 18,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : const Text('إنشاء'),
        ),
      ],
    );
  }
}

class _EmptyState extends StatelessWidget {
  const _EmptyState();

  @override
  Widget build(BuildContext context) {
    return const Center(child: Text('لا توجد حلقات بعد'));
  }
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Text(message),
          const SizedBox(height: 12),
          OutlinedButton(
            onPressed: onRetry,
            child: const Text('إعادة المحاولة'),
          ),
        ],
      ),
    );
  }
}
