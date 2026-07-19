import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:hive_flutter/hive_flutter.dart';

import 'core/cache/cache_manager.dart';
import 'core/constants.dart';
import 'core/theme/app_colors.dart';
import 'core/theme/app_theme.dart';
import 'features/auth/presentation/pages/login_page.dart';
import 'features/auth/presentation/pages/onboarding_page.dart';
import 'features/auth/presentation/providers/auth_provider.dart';
import 'features/meter_reading/presentation/pages/reading_form_page.dart';
import 'features/meter_reading/presentation/pages/route_tasks_page.dart';
import 'features/portal/presentation/pages/bills_page.dart';
import 'features/portal/presentation/pages/dashboard_page.dart';
import 'features/portal/presentation/pages/notifications_page.dart';
import 'features/portal/presentation/pages/profile_page.dart';
import 'features/portal/presentation/pages/usage_page.dart';
import 'features/shared/widgets/bottom_nav.dart';
import 'features/survey/presentation/pages/survey_form_page.dart';
import 'features/survey/presentation/pages/survey_tasks_page.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await Hive.initFlutter();
  await CacheManager().initialize();

  runApp(
    const ProviderScope(
      child: PdamMobileApp(),
    ),
  );
}

class PdamMobileApp extends ConsumerStatefulWidget {
  const PdamMobileApp({super.key});

  @override
  ConsumerState<PdamMobileApp> createState() => _PdamMobileAppState();
}

class _PdamMobileAppState extends ConsumerState<PdamMobileApp> {
  late final GoRouter _router;

  @override
  void initState() {
    super.initState();
    _router = _buildRouter();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(authProvider.notifier).checkAuthStatus();
    });
  }

  GoRouter _buildRouter() {
    return GoRouter(
      navigatorKey: _rootNavigatorKey,
      initialLocation: '/onboarding',
      redirect: (context, state) {
        final authState = ref.read(authProvider);
        final isLoggedIn = authState == AuthState.authenticated;
        final isAuthRoute = state.matchedLocation == '/login' ||
            state.matchedLocation == '/onboarding';

        if (isLoggedIn && isAuthRoute) {
          final role = ref.read(userRoleProvider);
          return _getHomeRoute(role);
        }

        if (!isLoggedIn && !isAuthRoute) {
          return '/onboarding';
        }

        return null;
      },
      routes: [
        GoRoute(
          path: '/onboarding',
          name: 'onboarding',
          builder: (context, state) => const OnboardingPage(),
        ),
        GoRoute(
          path: '/login',
          name: 'login',
          builder: (context, state) => const LoginPage(),
        ),
        GoRoute(
          path: '/home',
          name: 'home',
          redirect: (context, state) {
            final role = ref.read(userRoleProvider);
            return _getHomeRoute(role);
          },
        ),
        ShellRoute(
          builder: (context, state, child) {
            final role =
                ref.read(userRoleProvider) ?? AppConstants.roleCustomer;
            final currentIndex = _getNavIndex(state.matchedLocation, role);
            return Scaffold(
              body: child,
              bottomNavigationBar: BottomNav(
                currentIndex: currentIndex,
                role: role,
              ),
            );
          },
          routes: [
            GoRoute(
              path: '/portal',
              name: 'portal',
              builder: (context, state) => const DashboardPage(),
              routes: [
                GoRoute(
                  path: 'bills',
                  name: 'bills',
                  builder: (context, state) => const BillsPage(),
                ),
                GoRoute(
                  path: 'usage',
                  name: 'usage',
                  builder: (context, state) => const UsagePage(),
                ),
                GoRoute(
                  path: 'notifications',
                  name: 'notifications',
                  builder: (context, state) => const NotificationsPage(),
                ),
                GoRoute(
                  path: 'profile',
                  name: 'profile',
                  builder: (context, state) => const ProfilePage(),
                ),
              ],
            ),
            GoRoute(
              path: '/meter-reading',
              name: 'meter-reading',
              builder: (context, state) => const RouteTasksPage(),
              routes: [
                GoRoute(
                  path: 'form/:routeId',
                  name: 'meter-reading-form',
                  builder: (context, state) {
                    final routeId = int.parse(state.pathParameters['routeId']!);
                    return ReadingFormPage(routeId: routeId);
                  },
                ),
              ],
            ),
            GoRoute(
              path: '/survey',
              name: 'survey',
              builder: (context, state) => const SurveyTasksPage(),
              routes: [
                GoRoute(
                  path: 'form/:taskId',
                  name: 'survey-form',
                  builder: (context, state) {
                    final taskId = int.parse(state.pathParameters['taskId']!);
                    return SurveyFormPage(taskId: taskId);
                  },
                ),
              ],
            ),
          ],
        ),
      ],
      errorBuilder: (context, state) {
        return Scaffold(
          body: Center(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                const Icon(Icons.error_outline,
                    size: 64, color: AppColors.error),
                const SizedBox(height: 16),
                const Text('Halaman tidak ditemukan',
                    style: TextStyle(fontSize: 18)),
                const SizedBox(height: 12),
                ElevatedButton(
                  onPressed: () => context.go('/home'),
                  child: const Text('KEMBALI KE BERANDA'),
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp.router(
      title: 'PDAM Mobile',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.lightTheme,
      routerConfig: _router,
    );
  }

  String _getHomeRoute(String? role) {
    switch (role) {
      case AppConstants.roleMeterOfficer:
        return '/meter-reading';
      case AppConstants.roleSurveyOfficer:
        return '/survey';
      default:
        return '/portal';
    }
  }

  int _getNavIndex(String location, String role) {
    switch (role) {
      case AppConstants.roleCustomer:
        if (location.startsWith('/portal/bills')) return 1;
        if (location.startsWith('/portal/usage')) return 2;
        if (location.startsWith('/portal/notifications')) return 3;
        if (location.startsWith('/portal/profile')) return 4;
        return 0;
      case AppConstants.roleMeterOfficer:
        if (location.contains('/form/')) return 1;
        if (location.startsWith('/meter-reading')) return 0;
        if (location.startsWith('/portal/profile')) return 3;
        return 0;
      case AppConstants.roleSurveyOfficer:
        if (location.contains('/form/')) return 1;
        if (location.startsWith('/survey')) return 0;
        if (location.startsWith('/portal/profile')) return 3;
        return 0;
      default:
        return 0;
    }
  }
}

final _rootNavigatorKey = GlobalKey<NavigatorState>();
