import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../core/theme/app_colors.dart';
import '../../presentation/providers/field_provider.dart';

/// Tombol "Lapor Lokasi" — update titik petugas live di peta GIS kantor.
class LocationReportButton extends ConsumerWidget {
  const LocationReportButton({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(locationProvider);
    final bool isSending = state.status == LocationReportState.sending;

    return Tooltip(
      message: state.lastReportedAt != null
          ? 'Posisi terakhir: ${state.lastReportedAt!.toLocal()}'
          : 'Klik untuk kirim GPS',
      child: IconButton(
        onPressed: isSending ? null : () => _report(context, ref),
        icon: Icon(
            isSending ? Icons.sync_alt_rounded : Icons.my_location_rounded),
        color: Colors.white,
        style: IconButton.styleFrom(
            foregroundColor: Colors.white, backgroundColor: AppColors.primary),
      ),
    );
  }

  static Future<void> _report(BuildContext context, WidgetRef ref) async {
    await ref.read(locationProvider.notifier).report();
    ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
        content: Text('Berhasil lapor lokasi'),
        backgroundColor: Color(0xFF43A047)));
  }
}
