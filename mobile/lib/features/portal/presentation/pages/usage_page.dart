import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../../../core/theme/app_colors.dart';
import '../../../../core/utils/format_helper.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/error_state.dart';
import '../../../shared/widgets/loading_skeleton.dart';
import '../providers/portal_provider.dart';

class UsagePage extends ConsumerStatefulWidget {
  const UsagePage({super.key});

  @override
  ConsumerState<UsagePage> createState() => _UsagePageState();
}

class _UsagePageState extends ConsumerState<UsagePage> {
  int _selectedMonths = 12;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref
          .read(usageProvider.notifier)
          .loadUsageHistory(months: _selectedMonths);
    });
  }

  Future<void> _onRefresh() async {
    await ref
        .read(usageProvider.notifier)
        .loadUsageHistory(months: _selectedMonths);
  }

  @override
  Widget build(BuildContext context) {
    final usageState = ref.watch(usageProvider);
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Pemakaian Air'),
        actions: [
          PopupMenuButton<int>(
            onSelected: (value) {
              setState(() {
                _selectedMonths = value;
              });
              ref.read(usageProvider.notifier).loadUsageHistory(months: value);
            },
            icon: const Icon(Icons.filter_list),
            itemBuilder: (context) => [
              const PopupMenuItem(value: 6, child: Text('6 Bulan')),
              const PopupMenuItem(value: 12, child: Text('1 Tahun')),
              const PopupMenuItem(value: 24, child: Text('2 Tahun')),
            ],
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _onRefresh,
        color: AppColors.primary,
        child: _buildContent(usageState, theme),
      ),
    );
  }

  Widget _buildContent(UsageState state, ThemeData theme) {
    if (state.dataState == PortalDataState.loading) {
      return const LoadingSkeleton();
    }

    if (state.dataState == PortalDataState.error) {
      return ErrorStateWidget(
        message: state.errorMessage ?? 'Gagal memuat data pemakaian',
        onRetry: _onRefresh,
      );
    }

    if (state.usageData.isEmpty) {
      return const EmptyStateWidget(
        icon: Icons.show_chart,
        title: 'Belum Ada Data Pemakaian',
        subtitle: 'Data pemakaian air akan muncul di sini',
      );
    }

    return SingleChildScrollView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _buildSummaryCards(state, theme),
          const SizedBox(height: 20),
          Text(
            'Grafik Pemakaian Air Bulanan',
            style: theme.textTheme.titleMedium,
          ),
          const SizedBox(height: 12),
          _buildUsageChart(state, theme),
          const SizedBox(height: 20),
          Text(
            'Grafik Tagihan Bulanan',
            style: theme.textTheme.titleMedium,
          ),
          const SizedBox(height: 12),
          _buildBillChart(state, theme),
          const SizedBox(height: 24),
          Text(
            'Riwayat Lengkap',
            style: theme.textTheme.titleMedium,
          ),
          const SizedBox(height: 8),
          _buildHistoryList(state, theme),
        ],
      ),
    );
  }

  Widget _buildSummaryCards(UsageState state, ThemeData theme) {
    final data = state.usageData;
    final totalUsage = data.fold<double>(0, (sum, e) => sum + e.usage);
    final totalAmount = data.fold<double>(0, (sum, e) => sum + e.amount);
    final averageUsage = data.isNotEmpty ? totalUsage / data.length : 0.0;

    return Row(
      children: [
        Expanded(
          child: _buildSummaryCard(
            icon: Icons.water_drop,
            iconColor: AppColors.primary,
            label: 'Total Pemakaian',
            value: FormatHelper.formatVolume(totalUsage),
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: _buildSummaryCard(
            icon: Icons.receipt_long,
            iconColor: AppColors.warning,
            label: 'Total Tagihan',
            value: FormatHelper.formatRupiah(totalAmount),
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: _buildSummaryCard(
            icon: Icons.trending_up,
            iconColor: AppColors.secondary,
            label: 'Rata-rata',
            value: '${averageUsage.toStringAsFixed(1)} m\u00B3/bln',
          ),
        ),
      ],
    );
  }

  Widget _buildSummaryCard({
    required IconData icon,
    required Color iconColor,
    required String label,
    required String value,
  }) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(12),
        boxShadow: const [
          BoxShadow(
            color: AppColors.shadow,
            blurRadius: 4,
            offset: Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: iconColor, size: 22),
          const SizedBox(height: 10),
          Text(
            value,
            style: const TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.bold,
              color: AppColors.onSurface,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            label,
            style: const TextStyle(
              fontSize: 10,
              color: AppColors.onSurfaceVariant,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildUsageChart(UsageState state, ThemeData theme) {
    final data = state.usageData;
    final maxUsage =
        data.fold<double>(0, (max, e) => e.usage > max ? e.usage : max);

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(12),
        boxShadow: const [
          BoxShadow(
            color: AppColors.shadow,
            blurRadius: 4,
            offset: Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        children: [
          SizedBox(
            height: 180,
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: data.reversed.toList().asMap().entries.map((entry) {
                final double widthFactor = 1.0 / data.length;
                final double heightFactor =
                    maxUsage > 0 ? entry.value.usage / maxUsage : 0.0;

                return Expanded(
                  child: Padding(
                    padding: EdgeInsets.symmetric(horizontal: widthFactor * 4),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.end,
                      children: [
                        Text(
                          entry.value.usage.toStringAsFixed(0),
                          style: const TextStyle(
                            fontSize: 9,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Flexible(
                          child: Container(
                            width: double.infinity,
                            constraints: const BoxConstraints(maxWidth: 30),
                            decoration: const BoxDecoration(
                              gradient: LinearGradient(
                                begin: Alignment.bottomCenter,
                                end: Alignment.topCenter,
                                colors: [
                                  AppColors.primary,
                                  AppColors.primaryLight,
                                ],
                              ),
                              borderRadius: BorderRadius.vertical(
                                top: Radius.circular(4),
                              ),
                            ),
                            height: 140 * heightFactor,
                          ),
                        ),
                      ],
                    ),
                  ),
                );
              }).toList(),
            ),
          ),
          const SizedBox(height: 8),
          SizedBox(
            height: 20,
            child: Row(
              children: data.reversed.toList().map((e) {
                return Expanded(
                  child: Center(
                    child: Text(
                      FormatHelper.formatMonth(
                        DateFormat('yyyyMM').parse(e.period),
                      ),
                      style: const TextStyle(
                        fontSize: 9,
                        color: AppColors.onSurfaceVariant,
                      ),
                    ),
                  ),
                );
              }).toList(),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildBillChart(UsageState state, ThemeData theme) {
    final data = state.usageData;
    final maxAmount =
        data.fold<double>(0, (max, e) => e.amount > max ? e.amount : max);

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(12),
        boxShadow: const [
          BoxShadow(
            color: AppColors.shadow,
            blurRadius: 4,
            offset: Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        children: [
          SizedBox(
            height: 180,
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: data.reversed.toList().asMap().entries.map((entry) {
                final double heightFactor =
                    maxAmount > 0 ? entry.value.amount / maxAmount : 0.0;

                return Expanded(
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 4),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.end,
                      children: [
                        Text(
                          FormatHelper.formatRupiah(entry.value.amount),
                          style: const TextStyle(
                            fontSize: 8,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Flexible(
                          child: Container(
                            width: double.infinity,
                            constraints: const BoxConstraints(maxWidth: 30),
                            decoration: const BoxDecoration(
                              gradient: LinearGradient(
                                begin: Alignment.bottomCenter,
                                end: Alignment.topCenter,
                                colors: [
                                  AppColors.warning,
                                  AppColors.pending,
                                ],
                              ),
                              borderRadius: BorderRadius.vertical(
                                top: Radius.circular(4),
                              ),
                            ),
                            height: 140 * heightFactor,
                          ),
                        ),
                      ],
                    ),
                  ),
                );
              }).toList(),
            ),
          ),
          const SizedBox(height: 8),
          SizedBox(
            height: 20,
            child: Row(
              children: data.reversed.toList().map((e) {
                return Expanded(
                  child: Center(
                    child: Text(
                      FormatHelper.formatMonth(
                        DateFormat('yyyyMM').parse(e.period),
                      ),
                      style: const TextStyle(
                        fontSize: 9,
                        color: AppColors.onSurfaceVariant,
                      ),
                    ),
                  ),
                );
              }).toList(),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildHistoryList(UsageState state, ThemeData theme) {
    return Column(
      children: state.usageData.reversed.toList().map((data) {
        return Container(
          margin: const EdgeInsets.only(bottom: 8),
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: AppColors.surface,
            borderRadius: BorderRadius.circular(10),
            boxShadow: const [
              BoxShadow(
                color: AppColors.shadow,
                blurRadius: 2,
                offset: Offset(0, 1),
              ),
            ],
          ),
          child: Row(
            children: [
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: AppColors.primaryContainer,
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Center(
                  child: Text(
                    FormatHelper.formatMonth(
                      DateFormat('yyyyMM').parse(data.period),
                    ),
                    style: const TextStyle(
                      fontSize: 10,
                      fontWeight: FontWeight.bold,
                      color: AppColors.primaryDark,
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      FormatHelper.formatPeriod(data.period),
                      style: const TextStyle(
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    Text(
                      FormatHelper.formatVolume(data.usage),
                      style: const TextStyle(
                        fontSize: 13,
                        color: AppColors.onSurfaceVariant,
                      ),
                    ),
                  ],
                ),
              ),
              Text(
                FormatHelper.formatRupiah(data.amount),
                style: const TextStyle(
                  fontWeight: FontWeight.bold,
                  color: AppColors.primaryDark,
                ),
              ),
            ],
          ),
        );
      }).toList(),
    );
  }
}
