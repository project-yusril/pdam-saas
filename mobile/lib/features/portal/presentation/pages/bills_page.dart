import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../core/theme/app_colors.dart';
import '../../../../core/utils/format_helper.dart';
import '../../../shared/widgets/app_card.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/error_state.dart';
import '../../../shared/widgets/loading_skeleton.dart';
import '../../domain/models/bill.dart';
import '../providers/portal_provider.dart';

class BillsPage extends ConsumerStatefulWidget {
  const BillsPage({super.key});

  @override
  ConsumerState<BillsPage> createState() => _BillsPageState();
}

class _BillsPageState extends ConsumerState<BillsPage> {
  final ScrollController _scrollController = ScrollController();
  String? _selectedFilter;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(billsProvider.notifier).loadBills();
    });
    _scrollController.addListener(_onScroll);
  }

  @override
  void dispose() {
    _scrollController.dispose();
    super.dispose();
  }

  void _onScroll() {
    if (_scrollController.position.pixels >=
        _scrollController.position.maxScrollExtent - 200) {
      ref.read(billsProvider.notifier).loadMore();
    }
  }

  Future<void> _onRefresh() async {
    await ref.read(billsProvider.notifier).loadBills(refresh: true);
  }

  @override
  Widget build(BuildContext context) {
    final billsState = ref.watch(billsProvider);
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Tagihan'),
      ),
      body: Column(
        children: [
          _buildFilterChips(billsState, theme),
          Expanded(
            child: _buildBillList(billsState),
          ),
        ],
      ),
    );
  }

  Widget _buildFilterChips(BillsState state, ThemeData theme) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      child: SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        child: Row(
          children: [
            _buildFilterChip('Semua', null),
            const SizedBox(width: 8),
            _buildFilterChip('Belum Bayar', 'pending'),
            const SizedBox(width: 8),
            _buildFilterChip('Lunas', 'paid'),
            const SizedBox(width: 8),
            _buildFilterChip('Terlambat', 'overdue'),
          ],
        ),
      ),
    );
  }

  Widget _buildFilterChip(String label, String? value) {
    final isSelected = _selectedFilter == value;
    return FilterChip(
      label: Text(label),
      selected: isSelected,
      onSelected: (selected) {
        setState(() {
          _selectedFilter = selected ? value : null;
        });
        ref.read(billsProvider.notifier).loadBills(refresh: true);
      },
      selectedColor: AppColors.primaryContainer,
      checkmarkColor: AppColors.primary,
      side: BorderSide(
        color: isSelected ? AppColors.primary : AppColors.outline,
      ),
    );
  }

  Widget _buildBillList(BillsState state) {
    if (state.dataState == PortalDataState.loading && state.bills.isEmpty) {
      return const LoadingSkeleton();
    }

    if (state.dataState == PortalDataState.error && state.bills.isEmpty) {
      return ErrorStateWidget(
        message: state.errorMessage ?? 'Gagal memuat tagihan',
        onRetry: _onRefresh,
      );
    }

    if (state.bills.isEmpty) {
      return const EmptyStateWidget(
        icon: Icons.receipt_long,
        title: 'Belum Ada Tagihan',
        subtitle: 'Tagihan air Anda akan muncul di sini',
      );
    }

    return RefreshIndicator(
      onRefresh: _onRefresh,
      color: AppColors.primary,
      child: ListView.builder(
        controller: _scrollController,
        padding: const EdgeInsets.symmetric(horizontal: 16),
        itemCount: state.bills.length + (state.isLoadingMore ? 1 : 0),
        itemBuilder: (context, index) {
          if (index >= state.bills.length) {
            return const Center(
              child: Padding(
                padding: EdgeInsets.all(16),
                child: CircularProgressIndicator(strokeWidth: 2),
              ),
            );
          }
          return _buildBillCard(state.bills[index]);
        },
      ),
    );
  }

  Widget _buildBillCard(Bill bill) {
    return AppCard(
      margin: const EdgeInsets.only(bottom: 12),
      child: InkWell(
        onTap: () => _showBillDetail(bill),
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    FormatHelper.formatPeriod(bill.period),
                    style: const TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  _buildBillStatusChip(bill.status),
                ],
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'Total Tagihan',
                          style: TextStyle(
                            fontSize: 12,
                            color: AppColors.onSurfaceVariant,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          FormatHelper.formatRupiah(bill.totalAmount),
                          style: const TextStyle(
                            fontSize: 18,
                            fontWeight: FontWeight.bold,
                            color: AppColors.primaryDark,
                          ),
                        ),
                      ],
                    ),
                  ),
                  if (bill.status.toLowerCase() != 'paid')
                    SizedBox(
                      height: 36,
                      child: ElevatedButton(
                        onPressed: () => _handlePayBill(bill),
                        style: ElevatedButton.styleFrom(
                          padding: const EdgeInsets.symmetric(horizontal: 20),
                          textStyle: const TextStyle(fontSize: 13),
                        ),
                        child: const Text('BAYAR'),
                      ),
                    ),
                ],
              ),
              const SizedBox(height: 8),
              Row(
                children: [
                  _buildInfoChip(
                    Icons.water_drop_outlined,
                    '${bill.usage.toStringAsFixed(0)} m\u00B3',
                  ),
                  const SizedBox(width: 12),
                  _buildInfoChip(
                    Icons.calendar_today_outlined,
                    'Jatuh tempo: ${FormatHelper.formatShortDate(bill.dueDate)}',
                  ),
                ],
              ),
              if (bill.isLate && bill.lateFee != null) ...[
                const SizedBox(height: 8),
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(
                    color: AppColors.errorContainer,
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: Text(
                    'Denda: ${FormatHelper.formatRupiah(bill.lateFee!)}',
                    style: const TextStyle(
                      fontSize: 12,
                      color: AppColors.error,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildBillStatusChip(String status) {
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

  Widget _buildInfoChip(IconData icon, String text) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 14, color: AppColors.onSurfaceVariant),
        const SizedBox(width: 4),
        Text(
          text,
          style: const TextStyle(
            fontSize: 12,
            color: AppColors.onSurfaceVariant,
          ),
        ),
      ],
    );
  }

  void _showBillDetail(Bill bill) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (context) {
        return DraggableScrollableSheet(
          initialChildSize: 0.7,
          maxChildSize: 0.9,
          minChildSize: 0.4,
          expand: false,
          builder: (context, scrollController) {
            return SingleChildScrollView(
              controller: scrollController,
              padding: const EdgeInsets.all(24),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Center(
                    child: Container(
                      width: 40,
                      height: 4,
                      decoration: BoxDecoration(
                        color: AppColors.outline,
                        borderRadius: BorderRadius.circular(2),
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                  Text(
                    'Detail Tagihan',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  const SizedBox(height: 4),
                  Text(
                    FormatHelper.formatPeriod(bill.period),
                    style: const TextStyle(color: AppColors.onSurfaceVariant),
                  ),
                  const SizedBox(height: 16),
                  _buildDetailRow('No. Tagihan', bill.billNumber),
                  const Divider(),
                  _buildDetailRow(
                    'Meter Sebelumnya',
                    '${bill.previousReading.toStringAsFixed(0)} m\u00B3',
                  ),
                  _buildDetailRow(
                    'Meter Saat Ini',
                    '${bill.currentReading.toStringAsFixed(0)} m\u00B3',
                  ),
                  _buildDetailRow(
                    'Pemakaian',
                    '${bill.usage.toStringAsFixed(0)} m\u00B3',
                  ),
                  const Divider(),
                  ...bill.items.map((item) => _buildDetailRow(
                        item.label,
                        FormatHelper.formatRupiah(item.amount),
                      )),
                  _buildDetailRow(
                    'Biaya Admin',
                    FormatHelper.formatRupiah(bill.adminFee),
                  ),
                  if (bill.lateFee != null && bill.lateFee! > 0)
                    _buildDetailRow(
                      'Denda',
                      FormatHelper.formatRupiah(bill.lateFee!),
                      valueColor: AppColors.error,
                    ),
                  const Divider(),
                  _buildDetailRow(
                    'Total',
                    FormatHelper.formatRupiah(bill.totalAmount),
                    isBold: true,
                    valueColor: AppColors.primaryDark,
                  ),
                  const Divider(),
                  _buildDetailRow('Status', bill.status),
                  _buildDetailRow(
                    'Tanggal Tagihan',
                    FormatHelper.formatShortDate(bill.billDate),
                  ),
                  _buildDetailRow(
                    'Jatuh Tempo',
                    FormatHelper.formatShortDate(bill.dueDate),
                  ),
                  if (bill.paidDate != null)
                    _buildDetailRow(
                      'Tanggal Bayar',
                      FormatHelper.formatShortDate(bill.paidDate!),
                    ),
                  if (bill.paymentMethod != null)
                    _buildDetailRow('Metode Bayar', bill.paymentMethod!),
                  const SizedBox(height: 24),
                  if (bill.status.toLowerCase() != 'paid')
                    SizedBox(
                      width: double.infinity,
                      height: 48,
                      child: ElevatedButton(
                        onPressed: () {
                          Navigator.pop(context);
                          _handlePayBill(bill);
                        },
                        child: const Text('BAYAR SEKARANG'),
                      ),
                    ),
                  const SizedBox(height: 8),
                  SizedBox(
                    width: double.infinity,
                    height: 48,
                    child: OutlinedButton(
                      onPressed: () => Navigator.pop(context),
                      child: const Text('TUTUP'),
                    ),
                  ),
                ],
              ),
            );
          },
        );
      },
    );
  }

  Widget _buildDetailRow(String label, String value,
      {bool isBold = false, Color? valueColor}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            label,
            style: const TextStyle(
              fontSize: 14,
              color: AppColors.onSurfaceVariant,
            ),
          ),
          Text(
            value,
            style: TextStyle(
              fontSize: 14,
              fontWeight: isBold ? FontWeight.bold : FontWeight.w500,
              color: valueColor ?? AppColors.onSurface,
            ),
          ),
        ],
      ),
    );
  }

  void _handlePayBill(Bill bill) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Konfirmasi Pembayaran'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Anda akan membayar tagihan:'),
            const SizedBox(height: 8),
            Text(
              FormatHelper.formatRupiah(bill.totalAmount),
              style: const TextStyle(
                fontSize: 20,
                fontWeight: FontWeight.bold,
                color: AppColors.primaryDark,
              ),
            ),
            const SizedBox(height: 4),
            Text('Periode: ${FormatHelper.formatPeriod(bill.period)}'),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('BATAL'),
          ),
          ElevatedButton(
            onPressed: () async {
              Navigator.pop(context);
              final success =
                  await ref.read(billsProvider.notifier).payBill(bill.id);
              if (mounted) {
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(
                    content: Text(
                      success
                          ? 'Pembayaran berhasil diproses'
                          : 'Gagal memproses pembayaran',
                    ),
                    backgroundColor:
                        success ? AppColors.success : AppColors.error,
                  ),
                );
              }
            },
            child: const Text('BAYAR'),
          ),
        ],
      ),
    );
  }
}
