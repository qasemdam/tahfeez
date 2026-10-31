import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../features/attendance/presentation/attendance_page.dart';
import '../../features/auth/presentation/login_page.dart';
import '../../features/groups/presentation/group_details_page.dart';
import '../../features/groups/presentation/groups_page.dart';
import '../../features/memorization/presentation/memorization_page.dart';
import '../../features/review/presentation/review_page.dart';
import '../../features/students/presentation/students_page.dart';
import 'splash_page.dart';

final Provider<GoRouter> appRouterProvider = Provider<GoRouter>((Ref ref) {
  return GoRouter(
    initialLocation: '/',
    routes: <RouteBase>[
      GoRoute(path: '/', builder: (_, __) => const SplashPage()),
      GoRoute(path: '/login', builder: (_, __) => const LoginPage()),
      GoRoute(path: '/groups', builder: (_, __) => const GroupsPage()),
      GoRoute(
        path: '/groups/:id/attendance',
        builder: (_, GoRouterState state) => AttendancePage(
          groupId: int.tryParse(state.pathParameters['id'] ?? '') ?? 0,
        ),
      ),
      GoRoute(
        path: '/groups/:id',
        builder: (_, GoRouterState state) => GroupDetailsPage(
          groupId: int.tryParse(state.pathParameters['id'] ?? '') ?? 0,
        ),
      ),
      GoRoute(
        path: '/students/:id/memorization',
        builder: (_, GoRouterState state) => MemorizationPage(
          studentId: int.tryParse(state.pathParameters['id'] ?? '') ?? 0,
          studentName: state.uri.queryParameters['name'] ?? '',
        ),
      ),
      GoRoute(
        path: '/students/:id/review',
        builder: (_, GoRouterState state) => ReviewPage(
          studentId: int.tryParse(state.pathParameters['id'] ?? '') ?? 0,
          studentName: state.uri.queryParameters['name'] ?? '',
        ),
      ),
      GoRoute(path: '/students', builder: (_, __) => const StudentsPage()),
    ],
  );
});
