import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/theme/app_colors.dart';
import '../../../field/presentation/widgets/location_report_button.dart';
import '../../../shared/widgets/app_card.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/error_state.dart';
import '../../../shared/widgets/loading_skeleton.dart';
import '../../domain/models/route_task.dart';
import '../providers/meter_provider.dart';

class RouteTasksPage extends ConsumerStatefulWidget {
  const RouteTasksPage({super.key});

  @override
  ConsumerState<RouteTasksPage> createState() => _RouteTasksPageState();
}

class _RouteTasksPageState extends ConsumerState<RouteTasksPage> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(meterRoutesProvider.notifier).loadRoutes();
    });
  }

  Future<void> _onRefresh() async {
    await ref.read(meterRoutesProvider.notifier).loadRoutes();
  }

  @override
  Widget build(BuildContext context) {
    final routesState = ref.watch(meterRoutesProvider);
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Rute Baca Meter'),
        actions: [
          LocationReportButton(),
          IconButton(onPressed: _onRefresh, icon: const Icon(Icons.refresh_rounded)),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _onRefresh,
        color: AppColors.primary,
        child: _buildContent(routesState, theme),
      ),
    );
  }

  Widget _buildContent(MeterRoutesState state, ThemeData theme) {
    if (state.dataState == MeterDataState.loading) {
      return const LoadingSkeleton();
    }

    if (state.dataState == MeterDataState.error) {
      return ErrorStateWidget(
        message: state.errorMessage ?? 'Gagal memuat data rute',
        onRetry: _onRefresh,
      );
    }

    if (state.routes.isEmpty) {
      return const EmptyStateWidget(
        icon: Icons.route_outlined,
        title: 'Tidak Ada Rute',
        subtitle: 'Belum ada rute yang ditugaskan hari ini',
      );
    }

    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: state.routes.length,
      itemBuilder: (context, index) {
        final route = state.routes[index];
        return _buildRouteCard(route, theme);
      },
    );
  }

  Widget _buildRouteCard(RouteTask route, ThemeData theme) {
    return AppCard(
      margin: const EdgeInsets.only(bottom: 12),
      child: InkWell(
        onTap: route.completionPercentage >= 1.0
            ? null
            : () => context.go('/meter-reading/form/${route.id}'),
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Expanded(
                    child: Text(
                      route.routeName,
                      style: theme.textTheme.titleMedium,
                    ),
                  ),
                  _buildStatusChip(route.status),
                ],
              ),
              const SizedBox(height: 6),
              Text(
                route.area,
                style: const TextStyle(
                  color: AppColors.onSurfaceVariant,
                  fontSize: 13,
                ),
              ),
              const SizedBox(height: 12),
              ClipRRect(
                borderRadius: BorderRadius.circular(6),
                child: LinearProgressIndicator(
                  value: route.completionPercentage,
                  backgroundColor: AppColors.outline.withValues(alpha: 0.3),
                  valueColor: AlwaysStoppedAnimation<Color>(
                    route.completionPercentage >= 1.0
                        ? AppColors.success
                        : AppColors.primary,
                  ),
                  minHeight: 8,
                ),
              ),
              const SizedBox(height: 10),
              Row(
                children: [
                  _buildStat(
                    Icons.check_circle_outline,
                    '${route.completedCount} Selesai',
                    AppColors.success,
                  ),
                  const SizedBox(width: 16),
                  _buildStat(
                    Icons.pending_outlined,
                    '${route.pendingCount} Pending',
                    AppColors.pending,
                  ),
                  if (route.problemCount > 0) ...[
                    const SizedBox(width: 16),
                    _buildStat(
                      Icons.warning_amber_outlined,
                      '${route.problemCount} Masalah',
                      AppColors.error,
                    ),
                  ],
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildStatusChip(String status) {
    Color bgColor;
    Color textColor;
    String label;

    switch (status.toLowerCase()) {
      case 'completed':
        bgColor = AppColors.success.withValues(alpha: 0.1);
        textColor = AppColors.success;
        label = 'Selesai';
      case 'in_progress':
        bgColor = AppColors.primary.withValues(alpha: 0.1);
        textColor = AppColors.primary;
        label = 'Dalam Proses';
      case 'pending':
        bgColor = AppColors.pending.withValues(alpha: 0.1);
        textColor = AppColors.pending;
        label = 'Pending';
      default:
        bgColor = AppColors.onSurfaceVariant.withValues(alpha: 0.1);
        textColor = AppColors.onSurfaceVariant;
        label = status;
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: bgColor,
        borderRadius: BorderRadius.circular(10),
      ),
      child: Text(
        label,
        style: TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w600,
          color: textColor,
        ),
      ),
    );
  }

  Widget _buildStat(IconData icon, String text, Color color) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 14, color: color),
        const SizedBox(width: 4),
        Text(
          text,
          style: TextStyle(
            fontSize: 12,
            color: color,
            fontWeight: FontWeight.w500,
          ),
        ),
      ],
    );
  }
}
