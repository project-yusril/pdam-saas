import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/theme/app_colors.dart';
import '../../../../core/utils/format_helper.dart';
import '../../../shared/widgets/app_card.dart';
import '../../../shared/widgets/app_stat_tile.dart';
import '../../../shared/widgets/error_state.dart';
import '../../../shared/widgets/loading_skeleton.dart';
import '../../domain/models/customer_dashboard.dart';
import '../providers/portal_provider.dart';

class DashboardPage extends ConsumerStatefulWidget {
  const DashboardPage({super.key});

  @override
  ConsumerState<DashboardPage> createState() => _DashboardPageState();
}

class _DashboardPageState extends ConsumerState<DashboardPage> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(dashboardProvider.notifier).loadDashboard();
    });
  }

  Future<void> _onRefresh() async {
    await ref.read(dashboardProvider.notifier).loadDashboard();
  }

  @override
  Widget build(BuildContext context) {
    final dashboardState = ref.watch(dashboardProvider);
    final theme = Theme.of(context);

    return RefreshIndicator(
      onRefresh: _onRefresh,
      color: AppColors.primary,
      child: CustomScrollView(
        slivers: [
          SliverAppBar(
            floating: true,
            title: const Row(
              children: [
                Icon(Icons.water_drop_rounded,
                    color: AppColors.primary, size: 28),
                SizedBox(width: 8),
                Text('Beranda'),
              ],
            ),
            actions: [
              IconButton(
                icon: const Icon(Icons.notifications_outlined),
                onPressed: () => context.go('/portal/notifications'),
              ),
            ],
          ),
          if (dashboardState.dataState == PortalDataState.loading)
            const SliverFillRemaining(
              child: LoadingSkeleton(),
            )
          else if (dashboardState.dataState == PortalDataState.error)
            SliverFillRemaining(
              child: ErrorStateWidget(
                message: dashboardState.errorMessage ?? 'Gagal memuat data',
                onRetry: _onRefresh,
              ),
            )
          else if (dashboardState.dashboard != null)
            _buildContent(context, dashboardState.dashboard!, theme),
        ],
      ),
    );
  }

  Widget _buildContent(
      BuildContext context, CustomerDashboard dashboard, ThemeData theme) {
    return SliverPadding(
      padding: const EdgeInsets.all(16),
      sliver: SliverList(
        delegate: SliverChildListDelegate([
          AppCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    const CircleAvatar(
                      radius: 24,
                      backgroundColor: AppColors.primaryContainer,
                      child: Icon(
                        Icons.person,
                        color: AppColors.primary,
                        size: 28,
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            dashboard.customerName,
                            style: theme.textTheme.titleLarge,
                          ),
                          const SizedBox(height: 2),
                          Text(
                            'No. ${dashboard.customerNumber}',
                            style: theme.textTheme.bodySmall,
                          ),
                        ],
                      ),
                    ),
                    const Icon(Icons.chevron_right,
                        color: AppColors.onSurfaceVariant),
                  ],
                ),
                const SizedBox(height: 12),
                Text(
                  dashboard.address,
                  style: theme.textTheme.bodySmall,
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          Text(
            'Ringkasan Tagihan',
            style: theme.textTheme.titleMedium,
          ),
          const SizedBox(height: 8),
          AppCard(
            child: Column(
              children: [
                Row(
                  children: [
                    Expanded(
                      child: AppStatTile(
                        icon: Icons.receipt_long,
                        iconColor: AppColors.warning,
                        label: 'Tagihan Bulan Ini',
                        value: FormatHelper.formatRupiah(
                            dashboard.currentBillAmount),
                        subtitle: dashboard.currentBillPeriod,
                      ),
                    ),
                    Container(
                      width: 1,
                      height: 60,
                      color: AppColors.outline,
                    ),
                    Expanded(
                      child: AppStatTile(
                        icon: Icons.water_drop,
                        iconColor: AppColors.primary,
                        label: 'Total Pemakaian',
                        value: FormatHelper.formatVolume(dashboard.totalUsage),
                        subtitle:
                            '${dashboard.averageDailyUsage.toStringAsFixed(1)} L/hari',
                      ),
                    ),
                  ],
                ),
                const Divider(),
                Row(
                  children: [
                    Expanded(
                      child: _buildStatusChip(dashboard.currentBillStatus),
                    ),
                    Text(
                      'Jatuh tempo: ${dashboard.daysUntilDue} hari',
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: dashboard.daysUntilDue <= 3
                            ? AppColors.error
                            : AppColors.onSurfaceVariant,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              Text('Menu Cepat', style: theme.textTheme.titleMedium),
            ],
          ),
          const SizedBox(height: 8),
          Row(
            children: [
              Expanded(
                child: _buildQuickMenu(
                  context,
                  icon: Icons.receipt_long,
                  label: 'Tagihan',
                  onTap: () => context.go('/portal/bills'),
                  color: AppColors.primary,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: _buildQuickMenu(
                  context,
                  icon: Icons.show_chart,
                  label: 'Pemakaian',
                  onTap: () => context.go('/portal/usage'),
                  color: AppColors.secondary,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: _buildQuickMenu(
                  context,
                  icon: Icons.info_outline,
                  label: 'Pengaduan',
                  onTap: () => _showComplaintMenu(context),
                  color: AppColors.warning,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: _buildQuickMenu(
                  context,
                  icon: Icons.person,
                  label: 'Profil',
                  onTap: () => context.go('/portal/profile'),
                  color: AppColors.primaryDark,
                ),
              ),
            ],
          ),
          const SizedBox(height: 24),
          AppCard(
            child: InkWell(
              onTap: () => context.go('/portal/bills'),
              borderRadius: BorderRadius.circular(12),
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Row(
                  children: [
                    Container(
                      width: 40,
                      height: 40,
                      decoration: BoxDecoration(
                        color: AppColors.errorContainer,
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: const Icon(
                        Icons.warning_amber_rounded,
                        color: AppColors.error,
                        size: 22,
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Segera Bayar Tagihan Anda',
                            style: theme.textTheme.titleSmall,
                          ),
                          const SizedBox(height: 2),
                          Text(
                            'Hindari denda keterlambatan. Bayar sebelum jatuh tempo.',
                            style: theme.textTheme.bodySmall,
                          ),
                        ],
                      ),
                    ),
                    const Icon(Icons.arrow_forward_ios,
                        size: 16, color: AppColors.onSurfaceVariant),
                  ],
                ),
              ),
            ),
          ),
          const SizedBox(height: 32),
        ]),
      ),
    );
  }

  Widget _buildQuickMenu(
    BuildContext context, {
    required IconData icon,
    required String label,
    required VoidCallback onTap,
    required Color color,
  }) {
    return GestureDetector(
      onTap: onTap,
      child: AppCard(
        padding: const EdgeInsets.all(16),
        child: Column(
          children: [
            Container(
              width: 48,
              height: 48,
              decoration: BoxDecoration(
                color: color.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Icon(icon, color: color, size: 26),
            ),
            const SizedBox(height: 8),
            Text(
              label,
              style: const TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w600,
              ),
              textAlign: TextAlign.center,
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildStatusChip(String status) {
    Color bgColor;
    Color textColor;
    String label;

    switch (status.toLowerCase()) {
      case 'paid':
      case 'lunas':
        bgColor = AppColors.paid.withValues(alpha: 0.1);
        textColor = AppColors.paid;
        label = 'Lunas';
      case 'pending':
      case 'belum bayar':
        bgColor = AppColors.pending.withValues(alpha: 0.1);
        textColor = AppColors.pending;
        label = 'Belum Bayar';
      case 'overdue':
      case 'telat':
        bgColor = AppColors.overdue.withValues(alpha: 0.1);
        textColor = AppColors.overdue;
        label = 'Terlambat';
      default:
        bgColor = AppColors.onSurfaceVariant.withValues(alpha: 0.1);
        textColor = AppColors.onSurfaceVariant;
        label = status;
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: bgColor,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Text(
        label,
        style: TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.w600,
          color: textColor,
        ),
      ),
    );
  }

  void _showComplaintMenu(BuildContext context) {
    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (context) {
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  'Menu Pengaduan',
                  style: Theme.of(context).textTheme.titleLarge,
                ),
                const SizedBox(height: 16),
                ListTile(
                  leading: const Icon(Icons.add_circle_outline,
                      color: AppColors.primary),
                  title: const Text('Buat Pengaduan Baru'),
                  onTap: () {
                    Navigator.pop(context);
                  },
                ),
                ListTile(
                  leading: const Icon(Icons.history, color: AppColors.primary),
                  title: const Text('Riwayat Pengaduan'),
                  onTap: () {
                    Navigator.pop(context);
                  },
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}
