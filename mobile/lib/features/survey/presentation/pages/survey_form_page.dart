import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';

import '../../../../core/theme/app_colors.dart';
import '../../../../core/utils/gps_helper.dart';
import '../../domain/models/survey_form.dart';
import '../../domain/models/survey_task.dart';
import '../providers/survey_provider.dart';

class SurveyFormPage extends ConsumerStatefulWidget {
  final int taskId;

  const SurveyFormPage({super.key, required this.taskId});

  @override
  ConsumerState<SurveyFormPage> createState() => _SurveyFormPageState();
}

class _SurveyFormPageState extends ConsumerState<SurveyFormPage> {
  final _formKey = GlobalKey<FormState>();
  final ImagePicker _imagePicker = ImagePicker();
  XFile? _housePhoto1;
  XFile? _housePhoto2;
  XFile? _housePhoto3;

  String _houseCondition = 'baik';
  String _roadAccess = 'aspal';
  String _landStatus = 'hak_milik';
  String? _existingPipeSize;
  String? _waterSource;
  final _pipeDistanceController = TextEditingController();
  final _notesController = TextEditingController();
  String _recommendation = 'recommended';
  double? _houseLatitude;
  double? _houseLongitude;
  bool _isGettingLocation = false;

  final List<SurveyChecklistItem> _checklist = [
    const SurveyChecklistItem(
        label: 'Dokumen identitas lengkap', checked: false),
    const SurveyChecklistItem(
        label: 'Lokasi dapat dijangkau kendaraan', checked: false),
    const SurveyChecklistItem(
        label: 'Tersedia sumber air terdekat', checked: false),
    const SurveyChecklistItem(
        label: 'Tidak ada sengketa lahan', checked: false),
    const SurveyChecklistItem(
        label: 'Izin tetangga/lingkungan', checked: false),
  ];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(surveyFormProvider.notifier).loadTaskDetail(widget.taskId);
    });
  }

  @override
  void dispose() {
    _pipeDistanceController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  Future<void> _takePhoto(int index) async {
    final photo = await _imagePicker.pickImage(
      source: ImageSource.camera,
      imageQuality: 80,
    );
    if (photo != null) {
      setState(() {
        switch (index) {
          case 0:
            _housePhoto1 = photo;
          case 1:
            _housePhoto2 = photo;
          case 2:
            _housePhoto3 = photo;
        }
      });
    }
  }

  Future<void> _getLocation() async {
    setState(() => _isGettingLocation = true);
    try {
      final position = await GpsHelper.getCurrentPosition();
      setState(() {
        _houseLatitude = position.latitude;
        _houseLongitude = position.longitude;
        _isGettingLocation = false;
      });
    } catch (e) {
      setState(() => _isGettingLocation = false);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Gagal mendapatkan lokasi GPS'),
            backgroundColor: AppColors.error,
          ),
        );
      }
    }
  }

  Future<void> _handleSubmit() async {
    if (!_formKey.currentState!.validate()) return;

    final photos = <String>[
      if (_housePhoto1 != null) _housePhoto1!.path,
      if (_housePhoto2 != null) _housePhoto2!.path,
      if (_housePhoto3 != null) _housePhoto3!.path,
    ];

    final formData = SurveyFormData(
      taskId: widget.taskId,
      houseCondition: _houseCondition,
      roadAccess: _roadAccess,
      pipeDistance: double.tryParse(_pipeDistanceController.text),
      landStatus: _landStatus,
      existingPipeSize: _existingPipeSize,
      waterSource: _waterSource,
      houseLatitude: _houseLatitude,
      houseLongitude: _houseLongitude,
      photos: photos,
      recommendation: _recommendation,
      notes: _notesController.text.isNotEmpty ? _notesController.text : null,
      checklist: _checklist,
    );

    final success =
        await ref.read(surveyFormProvider.notifier).submitSurvey(formData);

    if (success && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Survey berhasil dikirim'),
          backgroundColor: AppColors.success,
        ),
      );
      context.go('/survey');
    }
  }

  Future<void> _handleSaveDraft() async {
    final photos = <String>[
      if (_housePhoto1 != null) _housePhoto1!.path,
      if (_housePhoto2 != null) _housePhoto2!.path,
      if (_housePhoto3 != null) _housePhoto3!.path,
    ];

    final formData = SurveyFormData(
      taskId: widget.taskId,
      houseCondition: _houseCondition,
      roadAccess: _roadAccess,
      pipeDistance: double.tryParse(_pipeDistanceController.text),
      landStatus: _landStatus,
      existingPipeSize: _existingPipeSize,
      waterSource: _waterSource,
      houseLatitude: _houseLatitude,
      houseLongitude: _houseLongitude,
      photos: photos,
      recommendation: _recommendation,
      notes: _notesController.text.isNotEmpty ? _notesController.text : null,
      checklist: _checklist,
    );

    final success =
        await ref.read(surveyFormProvider.notifier).saveDraft(formData);

    if (success && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Draft disimpan'),
          backgroundColor: AppColors.success,
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final formState = ref.watch(surveyFormProvider);
    final task = formState.task;
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Form Survey'),
        actions: [
          TextButton(
            onPressed: _handleSaveDraft,
            child: const Text('Simpan Draft'),
          ),
        ],
      ),
      body: formState.dataState == SurveyDataState.loading
          ? const Center(child: CircularProgressIndicator())
          : formState.dataState == SurveyDataState.error
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Text(formState.errorMessage ?? 'Gagal memuat data'),
                      const SizedBox(height: 12),
                      ElevatedButton(
                        onPressed: () {
                          ref
                              .read(surveyFormProvider.notifier)
                              .loadTaskDetail(widget.taskId);
                        },
                        child: const Text('COBA LAGI'),
                      ),
                    ],
                  ),
                )
              : Form(
                  key: _formKey,
                  child: SingleChildScrollView(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        _buildApplicantInfo(task, theme),
                        const SizedBox(height: 20),
                        _buildSectionTitle(theme, 'Foto Lokasi'),
                        const SizedBox(height: 8),
                        _buildPhotoSection(),
                        const SizedBox(height: 20),
                        _buildSectionTitle(theme, 'Kondisi Rumah & Lingkungan'),
                        const SizedBox(height: 8),
                        _buildDropdownField(
                          label: 'Kondisi Rumah',
                          value: _houseCondition,
                          items: const [
                            DropdownMenuItem(
                                value: 'baik', child: Text('Baik')),
                            DropdownMenuItem(
                                value: 'cukup', child: Text('Cukup')),
                            DropdownMenuItem(
                                value: 'rusak', child: Text('Rusak')),
                          ],
                          onChanged: (v) =>
                              setState(() => _houseCondition = v!),
                        ),
                        const SizedBox(height: 12),
                        _buildDropdownField(
                          label: 'Akses Jalan',
                          value: _roadAccess,
                          items: const [
                            DropdownMenuItem(
                                value: 'aspal', child: Text('Aspal')),
                            DropdownMenuItem(
                                value: 'beton', child: Text('Beton')),
                            DropdownMenuItem(
                                value: 'tanah', child: Text('Tanah')),
                            DropdownMenuItem(
                                value: 'tidak_ada', child: Text('Tidak Ada')),
                          ],
                          onChanged: (v) => setState(() => _roadAccess = v!),
                        ),
                        const SizedBox(height: 12),
                        TextFormField(
                          controller: _pipeDistanceController,
                          keyboardType: TextInputType.number,
                          decoration: const InputDecoration(
                            labelText: 'Jarak Pipa Terdekat (meter)',
                            suffixText: 'm',
                            hintText: 'Masukkan jarak dalam meter',
                          ),
                        ),
                        const SizedBox(height: 12),
                        _buildDropdownField(
                          label: 'Status Lahan',
                          value: _landStatus,
                          items: const [
                            DropdownMenuItem(
                                value: 'hak_milik', child: Text('Hak Milik')),
                            DropdownMenuItem(
                                value: 'sewa', child: Text('Sewa')),
                            DropdownMenuItem(
                                value: 'tanah_negara',
                                child: Text('Tanah Negara')),
                          ],
                          onChanged: (v) => setState(() => _landStatus = v!),
                        ),
                        const SizedBox(height: 20),
                        _buildSectionTitle(theme, 'Teknis'),
                        const SizedBox(height: 8),
                        _buildDropdownField(
                          label: 'Ukuran Pipa Existing',
                          value: _existingPipeSize,
                          hint: 'Pilih ukuran (opsional)',
                          items: const [
                            DropdownMenuItem(
                                value: '1/2', child: Text('1/2 inch')),
                            DropdownMenuItem(
                                value: '3/4', child: Text('3/4 inch')),
                            DropdownMenuItem(value: '1', child: Text('1 inch')),
                            DropdownMenuItem(value: '2', child: Text('2 inch')),
                          ],
                          onChanged: (v) =>
                              setState(() => _existingPipeSize = v),
                        ),
                        const SizedBox(height: 12),
                        _buildDropdownField(
                          label: 'Sumber Air',
                          value: _waterSource,
                          hint: 'Pilih sumber air (opsional)',
                          items: const [
                            DropdownMenuItem(
                                value: 'pdam', child: Text('PDAM')),
                            DropdownMenuItem(
                                value: 'sumur', child: Text('Sumur')),
                            DropdownMenuItem(
                                value: 'sungai', child: Text('Sungai')),
                            DropdownMenuItem(
                                value: 'mata_air', child: Text('Mata Air')),
                          ],
                          onChanged: (v) => setState(() => _waterSource = v),
                        ),
                        const SizedBox(height: 20),
                        _buildSectionTitle(theme, 'GPS & Lokasi'),
                        const SizedBox(height: 8),
                        Container(
                          padding: const EdgeInsets.all(16),
                          decoration: BoxDecoration(
                            color: AppColors.surface,
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(color: AppColors.outline),
                          ),
                          child: Column(
                            children: [
                              if (_houseLatitude != null) ...[
                                Text(
                                  'Lat: ${_houseLatitude!.toStringAsFixed(6)}, '
                                  'Lng: ${_houseLongitude!.toStringAsFixed(6)}',
                                  style: const TextStyle(
                                    fontWeight: FontWeight.w600,
                                    fontSize: 14,
                                  ),
                                ),
                                const SizedBox(height: 12),
                              ],
                              SizedBox(
                                height: 44,
                                child: OutlinedButton.icon(
                                  onPressed:
                                      _isGettingLocation ? null : _getLocation,
                                  icon: _isGettingLocation
                                      ? const SizedBox(
                                          width: 16,
                                          height: 16,
                                          child: CircularProgressIndicator(
                                              strokeWidth: 2),
                                        )
                                      : const Icon(Icons.gps_fixed),
                                  label: Text(
                                    _houseLatitude != null
                                        ? 'Ambil Ulang Lokasi'
                                        : 'Ambil Lokasi GPS',
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 20),
                        _buildSectionTitle(theme, 'Checklist Survey'),
                        const SizedBox(height: 8),
                        ...List.generate(_checklist.length, (index) {
                          return CheckboxListTile(
                            value: _checklist[index].checked,
                            onChanged: (v) {
                              setState(() {
                                _checklist[index] =
                                    _checklist[index].copyWith(checked: v);
                              });
                            },
                            title: Text(
                              _checklist[index].label,
                              style: const TextStyle(fontSize: 14),
                            ),
                            activeColor: AppColors.primary,
                            contentPadding:
                                const EdgeInsets.symmetric(horizontal: 8),
                            controlAffinity: ListTileControlAffinity.leading,
                            dense: true,
                          );
                        }),
                        const SizedBox(height: 20),
                        _buildSectionTitle(theme, 'Rekomendasi'),
                        const SizedBox(height: 8),
                        _buildDropdownField(
                          label: 'Rekomendasi Teknis',
                          value: _recommendation,
                          items: const [
                            DropdownMenuItem(
                                value: 'recommended',
                                child: Text('Direkomendasikan')),
                            DropdownMenuItem(
                                value: 'not_recommended',
                                child: Text('Tidak Direkomendasikan')),
                            DropdownMenuItem(
                                value: 'conditional', child: Text('Bersyarat')),
                          ],
                          onChanged: (v) =>
                              setState(() => _recommendation = v!),
                        ),
                        const SizedBox(height: 12),
                        TextFormField(
                          controller: _notesController,
                          maxLines: 3,
                          decoration: const InputDecoration(
                            labelText: 'Catatan',
                            hintText: 'Tambahkan catatan atau keterangan...',
                            alignLabelWithHint: true,
                          ),
                        ),
                        const SizedBox(height: 32),
                        SizedBox(
                          width: double.infinity,
                          height: 50,
                          child: ElevatedButton(
                            onPressed:
                                formState.isSubmitting ? null : _handleSubmit,
                            child: formState.isSubmitting
                                ? const SizedBox(
                                    width: 20,
                                    height: 20,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2,
                                      color: Colors.white,
                                    ),
                                  )
                                : const Text('KIRIM SURVEY'),
                          ),
                        ),
                        const SizedBox(height: 8),
                        SizedBox(
                          width: double.infinity,
                          height: 50,
                          child: OutlinedButton(
                            onPressed: _handleSaveDraft,
                            child: const Text('SIMPAN DRAFT'),
                          ),
                        ),
                        const SizedBox(height: 32),
                      ],
                    ),
                  ),
                ),
    );
  }

  Widget _buildApplicantInfo(SurveyTask? task, ThemeData theme) {
    if (task == null) return const SizedBox.shrink();

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.primaryContainer.withValues(alpha: 0.4),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.primaryContainer),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              CircleAvatar(
                backgroundColor: AppColors.primary,
                child: Text(
                  task.applicantName.isNotEmpty
                      ? task.applicantName[0].toUpperCase()
                      : '?',
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(task.applicantName,
                        style: theme.textTheme.titleMedium),
                    Text(task.applicantPhone,
                        style:
                            const TextStyle(color: AppColors.onSurfaceVariant)),
                  ],
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: AppColors.primary,
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Text(
                  task.surveyTypeLabel,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 11,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Row(
            children: [
              const Icon(Icons.location_on_outlined,
                  size: 14, color: AppColors.onSurfaceVariant),
              const SizedBox(width: 4),
              Expanded(
                child: Text(
                  task.address,
                  style: const TextStyle(
                    fontSize: 13,
                    color: AppColors.onSurfaceVariant,
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildPhotoSection() {
    return Row(
      children: [
        Expanded(
            child: _buildPhotoBox('Depan', _housePhoto1, () => _takePhoto(0))),
        const SizedBox(width: 12),
        Expanded(
            child:
                _buildPhotoBox('Samping', _housePhoto2, () => _takePhoto(1))),
        const SizedBox(width: 12),
        Expanded(
            child: _buildPhotoBox(
                'Lingkungan', _housePhoto3, () => _takePhoto(2))),
      ],
    );
  }

  Widget _buildPhotoBox(String label, XFile? photo, VoidCallback onTap) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        height: 120,
        decoration: BoxDecoration(
          color: AppColors.surfaceVariant,
          borderRadius: BorderRadius.circular(10),
          border: Border.all(color: AppColors.outline),
        ),
        child: photo != null
            ? ClipRRect(
                borderRadius: BorderRadius.circular(9),
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
                        padding: const EdgeInsets.all(6),
                        color: Colors.black54,
                        child: Text(
                          label,
                          textAlign: TextAlign.center,
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 11,
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
                  const Icon(Icons.add_a_photo_outlined,
                      size: 28, color: AppColors.onSurfaceVariant),
                  const SizedBox(height: 4),
                  Text(
                    label,
                    style: const TextStyle(
                      color: AppColors.onSurfaceVariant,
                      fontSize: 12,
                    ),
                  ),
                ],
              ),
      ),
    );
  }

  Widget _buildSectionTitle(ThemeData theme, String title) {
    return Text(title, style: theme.textTheme.titleMedium);
  }

  Widget _buildDropdownField({
    required String label,
    required String? value,
    required List<DropdownMenuItem<String>> items,
    required ValueChanged<String?> onChanged,
    String? hint,
  }) {
    return DropdownButtonFormField<String>(
      initialValue: value,
      items: items,
      onChanged: onChanged,
      decoration: InputDecoration(
        labelText: label,
        hintText: hint,
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
      ),
    );
  }
}
