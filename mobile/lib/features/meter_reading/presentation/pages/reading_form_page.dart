import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';
import '../../../../core/cache/sync_service.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/utils/gps_helper.dart';
import '../../../shared/widgets/app_card.dart';
import '../../domain/models/reading_entry.dart';
import '../providers/meter_provider.dart';

class ReadingFormPage extends ConsumerStatefulWidget {
  final int routeId;

  const ReadingFormPage({super.key, required this.routeId});

  @override
  ConsumerState<ReadingFormPage> createState() => _ReadingFormPageState();
}

class _ReadingFormPageState extends ConsumerState<ReadingFormPage> {
  final PageController _pageController = PageController();
  final ImagePicker _imagePicker = ImagePicker();
  int _currentIndex = 0;
  final Map<int, TextEditingController> _readingControllers = {};
  final Map<int, XFile?> _meterPhotos = {};
  final Map<int, XFile?> _housePhotos = {};

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(meterReadingsProvider.notifier).loadRouteTasks(widget.routeId);
    });
  }

  @override
  void dispose() {
    for (var c in _readingControllers.values) {
      c.dispose();
    }
    _pageController.dispose();
    super.dispose();
  }

  Future<void> _takePhoto(int id, bool isMeter) async {
    final photo = await _imagePicker.pickImage(
      source: ImageSource.camera,
      imageQuality: 80,
    );
    if (photo != null) {
      setState(() {
        if (isMeter) {
          _meterPhotos[id] = photo;
        } else {
          _housePhotos[id] = photo;
        }
      });
    }
  }

  Future<void> _handleSubmit(int id, int customerId) async {
    final controller = _readingControllers[id];
    if (controller == null || controller.text.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Harap masukkan angka meter'),
          backgroundColor: AppColors.error,
        ),
      );
      return;
    }

    final reading = double.tryParse(controller.text);
    if (reading == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Angka meter tidak valid'),
          backgroundColor: AppColors.error,
        ),
      );
      return;
    }

    Position? position;
    try {
      position = await GpsHelper.getCurrentPosition();
    } catch (_) {}

    final success =
        await ref.read(meterReadingsProvider.notifier).submitReading(
              id,
              reading,
              _meterPhotos[id]?.path,
              latitude: position?.latitude,
              longitude: position?.longitude,
            );

    if (success && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Pembacaan berhasil disimpan'),
          backgroundColor: AppColors.success,
        ),
      );

      if (_currentIndex < ref.read(meterReadingsProvider).readings.length - 1) {
        _pageController.nextPage(
          duration: const Duration(milliseconds: 300),
          curve: Curves.easeInOut,
        );
      }
      return;
    }

    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Offline: disimpan lokal, akan dikirim saat online'),
          backgroundColor: AppColors.warning,
          duration: Duration(seconds: 3),
        ),
      );
    }

    await ref.read(syncServiceProvider).cacheMeterReading(
          customerId: customerId,
          readingValue: reading,
          period:
              '${DateTime.now().year}-${DateTime.now().month.toString().padLeft(2, '0')}',
          photoMeterUrl: _meterPhotos[id]?.path,
          photoHouseUrl: _housePhotos[id]?.path,
          latitude: position?.latitude,
          longitude: position?.longitude,
        );

    if (mounted &&
        _currentIndex < ref.read(meterReadingsProvider).readings.length - 1) {
      _pageController.nextPage(
        duration: const Duration(milliseconds: 300),
        curve: Curves.easeInOut,
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final readingsState = ref.watch(meterReadingsProvider);
    final readings = readingsState.readings;
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: Text(
          readingsState.currentRoute?.routeName ?? 'Form Pembacaan',
        ),
      ),
      body: _buildBody(readingsState, readings, theme),
      bottomNavigationBar:
          readings.isNotEmpty && _currentIndex < readings.length
              ? SafeArea(
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: SizedBox(
                      height: 48,
                      child: ElevatedButton(
                        onPressed: readingsState.isSubmitting
                            ? null
                            : () => _handleSubmit(
                                  readings[_currentIndex].id,
                                  readings[_currentIndex].taskId,
                                ),
                        child: readingsState.isSubmitting
                            ? const SizedBox(
                                width: 20,
                                height: 20,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                  color: Colors.white,
                                ),
                              )
                            : const Text('SIMPAN PEMBACAAN'),
                      ),
                    ),
                  ),
                )
              : null,
    );
  }

  Widget _buildBody(
    MeterReadingsState state,
    List<ReadingEntry> readings,
    ThemeData theme,
  ) {
    if (state.dataState == MeterDataState.loading) {
      return const Center(child: CircularProgressIndicator());
    }

    if (state.dataState == MeterDataState.error) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text(state.errorMessage ?? 'Gagal memuat data'),
            const SizedBox(height: 12),
            ElevatedButton(
              onPressed: () {
                ref
                    .read(meterReadingsProvider.notifier)
                    .loadRouteTasks(widget.routeId);
              },
              child: const Text('COBA LAGI'),
            ),
          ],
        ),
      );
    }

    if (readings.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.check_circle_outline,
                size: 64, color: AppColors.success),
            const SizedBox(height: 16),
            const Text('Semua tugas selesai!'),
            const SizedBox(height: 12),
            ElevatedButton(
              onPressed: () => context.go('/meter-reading'),
              child: const Text('KEMBALI'),
            ),
          ],
        ),
      );
    }

    return Column(
      children: [
        _buildProgressIndicator(readings, theme),
        Expanded(
          child: PageView.builder(
            controller: _pageController,
            itemCount: readings.length,
            onPageChanged: (index) {
              setState(() {
                _currentIndex = index;
              });
            },
            itemBuilder: (context, index) {
              return _buildReadingPage(readings[index], theme);
            },
          ),
        ),
      ],
    );
  }

  Widget _buildProgressIndicator(List<ReadingEntry> readings, ThemeData theme) {
    final completed = readings.where((r) => r.status == 'completed').length;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      color: AppColors.surface,
      child: Column(
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                'Pelanggan ${_currentIndex + 1} dari ${readings.length}',
                style: const TextStyle(fontWeight: FontWeight.w600),
              ),
              Text(
                '$completed/${readings.length} selesai',
                style: const TextStyle(
                  color: AppColors.onSurfaceVariant,
                  fontSize: 13,
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          ClipRRect(
            borderRadius: BorderRadius.circular(4),
            child: LinearProgressIndicator(
              value: readings.isEmpty ? 0 : completed / readings.length,
              backgroundColor: AppColors.outline.withValues(alpha: 0.3),
              valueColor:
                  const AlwaysStoppedAnimation<Color>(AppColors.primary),
              minHeight: 6,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildReadingPage(ReadingEntry entry, ThemeData theme) {
    if (!_readingControllers.containsKey(entry.id)) {
      _readingControllers[entry.id] = TextEditingController(
        text: entry.currentReading?.toStringAsFixed(0) ?? '',
      );
    }

    final readingController = _readingControllers[entry.id]!;
    final meterPhoto = _meterPhotos[entry.id];
    final housePhoto = _housePhotos[entry.id];

    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          AppCard(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    const CircleAvatar(
                      backgroundColor: AppColors.primaryContainer,
                      child: Icon(Icons.person, color: AppColors.primary),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            entry.customerName,
                            style: theme.textTheme.titleMedium,
                          ),
                          Text(
                            'No. ${entry.customerNumber}',
                            style: const TextStyle(
                              color: AppColors.onSurfaceVariant,
                              fontSize: 13,
                            ),
                          ),
                        ],
                      ),
                    ),
                    if (entry.status == 'completed')
                      const Icon(Icons.check_circle, color: AppColors.success),
                  ],
                ),
                const SizedBox(height: 12),
                Text(
                  entry.address,
                  style: const TextStyle(
                    color: AppColors.onSurfaceVariant,
                    fontSize: 13,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          if (entry.previousReading != null) ...[
            AppCard(
              padding: const EdgeInsets.all(16),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text('Angka Meter Sebelumnya',
                      style: TextStyle(color: AppColors.onSurfaceVariant)),
                  Text(
                    '${entry.previousReading!.toStringAsFixed(0)} m\u00B3',
                    style: const TextStyle(
                      fontWeight: FontWeight.bold,
                      fontSize: 16,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
          ],
          Text(
            'Angka Meter Saat Ini',
            style: theme.textTheme.titleMedium,
          ),
          const SizedBox(height: 8),
          TextField(
            controller: readingController,
            keyboardType: TextInputType.number,
            textAlign: TextAlign.center,
            style: const TextStyle(
              fontSize: 32,
              fontWeight: FontWeight.bold,
              color: AppColors.primaryDark,
              letterSpacing: 4,
            ),
            decoration: InputDecoration(
              hintText: '000000',
              hintStyle: const TextStyle(
                fontSize: 32,
                color: AppColors.outline,
                letterSpacing: 4,
              ),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
              ),
              contentPadding: const EdgeInsets.symmetric(
                horizontal: 16,
                vertical: 20,
              ),
            ),
            readOnly: entry.status == 'completed',
          ),
          if (entry.previousReading != null &&
              readingController.text.isNotEmpty) ...[
            const SizedBox(height: 8),
            Center(
              child: Text(
                'Pemakaian: ${(double.tryParse(readingController.text) ?? 0) - entry.previousReading!} m\u00B3',
                style: const TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w600,
                  color: AppColors.primary,
                ),
              ),
            ),
          ],
          const SizedBox(height: 24),
          Text(
            'Foto Dokumentasi',
            style: theme.textTheme.titleMedium,
          ),
          const SizedBox(height: 8),
          Row(
            children: [
              Expanded(
                child: _buildPhotoBox(
                  label: 'Foto Rumah',
                  photo: housePhoto,
                  onTap: () => _takePhoto(entry.id, false),
                  icon: Icons.home_outlined,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: _buildPhotoBox(
                  label: 'Foto Meter',
                  photo: meterPhoto,
                  onTap: () => _takePhoto(entry.id, true),
                  icon: Icons.speed_outlined,
                ),
              ),
            ],
          ),
          const SizedBox(height: 24),
          Text(
            'Catatan',
            style: theme.textTheme.titleMedium,
          ),
          const SizedBox(height: 8),
          TextField(
            maxLines: 3,
            decoration: InputDecoration(
              hintText: 'Tambah catatan (opsional)',
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
              ),
            ),
          ),
          const SizedBox(height: 16),
        ],
      ),
    );
  }

  Widget _buildPhotoBox({
    required String label,
    required XFile? photo,
    required VoidCallback onTap,
    required IconData icon,
  }) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        height: 150,
        decoration: BoxDecoration(
          color: AppColors.surfaceVariant,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(
            color: AppColors.outline,
            style: BorderStyle.solid,
          ),
        ),
        child: photo != null
            ? ClipRRect(
                borderRadius: BorderRadius.circular(11),
                child: Stack(
                  fit: StackFit.expand,
                  children: [
                    Image.file(
                      File(photo.path),
                      fit: BoxFit.cover,
                    ),
                    Positioned(
                      bottom: 0,
                      left: 0,
                      right: 0,
                      child: Container(
                        padding: const EdgeInsets.all(8),
                        color: Colors.black54,
                        child: Text(
                          label,
                          textAlign: TextAlign.center,
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 12,
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              )
            : Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(icon, size: 36, color: AppColors.onSurfaceVariant),
                  const SizedBox(height: 8),
                  Text(
                    label,
                    style: const TextStyle(
                      color: AppColors.onSurfaceVariant,
                      fontSize: 13,
                    ),
                  ),
                  const SizedBox(height: 4),
                  const Text(
                    'Ketuk untuk foto',
                    style: TextStyle(
                      color: AppColors.primary,
                      fontSize: 11,
                    ),
                  ),
                ],
              ),
      ),
    );
  }
}
