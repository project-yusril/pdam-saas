import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/theme/app_colors.dart';
import '../../../../core/utils/format_helper.dart';
import '../../../field/presentation/widgets/location_report_button.dart';
import '../../../shared/widgets/app_card.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/error_state.dart';
import '../../../shared/widgets/loading_skeleton.dart';
import '../../domain/models/survey_task.dart';
import '../providers/survey_provider.dart';

class SurveyTasksPage extends ConsumerStatefulWidget {
  const SurveyTasksPage({super.key});

  @override
  ConsumerState<SurveyTasksPage> createState() => _SurveyTasksPageState();
}

class _SurveyTasksPageState extends ConsumerState<SurveyTasksPage> {
  String? _selectedStatus;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(surveyTasksProvider.notifier).loadTasks();
    });
  }

  Future<void> _onRefresh() async {
    await ref
        .read(surveyTasksProvider.notifier)
        .loadTasks(status: _selectedStatus);
  }

  @override
  Widget build(BuildContext context) {
    final tasksState = ref.watch(surveyTasksProvider);
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Tugas Survey'),
        actions: [
          const LocationReportButton(),
          IconButton(
              onPressed: _onRefresh, icon: const Icon(Icons.refresh_rounded)),
        ],
      ),
      body: Column(
        children: [
          _buildFilterRow(theme),
          Expanded(
            child: RefreshIndicator(
              onRefresh: _onRefresh,
              color: AppColors.primary,
              child: _buildContent(tasksState, theme),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildFilterRow(ThemeData theme) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      child: SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        child: Row(
          children: [
            _buildFilterChip('Semua', null),
            const SizedBox(width: 8),
            _buildFilterChip('Pending', 'pending'),
            const SizedBox(width: 8),
            _buildFilterChip('Dalam Proses', 'in_progress'),
            const SizedBox(width: 8),
            _buildFilterChip('Selesai', 'completed'),
          ],
        ),
      ),
    );
  }

  Widget _buildFilterChip(String label, String? value) {
    final isSelected = _selectedStatus == value;
    return FilterChip(
      label: Text(label),
      selected: isSelected,
      onSelected: (selected) {
        setState(() {
          _selectedStatus = selected ? value : null;
        });
        ref
            .read(surveyTasksProvider.notifier)
            .loadTasks(status: _selectedStatus);
      },
      selectedColor: AppColors.primaryContainer,
      checkmarkColor: AppColors.primary,
      side: BorderSide(
        color: isSelected ? AppColors.primary : AppColors.outline,
      ),
    );
  }

  Widget _buildContent(SurveyTasksState state, ThemeData theme) {
    if (state.dataState == SurveyDataState.loading) {
      return const LoadingSkeleton();
    }

    if (state.dataState == SurveyDataState.error) {
      return ErrorStateWidget(
        message: state.errorMessage ?? 'Gagal memuat tugas survey',
        onRetry: _onRefresh,
      );
    }

    if (state.tasks.isEmpty) {
      return const EmptyStateWidget(
        icon: Icons.assignment_outlined,
        title: 'Tidak Ada Tugas Survey',
        subtitle: 'Tugas survey akan muncul di sini',
      );
    }

    return ListView.builder(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      itemCount: state.tasks.length,
      itemBuilder: (context, index) {
        return _buildTaskCard(state.tasks[index], theme);
      },
    );
  }

  Widget _buildTaskCard(SurveyTask task, ThemeData theme) {
    return AppCard(
      margin: const EdgeInsets.only(bottom: 12),
      child: InkWell(
        onTap: task.status == 'completed'
            ? null
            : () => context.go('/survey/form/${task.id}'),
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
                      task.applicantName,
                      style: theme.textTheme.titleMedium,
                    ),
                  ),
                  _buildStatusChip(task.status),
                ],
              ),
              const SizedBox(height: 4),
              Row(
                children: [
                  Container(
                    padding:
                        const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                    decoration: BoxDecoration(
                      color: AppColors.primaryContainer,
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: Text(
                      task.surveyTypeLabel,
                      style: const TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w600,
                        color: AppColors.primaryDark,
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  const Icon(Icons.phone_outlined,
                      size: 14, color: AppColors.onSurfaceVariant),
                  const SizedBox(width: 4),
                  Text(
                    task.applicantPhone,
                    style: const TextStyle(
                      fontSize: 13,
                      color: AppColors.onSurfaceVariant,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Icon(Icons.location_on_outlined,
                      size: 15, color: AppColors.onSurfaceVariant),
                  const SizedBox(width: 4),
                  Expanded(
                    child: Text(
                      task.address,
                      style: const TextStyle(
                        fontSize: 13,
                        color: AppColors.onSurfaceVariant,
                      ),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              Row(
                children: [
                  const Icon(Icons.calendar_today_outlined,
                      size: 13, color: AppColors.onSurfaceVariant),
                  const SizedBox(width: 4),
                  Text(
                    'Jadwal: ${FormatHelper.formatShortDate(task.scheduledDate ?? task.createdAt)}',
                    style: const TextStyle(
                      fontSize: 12,
                      color: AppColors.onSurfaceVariant,
                    ),
                  ),
                  if (task.status == 'pending') ...[
                    const Spacer(),
                    const Icon(Icons.arrow_forward,
                        size: 18, color: AppColors.primary),
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
}
