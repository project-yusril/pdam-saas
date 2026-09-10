import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../core/theme/app_colors.dart';
import '../../domain/models/work_order.dart';
import '../../presentation/providers/field_provider.dart';

class WorkOrdersPage extends ConsumerStatefulWidget {
  const WorkOrdersPage({super.key});

  @override
  ConsumerState<WorkOrdersPage> createState() => _WorkOrdersPageState();
}

class _WorkOrdersPageState extends ConsumerState<WorkOrdersPage> {
  String _statusFilter = '';

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(workOrdersProvider.notifier).load();
    });
  }

  List<String> get _statusOptions => ['open', 'assigned', 'in_progress'];
  String get currentLabel => _statusFilter.isEmpty ? 'Semua' : _statusFilter;

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(workOrdersProvider);
    final loading = state.dataState == WorkDataState.loading;

    return Scaffold(
      appBar: AppBar(
        title: const Text('WO Saya'),
        actions: [
          PopupMenuButton<String>(
            icon: const Icon(Icons.filter_list_rounded),
            tooltip: 'Filter',
            onSelected: (s) {
              setState(() {
                _statusFilter = s;
              });
              ref.read(workOrdersProvider.notifier).load(status: s);
            },
            itemBuilder: (_) => [
              const PopupMenuItem(value: '', child: Text('Semua')),
              ..._statusOptions.map((e) => PopupMenuItem(value: e, child: Text(e))),
            ],
          ),
          IconButton(onPressed: () => ref.read(workOrdersProvider.notifier).load(), icon: const Icon(Icons.refresh_rounded)),
        ],
      ),
      body: loading
          ? const Center(child: CircularProgressIndicator())
          : state.dataState == WorkDataState.error
              ? Center(child: Text(state.errorMessage ?? 'Gagal memuat'))
              : RefreshIndicator(
                  onRefresh: () async => ref.read(workOrdersProvider.notifier).load(status: _statusFilter),
                  child: ListView.builder(
                    itemCount: state.items.length,
                    padding: const EdgeInsets.all(12),
                    itemBuilder: (_, i) => _buildItem(context, ref, i),
                  ),
                ),
      floatingActionButton: FloatingActionButton(
        onPressed: () {},
        backgroundColor: AppColors.primary,
        child: const Icon(Icons.local_shipping_outlined),
        tooltip: 'Buat WO', // placeholder utk fitur tambahan nanti
      ),
    );
  }

  Widget _buildItem(BuildContext context, WidgetRef ref, int index) {
    final state = ref.watch(workOrdersProvider);
    final item = state.items[index] as WorkOrderModel;
    final isBusy = state.busyId == item.id;

    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: InkWell(
        onTap: () {},
        child: Padding(
          padding: const EdgeInsets.all(10),
          child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Expanded(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(item.typeLabel, style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.bold)),
                Text(item.woNumber, style: TextStyle(color: Colors.grey[600], fontSize: 13)),
                const SizedBox(height: 4),
                Text(item.address, maxLines: 1, overflow: TextOverflow.ellipsis),
                if (item.slaDueAt != null)
                  Text(
                    'SLA: ${item.slaDueAt}',
                    style: TextStyle(fontSize: 11, color: item.priority == 'urgent' ? Colors.red : Colors.grey[700]),
                  ),
                if (item.gisFeatureId != null)
                  Row(mainAxisSize: MainAxisSize.min, children: [
                    Icon(Icons.location_on_rounded, size: 14, color: AppColors.primary),
                    const SizedBox(width: 4),
                    Text('GIS #$item.sourceType', style: TextStyle(fontSize: 12, color: AppColors.secondary)),
                  ]),
              ]),
            ),
            ElevatedButton(
              onPressed: isBusy || !item.canStart ? null : () => _doStart(ref, item),
              style: ElevatedButton.styleFrom(
                backgroundColor: item.status == 'in_progress' ? AppColors.info : AppColors.success,
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
              ),
              child: isBusy
                  ? const SizedBox.shrink()
                  : item.status == 'in_progress'
                      ? const Icon(Icons.speed, size: 20)
                      : const Text('Mulai'),
            ),
            const SizedBox(width: 8),
            OutlinedButton.icon(
              onPressed: isBusy || !item.canComplete ? null : () => _showCompleteDialog(context, ref, item),
              style: OutlinedButton.styleFrom(padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6)),
              icon: Icon(isBusy ? Icons.sync_alt_rounded : Icons.check_rounded, color: AppColors.success),
              label: item.canComplete ? const Text('Selesai') : const SizedBox(),
            ),
          ]),
        ),
      ),
    );
  }

  Future<void> _doStart(WidgetRef ref, WorkOrderModel item) async {
    final state = ref.read(workOrdersProvider);
    if (state.busyId == item.id) return; // prevent double tap
    await ref.read(workOrdersProvider.notifier).start(item.id);
    // show snackbar? Not needed — state refreshes on completion
  }

  void _showCompleteDialog(BuildContext context, WidgetRef ref, WorkOrderModel item) async {
    final controller = TextEditingController();

    await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('Selesaikan WO'),
        content: Column(mainAxisSize: MainAxisSize.min, children: [
          TextField(controller: controller, decoration: const InputDecoration(labelText: 'Hasil pekerjaan')),
        ]),
        actions: [
          TextButton(onPressed: () => Navigator.of(context).pop(), child: const Text('Batal')),
          ElevatedButton(onPressed: () async {
            final res = controller.text.trim();
            if (res.isEmpty) {
              ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Isi hasil pekerjaan')));
              return;
            }
            // Call provider.complete() via notifier
            await ref.read(workOrdersProvider.notifier).complete(item.id, res);
            Navigator.of(context).pop(true);
          }, child: const Text('Simpan')),
        ],
      ),
    );
  }
}
