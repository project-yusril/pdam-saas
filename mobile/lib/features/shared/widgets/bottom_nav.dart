import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../core/constants.dart';

class BottomNav extends StatelessWidget {
  final int currentIndex;
  final String role;

  const BottomNav({
    super.key,
    required this.currentIndex,
    required this.role,
  });

  @override
  Widget build(BuildContext context) {
    final items = _getNavItems();

    return BottomNavigationBar(
      currentIndex: currentIndex,
      onTap: (index) {
        final route = _getRouteForIndex(index);
        if (route != null) {
          context.go(route);
        }
      },
      items: items,
    );
  }

  List<BottomNavigationBarItem> _getNavItems() {
    switch (role) {
      case AppConstants.roleCustomer:
        return const [
          BottomNavigationBarItem(
            icon: Icon(Icons.dashboard_outlined),
            activeIcon: Icon(Icons.dashboard),
            label: 'Beranda',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.receipt_long_outlined),
            activeIcon: Icon(Icons.receipt_long),
            label: 'Tagihan',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.show_chart_outlined),
            activeIcon: Icon(Icons.show_chart),
            label: 'Pemakaian',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.notifications_outlined),
            activeIcon: Icon(Icons.notifications),
            label: 'Notifikasi',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.person_outline),
            activeIcon: Icon(Icons.person),
            label: 'Profil',
          ),
        ];
      case AppConstants.roleMeterOfficer:
        return const [
          BottomNavigationBarItem(
            icon: Icon(Icons.route_outlined),
            activeIcon: Icon(Icons.route),
            label: 'Rute',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.speed_outlined),
            activeIcon: Icon(Icons.speed),
            label: 'Baca Meter',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.history_outlined),
            activeIcon: Icon(Icons.history),
            label: 'Riwayat',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.person_outline),
            activeIcon: Icon(Icons.person),
            label: 'Profil',
          ),
        ];
      case AppConstants.roleSurveyOfficer:
        return const [
          BottomNavigationBarItem(
            icon: Icon(Icons.assignment_outlined),
            activeIcon: Icon(Icons.assignment),
            label: 'Tugas',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.content_paste_search_outlined),
            activeIcon: Icon(Icons.content_paste_search),
            label: 'Survey',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.lightbulb_outline),
            activeIcon: Icon(Icons.lightbulb),
            label: 'Rekomendasi',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.person_outline),
            activeIcon: Icon(Icons.person),
            label: 'Profil',
          ),
        ];
      default:
        return const [];
    }
  }

  String? _getRouteForIndex(int index) {
    switch (role) {
      case AppConstants.roleCustomer:
        switch (index) {
          case 0:
            return '/portal';
          case 1:
            return '/portal/bills';
          case 2:
            return '/portal/usage';
          case 3:
            return '/portal/notifications';
          case 4:
            return '/portal/profile';
          default:
            return null;
        }
      case AppConstants.roleMeterOfficer:
        switch (index) {
          case 0:
            return '/meter-reading';
          case 1:
            return '/meter-reading';
          case 2:
            return '/meter-reading';
          case 3:
            return '/portal/profile';
          default:
            return null;
        }
      case AppConstants.roleSurveyOfficer:
        switch (index) {
          case 0:
            return '/survey';
          case 1:
            return '/survey';
          case 2:
            return '/survey';
          case 3:
            return '/portal/profile';
          default:
            return null;
        }
      default:
        return null;
    }
  }
}
