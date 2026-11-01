import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../auth/presentation/auth_controller.dart';
import '../../../core/network/api_client.dart';
import '../data/students_repository.dart';
import '../domain/student.dart';

class StudentsPage extends ConsumerWidget {
  const StudentsPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AsyncValue<List<Student>> students = ref.watch(studentsProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('الطلاب'),
        actions: <Widget>[
          IconButton(
            tooltip: 'تحديث',
            onPressed: () => ref.invalidate(studentsProvider),
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
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _showComingSoon(context),
        icon: const Icon(Icons.person_add_alt_1_rounded),
        label: const Text('طالب جديد'),
      ),
      body: students.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (Object error, StackTrace _) => _ErrorState(
          message: describeApiError(error),
          onRetry: () => ref.invalidate(studentsProvider),
        ),
        data: (List<Student> rows) {
          if (rows.isEmpty) {
            return const _EmptyState();
          }
          return RefreshIndicator(
            onRefresh: () async => ref.invalidate(studentsProvider),
            child: ListView.separated(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 96),
              itemCount: rows.length,
              separatorBuilder: (_, __) => const SizedBox(height: 8),
              itemBuilder: (BuildContext context, int index) {
                return _StudentTile(student: rows[index]);
              },
            ),
          );
        },
      ),
    );
  }

  void _showComingSoon(BuildContext context) {
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('إضافة الطلاب في الشريحة القادمة')),
    );
  }
}

class _StudentTile extends StatelessWidget {
  const _StudentTile({required this.student});

  final Student student;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Card(
      child: ListTile(
        leading: CircleAvatar(
          backgroundColor: theme.colorScheme.primaryContainer,
          child: Text(
            student.fullName.isEmpty ? '؟' : student.fullName.substring(0, 1),
            style: TextStyle(color: theme.colorScheme.onPrimaryContainer),
          ),
        ),
        title: Text(student.fullName),
        subtitle: Text(
          <String>[
            if (student.gradeLevel != null) student.gradeLevel!,
            if (student.teacherName != null) 'المحفظ: ${student.teacherName}',
          ].join(' · '),
        ),
        trailing: Chip(
          label: Text(student.isActive ? 'نشط' : 'موقوف'),
          visualDensity: VisualDensity.compact,
        ),
      ),
    );
  }
}

class _EmptyState extends StatelessWidget {
  const _EmptyState();

  @override
  Widget build(BuildContext context) {
    return const Center(child: Text('لا يوجد طلاب بعد'));
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
