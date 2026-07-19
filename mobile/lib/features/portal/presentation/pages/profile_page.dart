import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/constants.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../auth/presentation/providers/auth_provider.dart';
import '../../../shared/widgets/app_card.dart';
import '../../../shared/widgets/error_state.dart';
import '../../../shared/widgets/loading_skeleton.dart';
import '../providers/portal_provider.dart';

class ProfilePage extends ConsumerStatefulWidget {
  const ProfilePage({super.key});

  @override
  ConsumerState<ProfilePage> createState() => _ProfilePageState();
}

class _ProfilePageState extends ConsumerState<ProfilePage> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(profileProvider.notifier).loadProfile();
    });
  }

  Future<void> _onRefresh() async {
    await ref.read(profileProvider.notifier).loadProfile();
  }

  @override
  Widget build(BuildContext context) {
    final profileState = ref.watch(profileProvider);
    final role = ref.watch(userRoleProvider);
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Profil'),
      ),
      body: RefreshIndicator(
        onRefresh: _onRefresh,
        color: AppColors.primary,
        child: _buildContent(profileState, role, theme),
      ),
    );
  }

  Widget _buildContent(ProfileState state, String? role, ThemeData theme) {
    if (state.dataState == PortalDataState.loading) {
      return const LoadingSkeleton();
    }

    if (state.dataState == PortalDataState.error) {
      return ErrorStateWidget(
        message: state.errorMessage ?? 'Gagal memuat profil',
        onRetry: _onRefresh,
      );
    }

    final profile = state.profile;

    return SingleChildScrollView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.all(16),
      child: Column(
        children: [
          AppCard(
            padding: const EdgeInsets.all(20),
            child: Column(
              children: [
                CircleAvatar(
                  radius: 40,
                  backgroundColor: AppColors.primaryContainer,
                  child: Text(
                    _getInitials(profile?['name'] as String? ?? 'User'),
                    style: const TextStyle(
                      fontSize: 28,
                      fontWeight: FontWeight.bold,
                      color: AppColors.primary,
                    ),
                  ),
                ),
                const SizedBox(height: 12),
                Text(
                  profile?['name'] as String? ?? '-',
                  style: theme.textTheme.titleLarge,
                ),
                const SizedBox(height: 4),
                Text(
                  profile?['email'] as String? ?? '-',
                  style: const TextStyle(
                    color: AppColors.onSurfaceVariant,
                  ),
                ),
                const SizedBox(height: 8),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 12,
                    vertical: 4,
                  ),
                  decoration: BoxDecoration(
                    color: AppColors.primaryContainer,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Text(
                    _getRoleLabel(role),
                    style: const TextStyle(
                      color: AppColors.primaryDark,
                      fontWeight: FontWeight.w600,
                      fontSize: 13,
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          if (role == AppConstants.roleCustomer) ...[
            _buildInfoSection(
              context,
              title: 'Informasi Pelanggan',
              items: [
                InfoItem(
                  icon: Icons.badge_outlined,
                  label: 'No. Pelanggan',
                  value: profile?['customer_number'] as String? ?? '-',
                ),
                InfoItem(
                  icon: Icons.location_on_outlined,
                  label: 'Alamat',
                  value: profile?['address'] as String? ?? '-',
                ),
                InfoItem(
                  icon: Icons.phone_outlined,
                  label: 'Telepon',
                  value: profile?['phone'] as String? ?? '-',
                ),
              ],
            ),
          ] else ...[
            _buildInfoSection(
              context,
              title: 'Informasi Petugas',
              items: [
                InfoItem(
                  icon: Icons.badge_outlined,
                  label: 'NIP',
                  value: profile?['employee_number'] as String? ?? '-',
                ),
                InfoItem(
                  icon: Icons.business_outlined,
                  label: 'Unit/Bagian',
                  value: profile?['department'] as String? ?? '-',
                ),
                InfoItem(
                  icon: Icons.phone_outlined,
                  label: 'Telepon',
                  value: profile?['phone'] as String? ?? '-',
                ),
              ],
            ),
          ],
          const SizedBox(height: 16),
          _buildMenuSection(
            context,
            items: [
              MenuItem(
                icon: Icons.settings_outlined,
                label: 'Pengaturan Akun',
                onTap: () {},
              ),
              MenuItem(
                icon: Icons.lock_outline,
                label: 'Ubah Kata Sandi',
                onTap: () {},
              ),
              if (role == AppConstants.roleCustomer)
                MenuItem(
                  icon: Icons.help_outline,
                  label: 'Bantuan & Pusat Informasi',
                  onTap: () {},
                ),
              MenuItem(
                icon: Icons.info_outline,
                label: 'Tentang Aplikasi',
                onTap: _showAboutDialog,
              ),
              MenuItem(
                icon: Icons.logout,
                label: 'Keluar',
                onTap: _handleLogout,
                isDestructive: true,
              ),
            ],
          ),
          const SizedBox(height: 24),
        ],
      ),
    );
  }

  Widget _buildInfoSection(
    BuildContext context, {
    required String title,
    required List<InfoItem> items,
  }) {
    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 14, 16, 8),
            child: Text(
              title,
              style: Theme.of(context).textTheme.titleMedium,
            ),
          ),
          ...items.map((item) => ListTile(
                leading: Icon(item.icon, color: AppColors.primary, size: 22),
                title: Text(
                  item.label,
                  style: const TextStyle(fontSize: 14),
                ),
                subtitle: Text(
                  item.value,
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                    color: AppColors.onSurface,
                  ),
                ),
                contentPadding: const EdgeInsets.symmetric(horizontal: 16),
              )),
        ],
      ),
    );
  }

  Widget _buildMenuSection(
    BuildContext context, {
    required List<MenuItem> items,
  }) {
    return AppCard(
      child: Column(
        children: items.asMap().entries.map((entry) {
          final index = entry.key;
          final item = entry.value;
          final isLast = index == items.length - 1;
          return Column(
            children: [
              ListTile(
                leading: Icon(
                  item.icon,
                  color:
                      item.isDestructive ? AppColors.error : AppColors.primary,
                  size: 22,
                ),
                title: Text(
                  item.label,
                  style: TextStyle(
                    color: item.isDestructive ? AppColors.error : null,
                    fontSize: 14,
                  ),
                ),
                trailing: const Icon(
                  Icons.chevron_right,
                  size: 20,
                  color: AppColors.onSurfaceVariant,
                ),
                onTap: item.onTap,
                contentPadding: const EdgeInsets.symmetric(horizontal: 16),
              ),
              if (!isLast) const Divider(indent: 56),
            ],
          );
        }).toList(),
      ),
    );
  }

  void _handleLogout() {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Konfirmasi Keluar'),
        content: const Text('Apakah Anda yakin ingin keluar dari akun?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('BATAL'),
          ),
          ElevatedButton(
            onPressed: () {
              Navigator.pop(context);
              ref.read(authProvider.notifier).logout();
              context.go('/login');
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.error,
            ),
            child: const Text('KELUAR'),
          ),
        ],
      ),
    );
  }

  void _showAboutDialog() {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Tentang PDAM Mobile'),
        content: const Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Versi: 1.0.0'),
            SizedBox(height: 8),
            Text(
              'Aplikasi resmi PDAM untuk pelanggan dan petugas. '
              'Kelola tagihan, pantau pemakaian air, dan laporkan pengaduan '
              'dengan mudah.',
            ),
            SizedBox(height: 12),
            Text('\u00A9 2024 PDAM'),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('TUTUP'),
          ),
        ],
      ),
    );
  }

  String _getInitials(String name) {
    final parts = name.trim().split(' ');
    if (parts.length >= 2) {
      return '${parts.first[0]}${parts.last[0]}'.toUpperCase();
    }
    return name.isEmpty ? '?' : name[0].toUpperCase();
  }

  String _getRoleLabel(String? role) {
    switch (role) {
      case AppConstants.roleCustomer:
        return 'Pelanggan';
      case AppConstants.roleMeterOfficer:
        return 'Petugas Baca Meter';
      case AppConstants.roleSurveyOfficer:
        return 'Petugas Survey';
      default:
        return role ?? 'Pengguna';
    }
  }
}

class InfoItem {
  final IconData icon;
  final String label;
  final String value;

  const InfoItem({
    required this.icon,
    required this.label,
    required this.value,
  });
}

class MenuItem {
  final IconData icon;
  final String label;
  final VoidCallback onTap;
  final bool isDestructive;

  const MenuItem({
    required this.icon,
    required this.label,
    required this.onTap,
    this.isDestructive = false,
  });
}
